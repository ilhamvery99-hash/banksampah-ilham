<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$nama_admin = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin';

$query = mysqli_query($koneksi, "
    SELECT *
    FROM nasabah
    ORDER BY id_nasabah DESC
");

if (!$query) {
    die("Query nasabah gagal: " . mysqli_error($koneksi));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Nasabah - Bank Sampah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --merah: #e32636;
            --merah-gelap: #b81527;
            --biru: #145da0;
            --biru-gelap: #103b66;
            --latar: #f1f6fc;
            --teks: #243247;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--latar);
            color: var(--teks);
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
            padding: 24px 16px;
            color: white;
            background: linear-gradient(
                160deg,
                var(--merah),
                var(--merah-gelap) 48%,
                var(--biru-gelap) 49%,
                var(--biru)
            );
            box-shadow: 4px 0 18px rgba(16, 59, 102, .12);
            z-index: 1000;
        }

        .logo {
            text-align: center;
            padding: 5px 0 25px;
            margin-bottom: 22px;
            border-bottom: 1px solid rgba(255,255,255,.3);
        }

        .logo-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.15);
            border: 3px solid white;
            border-radius: 50%;
            font-size: 31px;
        }

        .logo h4 {
            margin: 0;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .logo small {
            opacity: .85;
        }

        .menu-label {
            margin: 20px 12px 10px;
            font-size: 10px;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,.7);
            font-weight: bold;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            margin-bottom: 7px;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            transition: .2s;
        }

        .menu a i {
            width: 23px;
            text-align: center;
            font-size: 19px;
        }

        .menu a:hover,
        .menu a.active {
            background: rgba(255,255,255,.2);
            color: white;
            transform: translateX(3px);
        }

        .menu a.logout {
            margin-top: 25px;
            background: rgba(255,255,255,.12);
        }

        /* KONTEN */
        .content {
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
            margin-bottom: 24px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(24,59,92,.06);
        }

        .topbar h3 {
            margin: 0;
            color: var(--biru-gelap);
            font-size: 24px;
            font-weight: bold;
        }

        .topbar-subtitle {
            margin-top: 5px;
            color: #718096;
            font-size: 13px;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--biru);
            color: white;
            font-size: 22px;
        }

        .role-label {
            text-align: right;
            color: #718096;
            font-size: 12px;
        }

        /* BANNER */
        .hero {
            position: relative;
            overflow: hidden;
            padding: 25px;
            margin-bottom: 24px;
            color: white;
            border-radius: 16px;
            background: linear-gradient(110deg, var(--biru), #287ec0);
        }

        .hero h4 {
            font-weight: bold;
            margin-bottom: 7px;
        }

        .hero p {
            margin: 0;
            opacity: .9;
        }

        .hero-icon {
            position: absolute;
            right: 30px;
            top: 5px;
            font-size: 85px;
            opacity: .16;
        }

        /* MAIN CARD */
        .main-card {
            padding: 24px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(24,59,92,.06);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .page-header h4 {
            margin: 0 0 6px;
            color: var(--biru-gelap);
            font-weight: bold;
            font-size: 19px;
        }

        .page-header p {
            margin: 0;
            color: #718096;
            font-size: 13px;
        }

        .search-box {
            width: 270px;
            max-width: 100%;
        }

        .form-control,
        .form-select {
            border-color: #dce5ef;
            border-radius: 9px;
            padding: 10px 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--biru);
            box-shadow: 0 0 0 .2rem rgba(20,93,160,.12);
        }

        /* TOMBOL */
        .btn {
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--biru);
            border-color: var(--biru);
        }

        .btn-primary:hover {
            background: var(--biru-gelap);
            border-color: var(--biru-gelap);
        }

        .btn-danger {
            background: var(--merah);
            border-color: var(--merah);
        }

        .btn-danger:hover {
            background: var(--merah-gelap);
            border-color: var(--merah-gelap);
        }

        .btn-add {
            white-space: nowrap;
            padding: 10px 15px;
        }

        /* TABEL */
        .table-responsive {
            border: 1px solid #e6edf5;
            border-radius: 12px;
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            padding: 14px;
            color: white;
            background: var(--biru);
            border: none;
            font-size: 13px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px 14px;
            border-color: #edf1f6;
            font-size: 13px;
        }

        .table tbody tr:hover td {
            background: #f3f8ff;
        }

        .kode-badge {
            display: inline-block;
            padding: 6px 10px;
            color: var(--biru);
            background: #e6f1ff;
            border-radius: 7px;
            font-weight: bold;
            white-space: nowrap;
        }

        .saldo {
            color: #16834b;
            font-weight: bold;
            white-space: nowrap;
        }

        .aksi {
            min-width: 200px;
            white-space: nowrap;
        }

        .aksi .btn {
            margin: 2px;
        }

        .empty-state {
            padding: 40px 15px !important;
            text-align: center;
            color: #718096;
        }

        .empty-state i {
            display: block;
            font-size: 38px;
            margin-bottom: 10px;
            color: #9aacc0;
        }

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .footer {
            padding: 25px 0 5px;
            color: #8290a3;
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 75px;
                padding: 20px 9px;
            }

            .logo h4,
            .logo small,
            .menu-text,
            .menu-label {
                display: none;
            }

            .logo-icon {
                width: 48px;
                height: 48px;
                font-size: 23px;
            }

            .menu a {
                justify-content: center;
                padding: 14px 5px;
            }

            .menu a i {
                font-size: 21px;
            }

            .content {
                margin-left: 75px;
                padding: 14px;
            }

            .topbar {
                align-items: flex-start;
                padding: 16px;
            }

            .topbar h3 {
                font-size: 19px;
            }

            .admin-name {
                display: none;
            }

            .hero {
                padding: 20px;
            }

            .hero-icon {
                right: 10px;
                font-size: 60px;
            }

            .main-card {
                padding: 16px;
            }

            .page-header {
                align-items: stretch;
            }

            .page-tools {
                width: 100%;
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .btn-add {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">
            <i class="bi bi-recycle"></i>
        </div>
        <h4>BANK SAMPAH</h4>
        <small>Panel Administrator</small>
    </div>

    <div class="menu">
        <div class="menu-label">MENU UTAMA</div>

        <a href="../dashboard.php">
            <i class="bi bi-speedometer2"></i>
            <span class="menu-text">Dashboard</span>
        </a>

        <a href="index.php" class="active">
            <i class="bi bi-people-fill"></i>
            <span class="menu-text">Nasabah</span>
        </a>

        <a href="../sampah/index.php">
            <i class="bi bi-recycle"></i>
            <span class="menu-text">Jenis Sampah</span>
        </a>

        <a href="../transaksi/index.php">
            <i class="bi bi-box-seam-fill"></i>
            <span class="menu-text">Setoran</span>
        </a>

        <a href="../penarikan/index.php">
            <i class="bi bi-wallet-fill"></i>
            <span class="menu-text">Penarikan</span>
        </a>

        <a href="../laporan/index.php">
            <i class="bi bi-bar-chart-fill"></i>
            <span class="menu-text">Laporan</span>
        </a>

        <div class="menu-label">AKUN</div>

        <a href="../logout.php" class="logout"
           onclick="return confirm('Yakin ingin logout?')">
            <i class="bi bi-box-arrow-right"></i>
            <span class="menu-text">Logout</span>
        </a>
    </div>
</aside>

<!-- KONTEN -->
<main class="content">

    <?php if ($success !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- TOPBAR -->
    <header class="topbar">
        <div>
            <h3><i class="bi bi-people-fill me-2"></i>Data Nasabah</h3>
            <div class="topbar-subtitle">Kelola data nasabah Bank Sampah</div>
        </div>

        <div class="admin">
            <div class="admin-name">
                <strong><?= e($nama_admin) ?></strong>
                <div class="role-label">Administrator</div>
            </div>
            <div class="admin-icon">
                <i class="bi bi-person-fill"></i>
            </div>
        </div>
    </header>

    <!-- BANNER -->
    <section class="hero">
        <i class="bi bi-shield-check hero-icon"></i>
        <h4>Manajemen Data Nasabah</h4>
        <p>Lihat, cari, tambah, dan kelola informasi nasabah dalam satu halaman.</p>
    </section>

    <!-- DAFTAR NASABAH -->
    <section class="main-card">

        <div class="page-header">
            <div>
                <h4><i class="bi bi-card-list me-2"></i>Daftar Nasabah</h4>
                <p>Data seluruh nasabah yang terdaftar di sistem.</p>
            </div>

            <div class="page-tools d-flex gap-2">
                <input
                    type="search"
                    id="searchInput"
                    class="form-control search-box"
                    placeholder="Cari kode, nama, alamat..."
                    autocomplete="off"
                >

                <a href="tambah.php" class="btn btn-primary btn-add">
                    <i class="bi bi-person-plus-fill me-1"></i> Tambah
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="nasabahTable">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Nasabah</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>No. HP</th>
                        <th>Saldo</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($query) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <span class="kode-badge">
                                    <?= e($data['kode_nasabah'] ?? '-') ?>
                                </span>
                            </td>

                            <td>
                                <strong><?= e($data['nama'] ?? '-') ?></strong>
                            </td>

                            <td><?= e($data['alamat'] ?? '-') ?></td>

                            <td><?= e($data['no_hp'] ?? '-') ?></td>

                            <td class="saldo">
                                Rp <?= number_format(
                                    (float)($data['saldo'] ?? 0),
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td class="aksi">
                                <a
                                    href="detail.php?id=<?= (int)$data['id_nasabah'] ?>"
                                    class="btn btn-info btn-sm text-white"
                                    title="Lihat detail"
                                >
                                    <i class="bi bi-eye-fill"></i> Detail
                                </a>

                                <a
                                    href="edit.php?id=<?= (int)$data['id_nasabah'] ?>"
                                    class="btn btn-warning btn-sm"
                                    title="Edit nasabah"
                                >
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </a>

                                <a
                                    href="hapus.php?id=<?= (int)$data['id_nasabah'] ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Yakin ingin menghapus nasabah ini?')"
                                    title="Hapus nasabah"
                                >
                                    <i class="bi bi-trash-fill"></i> Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="bi bi-people"></i>
                            <strong>Belum ada data nasabah</strong>
                            <div class="mt-1">Klik tombol Tambah untuk memasukkan nasabah baru.</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bottom-actions">
            <small class="text-secondary" id="tableInfo">
                Menampilkan data nasabah
            </small>

            <a href="../dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
            </a>
        </div>

    </section>

    <footer class="footer">
        &copy; <?= date('Y') ?> Bank Sampah | Panel Administrator
    </footer>

</main>

<script>
const searchInput = document.getElementById('searchInput');
const rows = Array.from(
    document.querySelectorAll('#nasabahTable tbody tr')
);
const tableInfo = document.getElementById('tableInfo');

function filterNasabah() {
    const keyword = searchInput.value.toLowerCase().trim();
    let terlihat = 0;

    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        const cocok = text.includes(keyword);

        row.style.display = cocok ? '' : 'none';

        if (cocok && !row.querySelector('.empty-state')) {
            terlihat++;
        }
    });

    if (keyword === '') {
        tableInfo.textContent = 'Total nasabah: ' + terlihat;
    } else {
        tableInfo.textContent =
            'Ditemukan ' + terlihat + ' data dari pencarian "' +
            searchInput.value + '"';
    }
}

searchInput.addEventListener('input', filterNasabah);
filterNasabah();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>