```php
<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: login.php");
    exit();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

$namaGuru = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Guru';

// Statistik siswa
$totalSiswa = 0;
$q = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users WHERE role = 'siswa'");
if ($q) {
    $totalSiswa = (int)mysqli_fetch_assoc($q)['total'];
}

// Statistik setoran sampah
$totalSetoran = 0;
$q = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi");
if ($q) {
    $totalSetoran = (int)mysqli_fetch_assoc($q)['total'];
}

// Total saldo seluruh nasabah
$totalSaldo = 0;
$q = mysqli_query($koneksi, "SELECT COALESCE(SUM(saldo), 0) AS total FROM nasabah");
if ($q) {
    $totalSaldo = (float)mysqli_fetch_assoc($q)['total'];
}

// Total penarikan saldo
$totalPenarikan = 0;
$q = mysqli_query($koneksi, "SELECT COALESCE(SUM(jumlah), 0) AS total FROM penarikan");
if ($q) {
    $totalPenarikan = (float)mysqli_fetch_assoc($q)['total'];
}

// Riwayat setoran sampah dan penarikan saldo
$riwayat = [];

$sqlRiwayat = "
    SELECT
        t.tanggal AS tanggal,
        n.nama AS nama,
        'Setor Sampah' AS jenis,
        CONCAT(
            'Setoran ',
            COALESCE(j.nama_sampah, 'Sampah'),
            ' - ',
            t.berat,
            ' kg'
        ) AS keterangan,
        t.total AS nominal,
        'masuk' AS arah
    FROM transaksi t
    LEFT JOIN nasabah n ON t.id_nasabah = n.id_nasabah
    LEFT JOIN jenis_sampah j ON t.id_sampah = j.id_sampah

    UNION ALL

    SELECT
        p.tanggal AS tanggal,
        n.nama AS nama,
        'Tarik Saldo' AS jenis,
        'Penarikan saldo nasabah' AS keterangan,
        p.jumlah AS nominal,
        'keluar' AS arah
    FROM penarikan p
    LEFT JOIN nasabah n ON p.id_nasabah = n.id_nasabah

    ORDER BY tanggal DESC
    LIMIT 15
";

$q = mysqli_query($koneksi, $sqlRiwayat);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $riwayat[] = $row;
    }
}

// Data siswa
$siswa = [];
$q = mysqli_query(
    $koneksi,
    "SELECT id_nasabah, kode_nasabah, nama, no_hp, saldo
     FROM nasabah
     ORDER BY nama ASC
     LIMIT 10"
);

if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $siswa[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Guru | Bank Sampah</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --red: #d71932;
    --dark-red: #9e1024;
    --blue: #123d79;
    --dark-blue: #092650;
    --bg: #f2f5fb;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    color: #192943;
    font-family: Arial, sans-serif;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 245px;
    padding: 24px 15px;
    overflow-y: auto;
    color: white;
    background: linear-gradient(
        160deg,
        var(--red),
        var(--dark-red) 55%,
        var(--dark-blue)
    );
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 5px 5px 24px;
    border-bottom: 1px solid #ffffff44;
}

.brand-icon {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
    flex-shrink: 0;
    border-radius: 50%;
    color: var(--red);
    background: white;
    font-size: 25px;
}

.brand strong {
    font-size: 15px;
}

.brand small {
    display: block;
    font-size: 10px;
    color: #ffe1e5;
}

.menu-title {
    margin: 25px 10px 10px;
    color: #ffdbe0;
    font-size: 10px;
    letter-spacing: 1.5px;
}

.menu-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    margin-bottom: 6px;
    color: white;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    transition: .2s;
}

.menu-link i {
    font-size: 18px;
    width: 22px;
    text-align: center;
    flex-shrink: 0;
}

.menu-link:hover,
.menu-link.active {
    color: var(--blue);
    background: white;
}

/* KONTEN */
.main {
    margin-left: 245px;
    padding: 28px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 24px;
}

.topbar h3 {
    font-weight: 800;
    margin: 0 0 5px;
}

.muted {
    color: #78849a;
    font-size: 13px;
}

.user-chip {
    padding: 10px 14px;
    background: white;
    border: 1px solid #e3e9f3;
    border-radius: 12px;
    font-size: 13px;
}

/* BANNER */
.hero {
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
    padding: 28px;
    color: white;
    border-radius: 18px;
    background: linear-gradient(
        110deg,
        var(--red),
        #a91634 48%,
        var(--blue)
    );
}

.hero h2 {
    font-weight: 900;
}

.hero p {
    max-width: 580px;
    font-size: 13px;
    line-height: 1.7;
    margin: 0;
}

.hero-icon {
    position: absolute;
    right: 25px;
    top: 10px;
    font-size: 90px;
    opacity: .16;
}

/* STATISTIK */
.stat-card {
    height: 100%;
    padding: 20px;
    background: white;
    border: 1px solid #e4eaf3;
    border-radius: 16px;
    box-shadow: 0 5px 18px #102c5008;
    transition: .2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    margin-bottom: 15px;
    color: white;
    background: var(--blue);
    border-radius: 12px;
    font-size: 21px;
}

.stat-icon.red {
    background: var(--red);
}

.stat-icon.light {
    color: var(--blue);
    background: #e8efff;
}

.stat-label {
    color: #78849a;
    font-size: 12px;
    margin-bottom: 7px;
}

.stat-value {
    font-size: 24px;
    font-weight: 900;
    overflow-wrap: anywhere;
}

/* MENU AKSI */
.action-card {
    display: block;
    height: 100%;
    padding: 22px;
    color: #192943;
    background: white;
    border: 1px solid #e4eaf3;
    border-radius: 16px;
    text-decoration: none;
    transition: .2s;
}

.action-card:hover {
    transform: translateY(-4px);
    border-color: var(--red);
    box-shadow: 0 8px 22px #102c5010;
    color: var(--blue);
}

.action-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    margin-bottom: 15px;
    color: white;
    background: var(--red);
    border-radius: 13px;
    font-size: 23px;
}

.action-icon.blue {
    background: var(--blue);
}

.action-card h6 {
    font-weight: 800;
    margin-bottom: 7px;
}

.action-card p {
    margin: 0;
    color: #78849a;
    font-size: 12px;
    line-height: 1.7;
}

/* TABEL */
.section-card {
    background: white;
    border: 1px solid #e4eaf3;
    border-radius: 16px;
    overflow: hidden;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 19px 20px;
    border-bottom: 1px solid #edf0f6;
}

.section-heading h5 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
}

.section-heading i {
    color: var(--red);
}

.table-responsive {
    padding: 0 14px 10px;
}

.table {
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
}

.table thead th {
    padding-top: 14px;
    padding-bottom: 14px;
    background: #f7f9fd;
    color: #758198;
    font-size: 10px;
    text-transform: uppercase;
}

.table tbody td {
    padding-top: 12px;
    padding-bottom: 12px;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.status-masuk {
    background: #d9f7e6;
    color: #146b3c;
}

.status-keluar {
    background: #ffe0e4;
    color: #a20f28;
}

.footer {
    text-align: center;
    color: #8a95a7;
    font-size: 11px;
    padding: 25px 0 5px;
}

/* RESPONSIVE */
@media(max-width:800px) {
    .sidebar {
        width: 70px;
        padding: 18px 8px;
    }

    .brand {
        justify-content: center;
        padding: 5px 0 22px;
    }

    .brand-text,
    .menu-title,
    .menu-text {
        display: none;
    }

    .menu-link {
        justify-content: center;
        padding: 13px 5px;
    }

    .main {
        margin-left: 70px;
        padding: 20px 13px;
    }
}

@media(max-width:480px) {
    .main {
        padding: 16px 10px;
    }

    .topbar h3 {
        font-size: 19px;
    }

    .user-chip {
        padding: 8px;
        font-size: 11px;
    }

    .hero {
        padding: 22px 18px;
    }

    .hero h2 {
        font-size: 22px;
    }

    .hero-icon {
        right: 4px;
        font-size: 55px;
    }

    .stat-value {
        font-size: 21px;
    }

    .section-heading {
        padding: 15px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-shield-fill-check"></i>
        </div>
        <div class="brand-text">
            <strong>BANK SAMPAH</strong>
            <small>SPIDER-MAN EDITION</small>
        </div>
    </div>

    <div class="menu-title">MENU GURU</div>

    <a href="dashboard_guru.php" class="menu-link active">
        <i class="bi bi-grid-fill"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="transaksi/tambah.php" class="menu-link">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Setor Sampah</span>
    </a>

    <a href="penarikan/tambah.php" class="menu-link">
        <i class="bi bi-cash-coin"></i>
        <span class="menu-text">Tarik Saldo</span>
    </a>

    <a href="#riwayat" class="menu-link">
        <i class="bi bi-clock-history"></i>
        <span class="menu-text">Riwayat Transaksi</span>
    </a>

    <a href="#siswa" class="menu-link">
        <i class="bi bi-people-fill"></i>
        <span class="menu-text">Data Siswa</span>
    </a>

    <div class="menu-title">AKUN</div>

    <a href="logout.php" class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-left"></i>
        <span class="menu-text">Logout</span>
    </a>
</aside>

<!-- KONTEN UTAMA -->
<main class="main">

    <div class="topbar">
        <div>
            <h3>Dashboard Guru</h3>
            <div class="muted">
                Kelola setoran sampah dan pantau transaksi Bank Sampah.
            </div>
        </div>

        <div class="user-chip">
            <i class="bi bi-person-workspace text-primary me-1"></i>
            <?= e($namaGuru) ?>
        </div>
    </div>

    <!-- BANNER -->
    <section class="hero">
        <span class="badge border border-light mb-2">
            SPIDER-MAN EDITION
        </span>

        <h2>Halo, <?= e($namaGuru) ?>! 🕷️</h2>

        <p>
            Catat setoran sampah, kelola penarikan saldo siswa,
            dan pantau riwayat transaksi Bank Sampah sekolah.
        </p>

        <i class="bi bi-shield-shaded hero-icon"></i>
    </section>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-label">Jumlah Akun Siswa</div>
                <div class="stat-value">
                    <?= number_format($totalSiswa, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-recycle"></i>
                </div>
                <div class="stat-label">Jumlah Setoran Sampah</div>
                <div class="stat-value">
                    <?= number_format($totalSetoran, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon light">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-label">Total Saldo Nasabah</div>
                <div class="stat-value">
                    <?= rupiah($totalSaldo) ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label">Total Penarikan Saldo</div>
                <div class="stat-value">
                    <?= rupiah($totalPenarikan) ?>
                </div>
            </div>
        </div>

    </div>

    <!-- MENU SETOR SAMPAH DAN TARIK SALDO -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-md-6">
            <a href="transaksi/tambah.php" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-recycle"></i>
                </div>

                <h6>Setor Sampah</h6>

                <p>
                    Catat setoran sampah siswa berdasarkan jenis sampah,
                    berat, dan harga per kilogram. Saldo nasabah bertambah
                    sesuai nilai setoran yang dicatat.
                </p>

                <span class="btn btn-danger btn-sm mt-3">
                    <i class="bi bi-plus-circle me-1"></i>
                    Tambah Setoran
                </span>
            </a>
        </div>

        <div class="col-12 col-md-6">
            <a href="penarikan/tambah.php" class="action-card">
                <div class="action-icon blue">
                    <i class="bi bi-cash-coin"></i>
                </div>

                <h6>Tarik Saldo</h6>

                <p>
                    Catat penarikan saldo siswa dan pastikan saldo
                    mencukupi sebelum penarikan diproses.
                </p>

                <span class="btn btn-primary btn-sm mt-3">
                    <i class="bi bi-arrow-up-right-circle me-1"></i>
                    Tarik Saldo
                </span>
            </a>
        </div>

    </div>

    <!-- RIWAYAT TRANSAKSI -->
    <section class="section-card mb-4" id="riwayat">
        <div class="section-heading">
            <h5>
                <i class="bi bi-clock-history me-2"></i>
                Riwayat Transaksi
            </h5>

            <span class="badge text-bg-primary">15 Terbaru</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Nama Siswa</th>
                        <th>Jenis Transaksi</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$riwayat): ?>
                    <tr>
                        <td colspan="6"
                            class="text-center text-secondary p-4">
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayat as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>

                        <td>
                            <?php
                            $waktu = strtotime($r['tanggal']);
                            echo $waktu ? date('d-m-Y H:i', $waktu) : '-';
                            ?>
                        </td>

                        <td>
                            <?= e($r['nama'] ?? 'Tidak diketahui') ?>
                        </td>

                        <td>
                            <?php if ($r['arah'] === 'masuk'): ?>
                                <span class="status status-masuk">
                                    Setor Sampah
                                </span>
                            <?php else: ?>
                                <span class="status status-keluar">
                                    Tarik Saldo
                                </span>
                            <?php endif; ?>
                        </td>

                        <td><?= e($r['keterangan']) ?></td>

                        <td class="fw-bold <?= $r['arah'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
                            <?= $r['arah'] === 'masuk' ? '+' : '-' ?>
                            <?= rupiah($r['nominal']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- DATA SISWA -->
    <section class="section-card" id="siswa">
        <div class="section-heading">
            <h5>
                <i class="bi bi-people-fill me-2"></i>
                Data Nasabah / Siswa
            </h5>

            <span class="badge text-bg-danger">10 Data</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Nasabah</th>
                        <th>Nama</th>
                        <th>No. HP</th>
                        <th>Saldo</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$siswa): ?>
                    <tr>
                        <td colspan="5"
                            class="text-center text-secondary p-4">
                            Data siswa belum tersedia.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($siswa as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($s['kode_nasabah']) ?></td>
                        <td><?= e($s['nama']) ?></td>
                        <td><?= e($s['no_hp']) ?></td>
                        <td class="fw-bold text-primary">
                            <?= rupiah($s['saldo']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="footer">
        <i class="bi bi-shield-fill-check text-danger"></i>
        Bank Sampah Spider-Man Edition · Save Our Planet
    </footer>

</main>
</body>
</html>
```