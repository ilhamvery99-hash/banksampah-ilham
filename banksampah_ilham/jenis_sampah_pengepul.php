<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

if ($role !== 'pengepul') {
    header("Location: dashboard.php");
    exit();
}

$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengepul';

$query = mysqli_query($koneksi, "
    SELECT id_sampah, nama_sampah, harga_per_kg
    FROM jenis_sampah
    ORDER BY nama_sampah ASC
");

if (!$query) {
    die("Gagal mengambil data: " . mysqli_error($koneksi));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Jenis Sampah - Pengepul</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f0f4ff;
    color: #263238;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    padding: 25px 15px;
    background: linear-gradient(180deg, #c1121f, #780000);
    box-shadow: 4px 0 15px rgba(0,0,0,.12);
}

.logo {
    color: white;
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 8px;
}

.logo-icon {
    display: block;
    font-size: 40px;
    margin-bottom: 8px;
}

.subtitle {
    color: #ffe3e3;
    text-align: center;
    font-size: 12px;
    margin-bottom: 28px;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    text-decoration: none;
    padding: 13px 15px;
    margin-bottom: 8px;
    border-radius: 10px;
    transition: .2s;
}

.sidebar a:hover {
    background: rgba(255,255,255,.16);
    transform: translateX(4px);
}

.sidebar a.active {
    background: white;
    color: #c1121f;
    font-weight: bold;
}

.sidebar .logout {
    margin-top: 28px;
    background: rgba(255,255,255,.12);
}

.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

.header {
    background: white;
    padding: 24px;
    border-radius: 16px;
    border-left: 5px solid #c1121f;
    box-shadow: 0 5px 20px rgba(18,60,156,.07);
    margin-bottom: 24px;
}

.header h2 {
    color: #c1121f;
    font-weight: bold;
    margin-bottom: 8px;
}

.info-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 24px;
    box-shadow: 0 5px 20px rgba(18,60,156,.06);
    border-top: 4px solid #1877f2;
}

.info-card .number {
    font-size: 28px;
    font-weight: bold;
    color: #123c9c;
}

.table-card {
    background: white;
    padding: 22px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(18,60,156,.06);
}

.table-card h4 {
    color: #c1121f;
    font-weight: bold;
    margin-bottom: 20px;
}

.table thead th {
    background: #123c9c;
    color: white;
    padding: 14px;
    white-space: nowrap;
}

.table tbody td {
    padding: 13px;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f0f4ff;
}

.price {
    color: #123c9c;
    font-weight: bold;
}

@media (max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .content {
        margin-left: 0;
        padding: 15px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">
        <span class="logo-icon">🕷️</span>
        BANK SAMPAH
    </div>

    <div class="subtitle">SPIDER-MAN EDITION</div>

    <a href="portal.php">
        <i class="bi bi-house-door-fill"></i>
        Dashboard
    </a>

    <a href="jenis_sampah_pengepul.php" class="active">
        <i class="bi bi-recycle"></i>
        Jenis Sampah
    </a>

    <a href="logout.php"
       class="logout"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i>
        Logout
    </a>
</aside>

<main class="content">

    <div class="header">
        <h2>
            <i class="bi bi-recycle"></i>
            Daftar Jenis Sampah
        </h2>

        <p class="text-muted mb-0">
            Halo, <?= htmlspecialchars($nama) ?>!
            Lihat daftar sampah beserta harga per kilogram.
        </p>
    </div>

    <div class="info-card">
        <div class="text-muted mb-1">Jenis Sampah Terdaftar</div>
        <div class="number">
            <?= number_format(mysqli_num_rows($query), 0, ',', '.') ?>
            <span class="fs-6">jenis</span>
        </div>
    </div>

    <div class="table-card">
        <h4>
            <i class="bi bi-list-ul"></i>
            Daftar Harga Sampah
        </h4>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Sampah</th>
                        <th>Harga per Kg</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($data = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <i class="bi bi-recycle text-danger me-2"></i>
                                <?= htmlspecialchars($data['nama_sampah']) ?>
                            </td>
                            <td class="price">
                                Rp <?= number_format(
                                    $data['harga_per_kg'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            Belum ada data jenis sampah.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i>
            Harga yang ditampilkan mengikuti data di database Bank Sampah.
        </div>
    </div>

</main>

</body>
</html>