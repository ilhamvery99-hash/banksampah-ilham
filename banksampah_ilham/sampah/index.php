<?php
session_start();
require_once "../koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'pengepul'], true)) {
    if ($role === 'siswa') {
        header("Location: ../dashboard_siswa.php");
    } elseif ($role === 'guru') {
        header("Location: ../dashboard_guru.php");
    } else {
        header("Location: ../login.php");
    }
    exit();
}

$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna';

$query = mysqli_query(
    $koneksi,
    "SELECT * FROM jenis_sampah ORDER BY id_sampah DESC"
);

if (!$query) {
    die("Gagal mengambil data sampah: " . mysqli_error($koneksi));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Jenis Sampah - Bank Sampah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f0f4ff;
            font-family: Arial, sans-serif;
            color: #172554;
        }

        .topbar {
            background: linear-gradient(135deg, #c1121f, #e63946);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 15px rgba(193, 18, 31, .2);
        }

        .brand {
            font-size: 23px;
            font-weight: 800;
        }

        .topbar small {
            color: #ffe8e8;
        }

        .content {
            padding: 30px;
            max-width: 1500px;
            margin: auto;
        }

        .page-title {
            font-weight: 800;
            color: #123c9c;
        }

        .panel {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(25, 55, 120, .09);
            border: 1px solid #e6edfa;
        }

        .panel-heading {
            color: #123c9c;
            font-size: 18px;
            font-weight: 800;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #123c9c;
            color: white;
            padding: 15px;
            white-space: nowrap;
            border: none;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #edf1fa;
        }

        .table tbody tr:hover td {
            background: #f4f8ff;
        }

        .badge-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #e7efff;
            color: #123c9c;
            font-weight: 800;
        }

        .price {
            color: #087a43;
            font-weight: 800;
            white-space: nowrap;
        }

        .btn-primary-custom {
            background: #1877f2;
            border: none;
            color: white;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
        }

        .btn-primary-custom:hover {
            background: #123c9c;
            color: white;
        }

        .btn-dashboard {
            border: 1px solid #1877f2;
            color: #123c9c;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
        }

        .btn-dashboard:hover {
            background: #e7efff;
            color: #123c9c;
        }

        .btn-warning,
        .btn-danger {
            border-radius: 8px;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px !important;
            color: #64748b;
        }

        .empty-state i {
            display: block;
            font-size: 38px;
            color: #1877f2;
            margin-bottom: 10px;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 20px;
        }

        @media (max-width: 576px) {
            .topbar {
                padding: 18px;
            }

            .content {
                padding: 18px 12px;
            }

            .panel {
                padding: 15px;
            }

            .page-title {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<nav class="topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="brand">
            <i class="bi bi-recycle me-2"></i>Bank Sampah
        </div>
        <small>Sistem Pengelolaan Data Sampah</small>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="text-end">
            <div class="fw-bold">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <small><?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?></small>
        </div>

        <a href="../logout.php"
           class="btn btn-light btn-sm ms-2 fw-bold"
           onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</nav>

<main class="content">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-recycle me-2"></i>Data Jenis Sampah
            </h2>
            <p class="text-secondary mb-0">
                Daftar jenis sampah beserta harga per kilogram.
            </p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $role === 'admin'
                ? '../dashboard.php'
                : '../portal.php' ?>"
               class="btn btn-dashboard">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>

            <?php if ($role === 'admin'): ?>
                <a href="tambah.php" class="btn btn-primary-custom">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Sampah
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div class="panel-heading">
                <i class="bi bi-list-ul me-2"></i>Daftar Jenis Sampah
            </div>

            <span class="badge rounded-pill text-bg-primary px-3 py-2">
                <?= mysqli_num_rows($query) ?> jenis sampah
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Sampah</th>
                        <th>Harga per Kg</th>
                        <?php if ($role === 'admin'): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($query) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td>
                                <span class="badge-number"><?= $no++ ?></span>
                            </td>

                            <td class="fw-bold">
                                <i class="bi bi-box-seam me-2 text-primary"></i>
                                <?= htmlspecialchars(
                                    $data['nama_sampah'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td class="price">
                                Rp <?= number_format(
                                    (float) $data['harga_per_kg'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <?php if ($role === 'admin'): ?>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="edit.php?id=<?= (int) $data['id_sampah'] ?>"
                                           class="btn btn-warning btn-sm">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </a>

                                        <a href="hapus.php?id=<?= (int) $data['id_sampah'] ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Yakin ingin menghapus jenis sampah ini?')">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="<?= $role === 'admin' ? 4 : 3 ?>"
                            class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <strong>Belum ada data jenis sampah</strong>
                            <div class="mt-1">
                                <?php if ($role === 'admin'): ?>
                                    Klik tombol Tambah Sampah untuk menambahkan data.
                                <?php else: ?>
                                    Data jenis sampah belum tersedia.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> Bank Sampah — Sistem Pengelolaan Sampah
    </div>

</main>

</body>
</html>