```php
<?php
session_start();
require_once "koneksi.php";

// CEK LOGIN
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

if ($role !== 'pengepul') {
    if ($role === 'admin') {
        header("Location: dashboard.php");
    } elseif ($role === 'siswa') {
        header("Location: dashboard_siswa.php");
    } elseif ($role === 'guru') {
        header("Location: dashboard_guru.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengepul';

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// STATISTIK
$jumlahJenis = 0;
$totalPembelian = 0;
$totalBerat = 0;
$totalNilai = 0;
$totalNasabah = 0;

$qJenis = mysqli_query($koneksi,
    "SELECT COUNT(*) AS jumlah FROM jenis_sampah"
);
if ($qJenis) {
    $jumlahJenis = (int)(mysqli_fetch_assoc($qJenis)['jumlah'] ?? 0);
}

$qNasabah = mysqli_query($koneksi,
    "SELECT COUNT(*) AS jumlah FROM nasabah"
);
if ($qNasabah) {
    $totalNasabah = (int)(mysqli_fetch_assoc($qNasabah)['jumlah'] ?? 0);
}

$qTransaksi = mysqli_query($koneksi, "
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(berat), 0) AS berat,
           COALESCE(SUM(total), 0) AS nilai
    FROM transaksi
");

if ($qTransaksi) {
    $stat = mysqli_fetch_assoc($qTransaksi);
    $totalPembelian = (int)$stat['jumlah'];
    $totalBerat = (float)$stat['berat'];
    $totalNilai = (float)$stat['nilai'];
}

// TRANSAKSI TERBARU
$qTerbaru = mysqli_query($koneksi, "
    SELECT t.id_transaksi, t.tanggal, t.berat, t.total,
           n.nama AS nama_nasabah,
           s.nama_sampah
    FROM transaksi t
    LEFT JOIN nasabah n ON t.id_nasabah = n.id_nasabah
    LEFT JOIN jenis_sampah s ON t.id_sampah = s.id_sampah
    ORDER BY t.id_transaksi DESC
    LIMIT 5
");

if (!$qTerbaru) {
    die("Gagal mengambil transaksi: " .
        htmlspecialchars(mysqli_error($koneksi)));
}

$aktivitas = [];
while ($row = mysqli_fetch_assoc($qTerbaru)) {
    $aktivitas[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard Pengepul - Bank Sampah</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      rel="stylesheet">

<style>
:root {
    --red: #c1121f;
    --red-dark: #780000;
    --blue: #123c9c;
    --blue-light: #1877f2;
    --background: #f0f4ff;
    --text: #263238;
    --muted: #718096;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--background);
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    width: 250px;
    padding: 25px 16px;
    color: white;
    background: linear-gradient(180deg, var(--red), var(--red-dark));
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 1000;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 5px 30px;
}

.brand-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    background: white;
    color: var(--red);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 26px;
}

.brand h4 {
    margin: 0;
    font-weight: 800;
    font-size: 20px;
}

.brand small {
    color: #ffe3e3;
    font-size: 10px;
}

.menu-label {
    color: #ffe3e3;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 0 12px;
    margin-bottom: 12px;
}

.nav-link-custom {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    margin-bottom: 8px;
    border-radius: 11px;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    transition: .2s;
}

.nav-link-custom i {
    font-size: 19px;
}

.nav-link-custom:hover,
.nav-link-custom.active {
    color: var(--blue);
    background: white;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 20px;
}

/* KONTEN */
.main {
    margin-left: 250px;
    min-height: 100vh;
}

.topbar {
    min-height: 76px;
    padding: 15px 28px;
    background: white;
    border-bottom: 1px solid #e1e8f8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.search-box {
    width: min(530px, 60%);
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f0f4ff;
    padding: 12px 16px;
    border-radius: 30px;
}

.search-box input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    color: var(--text);
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: white;
    background: var(--blue);
    font-size: 20px;
}

.content {
    padding: 28px;
}

.page-title {
    color: var(--red);
    font-size: 28px;
    font-weight: 800;
}

.subtitle {
    color: var(--muted);
}

.date-box {
    background: #e4ecff;
    color: var(--blue);
    border-radius: 13px;
    padding: 13px 17px;
}

.welcome {
    position: relative;
    overflow: hidden;
    padding: 26px;
    margin: 24px 0;
    color: white;
    border-radius: 17px;
    background: linear-gradient(110deg, var(--red), var(--blue));
}

.welcome h4 {
    font-weight: 750;
}

.welcome p {
    margin: 0 0 4px;
    color: #fff;
}

.welcome-decoration {
    position: absolute;
    right: 25px;
    bottom: -25px;
    font-size: 110px;
    opacity: .13;
}

/* STATISTIK */
.stat-card {
    height: 100%;
    padding: 21px;
    border: 1px solid #dce5ff;
    border-top: 4px solid var(--blue);
    border-radius: 16px;
    background: white;
    box-shadow: 0 4px 15px rgba(18,60,156,.045);
    transition: .2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(18,60,156,.09);
}

.stat-icon {
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e4ecff;
    color: var(--red);
    font-size: 21px;
    margin-bottom: 13px;
}

.stat-label {
    color: #638096;
    font-size: 14px;
}

.stat-value {
    color: var(--blue);
    font-size: 25px;
    font-weight: 800;
    margin: 8px 0;
    overflow-wrap: anywhere;
}

.stat-note {
    color: #8a9aab;
    font-size: 12px;
}

/* PANEL */
.panel {
    height: 100%;
    padding: 22px;
    background: white;
    border: 1px solid #dce5ff;
    border-radius: 16px;
}

.panel-title {
    font-weight: 800;
    margin-bottom: 4px;
    color: #263b60;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 13px;
    min-height: 105px;
    padding: 17px;
    border: 1px solid #dce5ff;
    border-radius: 13px;
    color: var(--text);
    text-decoration: none;
    transition: .2s;
}

.quick-link:hover {
    background: #f0f4ff;
    border-color: #91b4ff;
    transform: translateY(-2px);
}

.quick-icon {
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    background: var(--red);
    color: white;
    border-radius: 12px;
    font-size: 20px;
}

.quick-link p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 14px 0;
    border-bottom: 1px solid #edf0f8;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    flex-shrink: 0;
    width: 37px;
    height: 37px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e4ecff;
    color: var(--blue);
}

.activity-name {
    font-weight: 600;
}

.activity-detail {
    font-size: 12px;
    color: var(--muted);
}

.table th {
    background: var(--blue);
    color: white;
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f0f4ff;
}

.btn-theme {
    background: var(--red);
    border: 1px solid var(--red);
    color: white;
}

.btn-theme:hover {
    background: var(--blue);
    border-color: var(--blue);
    color: white;
}

.text-success {
    color: var(--red) !important;
}

.footer {
    color: var(--muted);
    font-size: 12px;
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
    }

    .content {
        padding: 20px;
    }
}

@media (max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 18px;
    }

    .brand {
        padding-bottom: 20px;
    }

    .sidebar-bottom {
        margin-top: 8px;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        padding: 14px 18px;
        flex-wrap: wrap;
    }

    .search-box {
        width: 100%;
        order: 2;
    }

    .content {
        padding: 16px;
    }

    .page-title {
        font-size: 23px;
    }

    .date-box {
        width: 100%;
    }

    .welcome {
        padding: 20px;
    }

    .welcome-decoration {
        display: none;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-shield-fill"></i>
        </div>
        <div>
            <h4>Bank Sampah</h4>
            <small>SPIDER-MAN EDITION</small>
        </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="dashboard_pengepul.php" class="nav-link-custom active">
        <i class="bi bi-house-door-fill"></i>
        Dashboard
    </a>

    <a href="jenis_sampah_pengepul.php" class="nav-link-custom">
        <i class="bi bi-recycle"></i>
        Harga &amp; Stok
    </a>

    <a href="transaksi/index.php" class="nav-link-custom">
        <i class="bi bi-cart3"></i>
        Pembelian Saya
    </a>

    <div class="sidebar-bottom">
        <a href="logout.php" class="nav-link-custom"
           onclick="return confirm('Yakin ingin logout?')">
            <i class="bi bi-box-arrow-left"></i>
            Logout
        </a>
    </div>
</aside>

<!-- KONTEN UTAMA -->
<main class="main">

    <header class="topbar">
        <form class="search-box" method="GET" action="transaksi/index.php">
            <i class="bi bi-search"></i>
            <input type="search" name="cari"
                   placeholder="Cari nama nasabah atau transaksi...">
        </form>

        <div class="profile">
            <div class="profile-icon">
                <i class="bi bi-person-fill"></i>
            </div>
            <span><?= htmlspecialchars($nama) ?></span>
            <i class="bi bi-chevron-down"></i>
        </div>
    </header>

    <div class="content">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-shield-fill"></i>
                    Dashboard Pengepul
                </h1>
                <p class="subtitle">
                    Kelola pembelian dan pantau aktivitas Bank Sampah.
                </p>
            </div>

            <div class="date-box">
                <i class="bi bi-calendar3 me-2"></i>
                <strong><?= date('d-m-Y') ?></strong>
                <div class="small"><?= date('H:i') ?> WIB</div>
            </div>
        </div>

        <!-- SAMBUTAN -->
        <section class="welcome">
            <div class="d-flex align-items-center gap-3 position-relative">
                <div class="profile-icon"
                     style="width:65px;height:65px;background:rgba(255,255,255,.2);font-size:30px">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div>
                    <h4>Halo, <?= htmlspecialchars($nama) ?>! 👋</h4>
                    <p>Selamat datang di dashboard pengepul Bank Sampah.</p>
                    <p>Pantau data nasabah, jenis sampah, dan aktivitas setoran.</p>
                </div>
            </div>
            <i class="bi bi-shield-fill welcome-decoration"></i>
        </section>

        <!-- STATISTIK -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-recycle"></i></div>
                    <div class="stat-label">Jenis Sampah</div>
                    <div class="stat-value"><?= number_format($jumlahJenis) ?></div>
                    <div class="stat-note">Jenis sampah terdaftar</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-label">Jumlah Nasabah</div>
                    <div class="stat-value"><?= number_format($totalNasabah) ?></div>
                    <div class="stat-note">Nasabah terdaftar</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-label">Total Berat Setoran</div>
                    <div class="stat-value">
                        <?= number_format($totalBerat, 2, ',', '.') ?> kg
                    </div>
                    <div class="stat-note">Akumulasi berat setoran</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-label">Nilai Total Setoran</div>
                    <div class="stat-value" style="font-size:21px">
                        <?= rupiah($totalNilai) ?>
                    </div>
                    <div class="stat-note">Akumulasi nilai setoran</div>
                </div>
            </div>

        </div>

        <!-- MENU CEPAT DAN AKTIVITAS -->
        <div class="row g-4 mb-4">

            <div class="col-12 col-xl-7">
                <section class="panel">
                    <h5 class="panel-title">
                        <i class="bi bi-lightning-charge-fill text-danger"></i>
                        Menu Cepat
                    </h5>

                    <p class="subtitle small mb-4">
                        Akses fitur utama Bank Sampah.
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <a href="jenis_sampah_pengepul.php" class="quick-link">
                                <div class="quick-icon">
                                    <i class="bi bi-recycle"></i>
                                </div>
                                <div>
                                    <strong>Harga &amp; Stok</strong>
                                    <p>Lihat daftar jenis dan harga sampah.</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-12 col-md-6">
                            <a href="transaksi/index.php" class="quick-link">
                                <div class="quick-icon">
                                    <i class="bi bi-cart3"></i>
                                </div>
                                <div>
                                    <strong>Pembelian Saya</strong>
                                    <p>Lihat daftar transaksi setoran.</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="panel">
                    <h5 class="panel-title">
                        <i class="bi bi-clock-history text-danger"></i>
                        Aktivitas Terbaru
                    </h5>

                    <p class="subtitle small mb-2">
                        Transaksi terbaru yang tersimpan.
                    </p>

                    <?php if (count($aktivitas) > 0): ?>
                        <?php foreach ($aktivitas as $item): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="activity-name">
                                        <?= htmlspecialchars($item['nama_sampah'] ?? 'Jenis sampah') ?>
                                    </div>

                                    <div class="activity-detail">
                                        Nasabah:
                                        <?= htmlspecialchars($item['nama_nasabah'] ?? '-') ?>
                                    </div>

                                    <div class="activity-detail">
                                        <?= date('d-m-Y H:i', strtotime($item['tanggal'])) ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <span class="badge rounded-pill text-bg-primary">
                                        Tercatat
                                    </span>
                                    <div class="small fw-semibold mt-1">
                                        <?= rupiah($item['total']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-secondary">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mb-0">Belum ada aktivitas transaksi.</p>
                        </div>
                    <?php endif; ?>

                    <a href="transaksi/index.php"
                       class="btn btn-outline-primary w-100 mt-3">
                        Lihat Semua Transaksi
                    </a>
                </section>
            </div>

        </div>

        <!-- TABEL TRANSAKSI -->
        <section class="panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="panel-title">Ringkasan Transaksi</h5>
                    <p class="subtitle small mb-0">
                        Lima transaksi terbaru dari database.
                    </p>
                </div>

                <a href="transaksi/index.php" class="btn btn-theme btn-sm">
                    Lihat Semua
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal</th>
                            <th>Nasabah</th>
                            <th>Jenis Sampah</th>
                            <th>Berat</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (count($aktivitas) > 0): ?>
                        <?php foreach ($aktivitas as $i => $data): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?= date('d-m-Y H:i', strtotime($data['tanggal'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($data['nama_nasabah'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($data['nama_sampah'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= number_format((float)$data['berat'], 2, ',', '.') ?> kg
                                </td>
                                <td class="fw-semibold">
                                    <?= rupiah($data['total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                Belum ada transaksi.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="text-center footer py-4">
            © <?= date('Y') ?> Bank Sampah | Spider-Man Edition
        </footer>

    </div>
</main>

</body>
</html>
``` 