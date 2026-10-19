
<?php
session_start();
require_once "koneksi.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= CEK LOGIN =================
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

// ================= CEK ROLE =================
$role = strtolower(trim($_SESSION['role'] ?? ''));

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
    exit;
}

$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengepul';

// ================= FUNGSI =================
function angka($koneksi, $sql, $kolom) {
    $hasil = mysqli_query($koneksi, $sql);
    if (!$hasil) return 0;

    $data = mysqli_fetch_assoc($hasil);
    return $data[$kolom] ?? 0;
}

function rupiah($nilai) {
    return 'Rp ' . number_format((float)$nilai, 0, ',', '.');
}

function e($nilai) {
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

// ================= STATISTIK =================
$totalNasabah = angka($koneksi,
    "SELECT COUNT(*) AS jumlah FROM nasabah", "jumlah");

$totalJenis = angka($koneksi,
    "SELECT COUNT(*) AS jumlah FROM jenis_sampah", "jumlah");

$totalTransaksi = angka($koneksi,
    "SELECT COUNT(*) AS jumlah FROM transaksi", "jumlah");

$totalBerat = angka($koneksi,
    "SELECT COALESCE(SUM(berat),0) AS jumlah FROM transaksi", "jumlah");

$totalSetoran = angka($koneksi,
    "SELECT COALESCE(SUM(total),0) AS jumlah FROM transaksi", "jumlah");

$totalSaldo = angka($koneksi,
    "SELECT COALESCE(SUM(saldo),0) AS jumlah FROM nasabah", "jumlah");

// ================= TRANSAKSI TERBARU =================
$transaksi = mysqli_query($koneksi, "
    SELECT t.id_transaksi, t.berat, t.total, t.tanggal,
           n.nama AS nama_nasabah,
           j.nama_sampah
    FROM transaksi t
    LEFT JOIN nasabah n ON t.id_nasabah = n.id_nasabah
    LEFT JOIN jenis_sampah j ON t.id_sampah = j.id_sampah
    ORDER BY t.id_transaksi DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard Pengepul | Bank Sampah</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --red: #e62429;
    --red-dark: #b9132b;
    --blue: #0878d1;
    --navy: #082b55;
    --bg: #f2f6fc;
    --text: #17375e;
}

* { box-sizing: border-box; }

html {
    min-width: 320px;
    overflow-x: hidden;
}

body {
    margin: 0;
    background:
        radial-gradient(circle at 90% 0%, #dcecff 0, transparent 28%),
        var(--bg);
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
    overflow-x: hidden;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 255px;
    height: 100vh;
    padding: 25px 17px;
    background: linear-gradient(160deg, #0759a8, #082b55);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 8px 0 35px #082b5512;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 7px 25px;
    border-bottom: 1px solid #ffffff35;
}

.brand-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 15px;
    color: white;
    background: linear-gradient(135deg, #ff454b, #b9132b);
    box-shadow: 0 8px 22px #e624293d;
    font-size: 25px;
}

.brand h5 {
    margin: 0;
    color: white;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: .7px;
}

.brand small { color: #d9e9ff; }

.menu-label {
    margin: 27px 10px 11px;
    color: #c7ddf6;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
}

.menu {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    margin: 7px 0;
    border: 1px solid transparent;
    border-radius: 12px;
    color: #fff !important;
    text-decoration: none !important;
    transition: .25s ease;
}

.menu i { font-size: 19px; }

.menu:hover,
.menu.active {
    background: white;
    color: #07529a !important;
    box-shadow: 0 7px 20px #00000015;
    transform: translateX(3px);
}

.menu.logout {
    margin-top: 28px;
    background: linear-gradient(110deg, #ff454b, #c9152b);
}

.menu.logout:hover {
    background: white;
    color: var(--red) !important;
}

/* KONTEN */
.main {
    width: auto;
    min-width: 0;
    min-height: 100vh;
    margin-left: 255px;
    padding: 28px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 25px;
}

.topbar h3 {
    margin: 0 0 6px;
    color: var(--navy);
    font-size: 25px;
    font-weight: 850;
    letter-spacing: -.5px;
}

.subtitle {
    color: #7a8ba5;
    font-size: 13px;
}

.profile {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 15px;
    border: 1px solid #e1eaf5;
    border-radius: 15px;
    background: white;
    box-shadow: 0 8px 25px #0d39690a;
    flex-shrink: 0;
}

.avatar {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    border-radius: 13px;
    color: white;
    background: linear-gradient(135deg, #f43a42, #b80f2c);
    font-size: 22px;
}

/* BANNER */
.hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: 34px;
    margin-bottom: 27px;
    border: 1px solid #ffffff40;
    border-radius: 24px;
    color: white;
    background: linear-gradient(
        115deg,
        #b9132b 0%,
        #e62429 30%,
        #0759a8 72%,
        #082b55 100%
    );
    box-shadow: 0 18px 40px #082b5520;
    animation: fadeUp .55s ease both;
}

.hero::before {
    content: "";
    position: absolute;
    z-index: -1;
    width: 270px;
    height: 270px;
    top: -150px;
    right: 5%;
    border: 2px solid #ffffff20;
    border-radius: 50%;
    box-shadow:
        0 0 0 20px #ffffff08,
        0 0 0 42px #ffffff07,
        0 0 0 64px #ffffff05;
}

.hero::after {
    content: "♻";
    position: absolute;
    z-index: -1;
    right: 30px;
    bottom: -44px;
    color: white;
    opacity: .13;
    font-size: 155px;
    transform: rotate(-15deg);
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-tag {
    display: inline-block;
    padding: 7px 12px;
    margin-bottom: 15px;
    border: 1px solid #ffffff40;
    border-radius: 30px;
    color: white;
    background: #ffffff20;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hero h1 {
    margin-bottom: 12px;
    color: white;
    font-size: clamp(25px, 3vw, 35px);
    font-weight: 900;
    letter-spacing: -.7px;
}

.hero p {
    max-width: 570px;
    margin-bottom: 20px;
    color: #ffffffed;
    font-size: 14px;
    line-height: 1.9;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 12px 18px;
    border: 1px solid #ffffffaa;
    border-radius: 11px;
    color: #07529a !important;
    background: white;
    box-shadow: 0 7px 18px #00000015;
    font-weight: 800;
    text-decoration: none !important;
    transition: .25s;
}

.hero-btn:hover {
    color: var(--red) !important;
    background: #edf5ff;
    transform: translateY(-3px);
}

/* JUDUL BAGIAN */
.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 28px 0 17px;
}

.section-heading h5 {
    margin: 0;
    color: var(--navy);
    font-weight: 850;
}

/* KARTU STATISTIK */
.stat-card {
    position: relative;
    height: 100%;
    min-width: 0;
    overflow: hidden;
    padding: 21px;
    border: 1px solid #e1eaf5;
    border-radius: 19px;
    background: linear-gradient(145deg, #fff, #fbfdff);
    box-shadow: 0 7px 24px #082b5509;
    transition: transform .25s, box-shadow .25s;
    animation: fadeUp .55s ease both;
}

.stat-card::after {
    content: "";
    position: absolute;
    right: -38px;
    bottom: -43px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0878d10c, #e6242910);
    pointer-events: none;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: #bfdcf6;
    box-shadow: 0 15px 35px #082b5515;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.stat-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    border-radius: 15px;
    color: white;
    font-size: 22px;
    box-shadow: 0 7px 15px #082b5518;
}

.stat-mini {
    color: #b2c0d2;
    font-size: 21px;
}

.stat-card small {
    color: #71829a;
    font-size: 12px;
    font-weight: 650;
}

.stat-card h3 {
    margin: 7px 0 0;
    color: var(--navy);
    font-size: clamp(19px, 2vw, 26px);
    font-weight: 900;
    overflow-wrap: anywhere;
}

.stat-icon.red {
    background: linear-gradient(135deg, #ff454b, #c8102e);
}

.stat-icon.blue,
.stat-icon.green {
    background: linear-gradient(135deg, #168fe8, #0755a4);
}

.stat-icon.navy {
    background: linear-gradient(135deg, #164d91, #071f43);
}

.stat-icon.orange {
    background: linear-gradient(135deg, #e62429, #a80e2c);
}

.stat-icon.purple {
    background: linear-gradient(135deg, #0878d1, #073b75);
}

/* PANEL DAN TABEL */
.panel {
    height: 100%;
    min-width: 0;
    padding: 23px;
    border: 1px solid #e1eaf5;
    border-radius: 20px;
    background: rgba(255,255,255,.97);
    box-shadow: 0 8px 28px #082b5509;
}

.panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 20px;
}

.panel-title h5 {
    margin: 0;
    color: var(--navy);
    font-size: 16px;
    font-weight: 850;
}

.view-all {
    color: var(--blue) !important;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none !important;
}

.view-all:hover { color: var(--red) !important; }

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.table { margin-bottom: 0; }

.table thead th {
    padding: 13px 12px;
    border: none;
    background: #edf5ff;
    color: #31577f;
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
}

.table tbody td {
    padding: 14px 12px;
    border-color: #edf2f8;
    color: #344761;
    font-size: 13px;
    vertical-align: middle;
}

.table tbody tr:last-child td { border-bottom: none; }

.table tbody tr:hover td { background: #f5f9ff; }

.name-cell {
    color: #173a65 !important;
    font-weight: 750;
}

.badge-kg {
    display: inline-block;
    padding: 5px 8px;
    border: 1px solid #cfe6fb;
    border-radius: 7px;
    color: #0867b6;
    background: #e9f4ff;
    font-weight: 800;
    white-space: nowrap;
}

/* AKSES CEPAT */
.quick-link {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 14px;
    margin-bottom: 11px;
    border: 1px solid #e3ebf6;
    border-radius: 14px;
    color: var(--text) !important;
    background: white;
    text-decoration: none !important;
    transition: .25s;
}

.quick-link:last-child { margin-bottom: 0; }

.quick-link:hover {
    border-color: #a7cef2;
    background: #f4f9ff;
    box-shadow: 0 7px 18px #082b550a;
    transform: translateX(3px);
}

.quick-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    color: white;
    background: linear-gradient(135deg, #168fe8, #0755a4);
    font-size: 19px;
}

.quick-link strong {
    display: block;
    color: #17375e;
    font-size: 13px;
}

.quick-link small {
    display: block;
    margin-top: 4px;
    color: #8190a4;
    font-size: 11px;
}

.quick-arrow {
    margin-left: auto;
    color: #0878d1;
}

.btn-primary {
    border: none !important;
    border-radius: 10px;
    color: white !important;
    background: linear-gradient(110deg, #0878d1, #07529a) !important;
    box-shadow: 0 5px 12px #0878d126;
}

.btn-primary:hover {
    background: linear-gradient(110deg, #e62429, #b9132b) !important;
    transform: translateY(-1px);
}

.text-primary { color: #0878d1 !important; }

/* FOOTER */
.footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e9f4;
    color: #7e91aa;
    font-size: 12px;
    line-height: 1.8;
    text-align: center;
}

/* ANIMASI */
@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .sidebar {
        width: 76px;
        padding: 20px 9px;
    }

    .brand {
        justify-content: center;
        padding-right: 0;
        padding-left: 0;
    }

    .brand-text,
    .menu-label,
    .menu span {
        display: none;
    }

    .menu {
        justify-content: center;
        padding: 13px 5px;
    }

    .menu i { font-size: 22px; }

    .main {
        margin-left: 76px;
        padding: 18px;
    }
}

@media (max-width: 575px) {
    .main { padding: 12px; }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile { width: 100%; }

    .hero { padding: 23px 20px; }

    .hero::after {
        right: -20px;
        font-size: 110px;
    }

    .stat-card {
        padding: 15px;
        border-radius: 15px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 19px;
    }

    .stat-card h3 { font-size: 19px; }

    .panel { padding: 14px; }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-recycle"></i>
        </div>

        <div class="brand-text">
            <h5>BANK SAMPAH</h5>
            <small>Portal Pengepul</small>
        </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>

    <a href="portal.php" class="menu active">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
    </a>

    <a href="sampah/index.php" class="menu">
        <i class="bi bi-recycle"></i>
        <span>Jenis Sampah</span>
    </a>

    <a href="transaksi/index.php" class="menu">
        <i class="bi bi-arrow-left-right"></i>
        <span>Transaksi Setoran</span>
    </a>

    <!-- MENU DATA NASABAH SUDAH DIHAPUS -->

    <div class="menu-label">AKUN SAYA</div>

    <a href="logout.php" class="menu logout"
       onclick="return confirm('Yakin ingin keluar dari akun?')">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
</aside>

<!-- KONTEN UTAMA -->
<main class="main">

    <header class="topbar">
        <div>
            <h3>Dashboard Pengepul</h3>
            <div class="subtitle">
                Ringkasan aktivitas dan pengelolaan Bank Sampah
            </div>
        </div>

        <div class="profile">
            <div class="avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <strong><?= e($nama) ?></strong><br>
                <span class="subtitle">Pengepul</span>
            </div>
        </div>
    </header>

    <!-- BANNER -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-tag">
                <i class="bi bi-shield-check"></i>
                BANK SAMPAH DIGITAL
            </div>

            <h1>Halo, <?= e($nama) ?>! ♻️</h1>

            <p>
                Selamat datang di Portal Pengepul. Lihat harga jenis sampah
                dan pantau transaksi setoran dalam satu dashboard.
            </p>

            <a href="transaksi/index.php" class="hero-btn">
                <i class="bi bi-arrow-left-right"></i>
                Buka Transaksi
            </a>
        </div>
    </section>

    <!-- STATISTIK -->
    <div class="section-heading">
        <h5>
            <i class="bi bi-bar-chart-fill text-primary"></i>
            Ringkasan Data
        </h5>
    </div>

    <div class="row g-3">

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon red">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <i class="bi bi-graph-up-arrow stat-mini"></i>
                </div>
                <small>Total Nasabah</small>
                <h3><?= number_format((int)$totalNasabah, 0, ',', '.') ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon blue">
                        <i class="bi bi-recycle"></i>
                    </div>
                    <i class="bi bi-box-seam stat-mini"></i>
                </div>
                <small>Jenis Sampah</small>
                <h3><?= number_format((int)$totalJenis, 0, ',', '.') ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon navy">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <i class="bi bi-activity stat-mini"></i>
                </div>
                <small>Total Transaksi</small>
                <h3><?= number_format((int)$totalTransaksi, 0, ',', '.') ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon green">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <i class="bi bi-recycle stat-mini"></i>
                </div>
                <small>Total Berat Setoran</small>
                <h3><?= number_format((float)$totalBerat, 2, ',', '.') ?> kg</h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon orange">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <i class="bi bi-wallet stat-mini"></i>
                </div>
                <small>Nilai Seluruh Setoran</small>
                <h3><?= rupiah($totalSetoran) ?></h3>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon purple">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <i class="bi bi-piggy-bank stat-mini"></i>
                </div>
                <small>Total Saldo Nasabah</small>
                <h3><?= rupiah($totalSaldo) ?></h3>
            </div>
        </div>

    </div>

    <!-- TRANSAKSI TERBARU -->
    <div class="row g-3 mt-2">

        <div class="col-12 col-xl-8">
            <section class="panel">
                <div class="panel-title">
                    <h5>
                        <i class="bi bi-clock-history text-primary"></i>
                        Transaksi Terbaru
                    </h5>

                    <a href="transaksi/index.php" class="view-all">
                        Lihat semua <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th>Jenis Sampah</th>
                                <th>Berat</th>
                                <th>Total</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($transaksi && mysqli_num_rows($transaksi) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($transaksi)): ?>
                                <tr>
                                    <td class="name-cell">
                                        <?= e($row['nama_nasabah'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= e($row['nama_sampah'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <span class="badge-kg">
                                            <?= number_format((float)$row['berat'], 2, ',', '.') ?> kg
                                        </span>
                                    </td>

                                    <td>
                                        <strong><?= rupiah($row['total']) ?></strong>
                                    </td>

                                    <td><?= e($row['tanggal']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Belum ada transaksi setoran.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- AKSES CEPAT TANPA DATA NASABAH -->
        <div class="col-12 col-xl-4">
            <section class="panel">
                <div class="panel-title">
                    <h5>
                        <i class="bi bi-lightning-charge-fill text-warning"></i>
                        Akses Cepat
                    </h5>
                </div>

                <a href="sampah/index.php" class="quick-link">
                    <div class="quick-icon">
                        <i class="bi bi-recycle"></i>
                    </div>
                    <div>
                        <strong>Jenis Sampah</strong>
                        <small>Lihat jenis dan harga sampah</small>
                    </div>
                    <i class="bi bi-chevron-right quick-arrow"></i>
                </a>

                <a href="transaksi/index.php" class="quick-link">
                    <div class="quick-icon"
                         style="background:linear-gradient(135deg,#ff454b,#c8102e)">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <div>
                        <strong>Transaksi Setoran</strong>
                        <small>Pantau riwayat setoran</small>
                    </div>
                    <i class="bi bi-chevron-right quick-arrow"></i>
                </a>
            </section>
        </div>

    </div>

    <footer class="footer">
        &copy; <?= date('Y') ?> Bank Sampah Digital
        <br>Bersama menjaga lingkungan agar tetap bersih dan sehat.
    </footer>

</main>

</body>
</html>
