<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "koneksi.php";

// ================= CEK LOGIN DAN ROLE =================
if (
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true
) {
    header("Location: login.php");
    exit();
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'admin') {
    if ($role === 'siswa') {
        header("Location: dashboard_siswa.php");
    } elseif ($role === 'guru') {
        header("Location: dashboard_guru.php");
    } elseif ($role === 'pengepul') {
        header("Location: dashboard_pengepul.php");
    } else {
        header("Location: login.php");
    }

    exit();
}

$nama_admin = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin';

// ================= FUNGSI =================
function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// ================= STATISTIK NASABAH =================
$qNasabah = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM nasabah"
);

if (!$qNasabah) {
    die("Query nasabah gagal: " . mysqli_error($koneksi));
}

$totalNasabah = mysqli_fetch_assoc($qNasabah)['total'];

// ================= STATISTIK JENIS SAMPAH =================
$qSampah = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM jenis_sampah"
);

if (!$qSampah) {
    die("Query jenis sampah gagal: " . mysqli_error($koneksi));
}

$totalJenisSampah = mysqli_fetch_assoc($qSampah)['total'];

// ================= STATISTIK SETORAN =================
$qSetoran = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS jumlah,
            COALESCE(SUM(berat), 0) AS berat,
            COALESCE(SUM(total), 0) AS total
     FROM transaksi"
);

if (!$qSetoran) {
    die("Query setoran gagal: " . mysqli_error($koneksi));
}

$dataSetoran = mysqli_fetch_assoc($qSetoran);
$totalSetoran = $dataSetoran['jumlah'];
$totalBerat = $dataSetoran['berat'];
$totalRp = $dataSetoran['total'];

// ================= STATISTIK PENARIKAN =================
$qPenarikan = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS jumlah,
            COALESCE(SUM(jumlah), 0) AS total
     FROM penarikan"
);

if (!$qPenarikan) {
    die("Query penarikan gagal: " . mysqli_error($koneksi));
}

$dataPenarikan = mysqli_fetch_assoc($qPenarikan);
$totalPenarikan = $dataPenarikan['jumlah'];
$totalRpPenarikan = $dataPenarikan['total'];

// ================= TOTAL SALDO =================
$qSaldo = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(saldo), 0) AS total FROM nasabah"
);

if (!$qSaldo) {
    die("Query saldo gagal: " . mysqli_error($koneksi));
}

$totalSaldo = mysqli_fetch_assoc($qSaldo)['total'];

// ================= GRAFIK TAHUN INI =================
$bulan = [];
$dataGrafikSetoran = [];
$dataGrafikPenarikan = [];

for ($i = 1; $i <= 12; $i++) {
    $bulan[] = date('M', mktime(0, 0, 0, $i, 1));

    $q = mysqli_query(
        $koneksi,
        "SELECT COALESCE(SUM(total), 0) AS total
         FROM transaksi
         WHERE MONTH(tanggal) = $i
         AND YEAR(tanggal) = YEAR(CURDATE())"
    );

    $dataGrafikSetoran[] = $q
        ? (float) mysqli_fetch_assoc($q)['total']
        : 0;

    $q = mysqli_query(
        $koneksi,
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM penarikan
         WHERE MONTH(tanggal) = $i
         AND YEAR(tanggal) = YEAR(CURDATE())"
    );

    $dataGrafikPenarikan[] = $q
        ? (float) mysqli_fetch_assoc($q)['total']
        : 0;
}

// ================= JUMLAH PENGAJUAN PERSETUJUAN =================
// Menggunakan kolom status pada tabel transaksi dan penarikan.
// Jika tabel belum mempunyai kolom status, bagian ini perlu
// disesuaikan dengan struktur database yang digunakan.

$pendingSetoran = 0;
$pendingPenarikan = 0;

$cekStatusTransaksi = mysqli_query(
    $koneksi,
    "SHOW COLUMNS FROM transaksi LIKE 'status'"
);

if ($cekStatusTransaksi && mysqli_num_rows($cekStatusTransaksi) > 0) {
    $q = mysqli_query(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM transaksi
         WHERE LOWER(status) IN ('menunggu', 'pending', 'diajukan')"
    );

    if ($q) {
        $pendingSetoran = (int) mysqli_fetch_assoc($q)['total'];
    }
}

$cekStatusPenarikan = mysqli_query(
    $koneksi,
    "SHOW COLUMNS FROM penarikan LIKE 'status'"
);

if ($cekStatusPenarikan && mysqli_num_rows($cekStatusPenarikan) > 0) {
    $q = mysqli_query(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM penarikan
         WHERE LOWER(status) IN ('menunggu', 'pending', 'diajukan')"
    );

    if ($q) {
        $pendingPenarikan = (int) mysqli_fetch_assoc($q)['total'];
    }
}

$totalPersetujuan = $pendingSetoran + $pendingPenarikan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Admin - Bank Sampah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>
    :root {
        --merah: #d71932;
        --merah-gelap: #9e1024;
        --biru: #123d79;
        --biru-gelap: #092650;
        --putih: #ffffff;
        --latar: #f2f5fb;
        --teks: #243247;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: var(--latar);
        color: var(--teks);
    }

    /* SIDEBAR SPIDER-MAN */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 250px;
        height: 100vh;
        overflow-y: auto;
        padding: 24px 16px;
        color: white;
        background:
            repeating-linear-gradient(
                45deg,
                transparent 0 38px,
                rgba(255,255,255,.035) 39px 40px
            ),
            linear-gradient(
                160deg,
                var(--merah),
                var(--merah-gelap) 48%,
                var(--biru-gelap) 49%,
                var(--biru)
            );
        box-shadow: 4px 0 18px rgba(16, 59, 102, .12);
        z-index: 1000;
    }

    .brand {
        text-align: center;
        padding: 5px 0 25px;
        margin-bottom: 22px;
        border-bottom: 1px solid rgba(255,255,255,.3);
    }

    .brand-icon {
        width: 65px;
        height: 65px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 3px solid white;
        border-radius: 50%;
        background: var(--biru);
        font-size: 31px;
        margin-bottom: 10px;
        box-shadow: 0 0 0 5px rgba(255,255,255,.12);
    }

    .brand h5 {
        margin: 0;
        font-weight: 800;
        letter-spacing: 1px;
    }

    .brand small {
        opacity: .9;
    }

    .menu-label {
        margin: 20px 12px 10px;
        font-size: 10px;
        letter-spacing: 1.5px;
        color: rgba(255,255,255,.75);
        font-weight: bold;
    }

    .menu-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 14px;
        margin-bottom: 7px;
        border-radius: 10px;
        color: white;
        text-decoration: none;
        transition: .2s ease;
    }

    .menu-link i {
        font-size: 19px;
        width: 23px;
        text-align: center;
    }

    .menu-link:hover,
    .menu-link.active {
        background: white;
        color: var(--biru-gelap);
        transform: translateX(3px);
    }

    .menu-link.logout {
        margin-top: 25px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
    }

    .menu-link.logout:hover {
        background: white;
        color: var(--merah);
    }

    /* KONTEN */
    .main {
        margin-left: 250px;
        padding: 28px;
        min-height: 100vh;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        padding: 20px 24px;
        background: white;
        border: 1px solid #e7ecf4;
        border-radius: 16px;
        box-shadow: 0 5px 20px rgba(24,59,92,.06);
        margin-bottom: 24px;
    }

    .topbar h2 {
        margin: 0;
        font-size: 25px;
        font-weight: 800;
        color: var(--biru-gelap);
    }

    .topbar h2 i {
        color: var(--merah);
    }

    .welcome {
        margin-top: 6px;
        color: #718096;
        font-size: 14px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .avatar {
        width: 47px;
        height: 47px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--biru);
        color: white;
        font-size: 23px;
        border: 3px solid #e7efff;
    }

    .role-label {
        color: #718096;
        font-size: 12px;
        text-align: right;
    }

    /* BANNER */
    .hero {
        position: relative;
        overflow: hidden;
        padding: 30px;
        margin-bottom: 25px;
        color: white;
        border-radius: 18px;
        background:
            repeating-linear-gradient(
                45deg,
                transparent 0 35px,
                rgba(255,255,255,.045) 36px 38px
            ),
            linear-gradient(
                115deg,
                var(--merah) 0%,
                #b71535 43%,
                var(--biru) 100%
            );
        box-shadow: 0 10px 28px rgba(18,61,121,.14);
    }

    .hero h3 {
        font-weight: 900;
        margin-bottom: 10px;
    }

    .hero p {
        margin: 0;
        max-width: 750px;
        line-height: 1.7;
        color: #fff0f2;
    }

    /* KARTU STATISTIK */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 25px;
    }

    .stat-card {
        min-width: 0;
        padding: 21px;
        background: white;
        border-radius: 16px;
        border: 1px solid #e7ecf4;
        box-shadow: 0 5px 20px rgba(24,59,92,.045);
        transition: transform .2s, box-shadow .2s;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(24,59,92,.09);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        font-size: 23px;
        margin-bottom: 15px;
    }

    .icon-red {
        color: var(--merah);
        background: #ffebee;
    }

    .icon-blue {
        color: var(--biru);
        background: #e6efff;
    }

    .icon-green {
        color: #16834b;
        background: #e5f8ed;
    }

    .icon-orange {
        color: #b96a00;
        background: #fff2dc;
    }

    .stat-title {
        color: #718096;
        font-size: 13px;
        margin-bottom: 7px;
    }

    .stat-value {
        color: var(--biru-gelap);
        font-size: clamp(18px, 1.5vw, 24px);
        font-weight: 900;
        overflow-wrap: anywhere;
    }

    /* MENU CEPAT */
    .section-card {
        background: white;
        border: 1px solid #e7ecf4;
        border-radius: 17px;
        padding: 23px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(24,59,92,.045);
    }

    .section-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--biru-gelap);
        margin-bottom: 5px;
    }

    .section-subtitle {
        color: #718096;
        font-size: 13px;
        margin-bottom: 20px;
    }

    .quick-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .quick-card {
        display: block;
        padding: 20px;
        color: var(--teks);
        text-decoration: none;
        border: 1px solid #e6edf5;
        border-radius: 14px;
        transition: .2s;
    }

    .quick-card:hover {
        transform: translateY(-3px);
        border-color: var(--biru);
        background: #f7faff;
        color: var(--biru);
    }

    .quick-icon {
        width: 47px;
        height: 47px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e6f1ff;
        color: var(--biru);
        border-radius: 12px;
        font-size: 23px;
        margin-bottom: 13px;
    }

    .quick-card:nth-child(3) .quick-icon {
        background: #ffebee;
        color: var(--merah);
    }

    .quick-title {
        font-weight: 800;
        font-size: 15px;
    }

    .quick-text {
        color: #718096;
        font-size: 12px;
        margin-top: 5px;
        line-height: 1.6;
    }

    /* GRAFIK */
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .chart-card {
        min-width: 0;
        padding: 22px;
        background: white;
        border: 1px solid #e7ecf4;
        border-radius: 17px;
        box-shadow: 0 5px 20px rgba(24,59,92,.045);
    }

    .chart-title {
        color: var(--biru-gelap);
        font-size: 17px;
        font-weight: 800;
        margin-bottom: 20px;
    }

    .chart-title i {
        color: var(--merah);
    }

    .chart-container {
        height: 290px;
        position: relative;
    }

    .footer {
        padding: 25px 0 5px;
        text-align: center;
        color: #8290a3;
        font-size: 12px;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .quick-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 75px;
            padding: 20px 9px;
        }

        .brand h5,
        .brand small,
        .menu-text,
        .menu-label {
            display: none;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            font-size: 23px;
        }

        .menu-link {
            justify-content: center;
            padding: 14px 5px;
        }

        .menu-link i {
            font-size: 21px;
        }

        .main {
            margin-left: 75px;
            padding: 14px;
        }

        .topbar {
            align-items: flex-start;
            padding: 16px;
        }

        .topbar h2 {
            font-size: 20px;
        }

        .avatar {
            width: 40px;
            height: 40px;
        }

        .hero {
            padding: 22px;
        }

        .stat-grid {
            gap: 12px;
        }

        .stat-card {
            padding: 15px;
        }

        .quick-grid {
            grid-template-columns: 1fr;
        }

        .section-card,
        .chart-card {
            padding: 17px;
        }

        .chart-container {
            height: 250px;
        }
    }

    @media (max-width: 420px) {
        .stat-grid {
            grid-template-columns: 1fr;
        }

        .user-name {
            display: none;
        }
    }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-recycle"></i>
        </div>
        <h5>BANK SAMPAH</h5>
        <small>Panel Administrator</small>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="dashboard.php" class="menu-link active">
        <i class="bi bi-speedometer2"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="nasabah/index.php" class="menu-link">
        <i class="bi bi-people-fill"></i>
        <span class="menu-text">Nasabah</span>
    </a>

    <a href="sampah/index.php" class="menu-link">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Jenis Sampah</span>
    </a>

    <!-- Menu setoran dan penarikan dihapus dari sidebar -->

    <a href="persetujuan.php" class="menu-link">
        <i class="bi bi-clipboard-check"></i>
        <span class="menu-text">Persetujuan</span>

        <?php if ($totalPersetujuan > 0): ?>
            <span class="badge bg-danger ms-auto">
                <?= $totalPersetujuan ?>
            </span>
        <?php endif; ?>
    </a>

    <a href="laporan/index.php" class="menu-link">
        <i class="bi bi-bar-chart-fill"></i>
        <span class="menu-text">Laporan</span>
    </a>

    <div class="menu-label">AKUN</div>

    <a href="logout.php" class="menu-link logout"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i>
        <span class="menu-text">Logout</span>
    </a>
</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
            <div class="welcome">Ringkasan data Bank Sampah</div>
        </div>

        <div class="user-info">
            <div class="user-name">
                <strong>
                    <?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8') ?>
                </strong>
                <div class="role-label">Administrator</div>
            </div>
            <div class="avatar">
                <i class="bi bi-person-fill"></i>
            </div>
        </div>
    </header>

    <section class="hero">
        <h3>
            Selamat datang,
            <?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8') ?>!
        </h3>
        <p>
            Kelola data nasabah, jenis sampah, dan persetujuan transaksi
            melalui dashboard administrator.
        </p>
    </section>

    <section class="stat-grid">

        <div class="stat-card">
            <div class="stat-icon icon-blue">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-title">Total Nasabah</div>
            <div class="stat-value">
                <?= number_format((float)$totalNasabah, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-green">
                <i class="bi bi-recycle"></i>
            </div>
            <div class="stat-title">Jenis Sampah</div>
            <div class="stat-value">
                <?= number_format((float)$totalJenisSampah, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-orange">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="stat-title">Jumlah Setoran</div>
            <div class="stat-value">
                <?= number_format((float)$totalSetoran, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-red">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-title">Nilai Setoran</div>
            <div class="stat-value"><?= rupiah($totalRp) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-blue">
                <i class="bi bi-speedometer"></i>
            </div>
            <div class="stat-title">Total Berat Sampah</div>
            <div class="stat-value">
                <?= number_format((float)$totalBerat, 2, ',', '.') ?> kg
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-orange">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-title">Total Penarikan</div>
            <div class="stat-value"><?= rupiah($totalRpPenarikan) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-red">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-title">Jumlah Penarikan</div>
            <div class="stat-value">
                <?= number_format((float)$totalPenarikan, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-green">
                <i class="bi bi-piggy-bank-fill"></i>
            </div>
            <div class="stat-title">Saldo Nasabah</div>
            <div class="stat-value"><?= rupiah($totalSaldo) ?></div>
        </div>

    </section>

    <section class="section-card">
        <div class="section-title">Menu Cepat</div>
        <div class="section-subtitle">
            Akses pengelolaan data dan persetujuan.
        </div>

        <div class="quick-grid">

            <a href="nasabah/tambah.php" class="quick-card">
                <div class="quick-icon">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div class="quick-title">Tambah Nasabah</div>
                <div class="quick-text">
                    Daftarkan nasabah baru ke sistem.
                </div>
            </a>

            <a href="sampah/tambah.php" class="quick-card">
                <div class="quick-icon">
                    <i class="bi bi-recycle"></i>
                </div>
                <div class="quick-title">Tambah Jenis Sampah</div>
                <div class="quick-text">
                    Tambahkan jenis sampah beserta harga per kilogram.
                </div>
            </a>

            <a href="persetujuan.php" class="quick-card">
                <div class="quick-icon">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div class="quick-title">Persetujuan Transaksi</div>
                <div class="quick-text">
                    Periksa pengajuan setoran sampah dan penarikan saldo.
                </div>
            </a>

        </div>
    </section>

    <section class="chart-grid">

        <div class="chart-card">
            <div class="chart-title">
                <i class="bi bi-bar-chart-fill me-2"></i>
                Grafik Setoran Tahun Ini
            </div>
            <div class="chart-container">
                <canvas id="grafikSetoran"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-title">
                <i class="bi bi-graph-up me-2"></i>
                Grafik Penarikan Tahun Ini
            </div>
            <div class="chart-container">
                <canvas id="grafikPenarikan"></canvas>
            </div>
        </div>

    </section>

    <footer class="footer">
        &copy; <?= date('Y') ?> Bank Sampah | Dashboard Administrator
    </footer>

</main>

<script>
const bulan = <?= json_encode($bulan) ?>;
const dataSetoran = <?= json_encode($dataGrafikSetoran) ?>;
const dataPenarikan = <?= json_encode($dataGrafikPenarikan) ?>;

const formatRupiah = (nilai) =>
    'Rp ' + new Intl.NumberFormat('id-ID').format(nilai);

new Chart(document.getElementById('grafikSetoran'), {
    type: 'bar',
    data: {
        labels: bulan,
        datasets: [{
            label: 'Total Setoran',
            data: dataSetoran,
            backgroundColor: '#145da0',
            hoverBackgroundColor: '#e32636',
            borderRadius: 6,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => formatRupiah(context.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => formatRupiah(value)
                },
                grid: { color: '#edf1f6' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

new Chart(document.getElementById('grafikPenarikan'), {
    type: 'line',
    data: {
        labels: bulan,
        datasets: [{
            label: 'Total Penarikan',
            data: dataPenarikan,
            borderColor: '#e32636',
            backgroundColor: 'rgba(227,38,54,.10)',
            pointBackgroundColor: '#145da0',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 3,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => formatRupiah(context.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => formatRupiah(value)
                },
                grid: { color: '#edf1f6' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>

</body>
</html>
