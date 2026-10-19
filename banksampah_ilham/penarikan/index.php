<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

$role = $_SESSION['role'] ?? 'admin';

$dashboardUrl = match ($role) {
    'pengepul' => '../dashboard_pengepul.php',
    'guru' => '../dashboard_guru.php',
    'siswa' => '../dashboard_siswa.php',
    default => '../dashboard.php'
};

// Ambil data penarikan
$query = mysqli_query($koneksi, "
    SELECT
        penarikan.id_penarikan,
        penarikan.jumlah,
        penarikan.tanggal,
        nasabah.kode_nasabah,
        nasabah.nama
    FROM penarikan
    INNER JOIN nasabah
        ON penarikan.id_nasabah = nasabah.id_nasabah
    ORDER BY penarikan.id_penarikan DESC
");

if (!$query) {
    die("Query gagal: " . mysqli_error($koneksi));
}

// Hak akses
$bolehTambah = in_array($role, ['admin', 'pengepul'], true);
$bolehHapus = ($role === 'admin');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Penarikan - Bank Sampah</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --spider-red: #c1121f;
            --spider-red-light: #e63946;
            --spider-blue: #123c9c;
            --spider-blue-light: #1877f2;
            --page-bg: #f0f4ff;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--page-bg);
            color: #263238;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            padding: 24px 15px;
            background: linear-gradient(
                180deg,
                var(--spider-red),
                #8b0000
            );
            box-shadow: 4px 0 15px rgba(0, 0, 0, .12);
            z-index: 1000;
            overflow-y: auto;
        }

        .logo {
            color: white;
            text-align: center;
            font-size: 21px;
            font-weight: bold;
            padding: 8px 0 25px;
            margin-bottom: 22px;
            border-bottom: 1px solid rgba(255,255,255,.2);
        }

        .logo i {
            display: block;
            font-size: 36px;
            margin-bottom: 5px;
        }

        .menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 14px;
            margin-bottom: 8px;
            border-radius: 10px;
            transition: .2s;
        }

        .menu a:hover {
            background: rgba(255,255,255,.16);
            transform: translateX(3px);
        }

        .menu a.active {
            background: white;
            color: var(--spider-red);
            font-weight: bold;
        }

        .menu .logout {
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
            flex-wrap: wrap;
            gap: 15px;
            background: white;
            padding: 22px 25px;
            margin-bottom: 22px;
            border-radius: 15px;
            border-top: 5px solid var(--spider-red);
            box-shadow: 0 5px 20px rgba(18,60,156,.08);
        }

        .topbar h2 {
            color: var(--spider-red);
            font-weight: bold;
            margin: 0 0 5px;
        }

        .topbar p {
            margin-bottom: 0;
        }

        /* TOMBOL */
        .btn-tambah {
            display: inline-block;
            background: var(--spider-blue);
            color: white;
            padding: 11px 18px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: bold;
            transition: .2s;
        }

        .btn-tambah:hover {
            background: var(--spider-blue-light);
            color: white;
            transform: translateY(-1px);
        }

        .btn-hapus {
            display: inline-block;
            background: var(--spider-red);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-hapus:hover {
            background: #a30f19;
            color: white;
        }

        /* TABEL */
        .card-table {
            background: white;
            border-radius: 15px;
            padding: 22px;
            border-top: 4px solid var(--spider-blue);
            box-shadow: 0 5px 20px rgba(18,60,156,.08);
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            background: var(--spider-blue);
            color: white;
            border: none;
            padding: 14px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px;
            white-space: nowrap;
        }

        .table tbody tr:hover td {
            background: #f0f4ff;
        }

        .badge-uang {
            display: inline-block;
            background: #e2eaff;
            color: var(--spider-blue);
            padding: 8px 11px;
            border-radius: 8px;
            font-weight: bold;
        }

        .kode-badge {
            display: inline-block;
            background: #ffeaec;
            color: var(--spider-red);
            padding: 6px 9px;
            border-radius: 7px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 35px !important;
            color: #6c757d;
        }

        .alert {
            border-radius: 10px;
        }

        .footer {
            text-align: center;
            color: #7a8498;
            font-size: 13px;
            margin-top: 25px;
        }

        /* RESPONSIVE */
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

            .topbar {
                padding: 18px;
            }

            .card-table {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo">
        <i class="bi bi-shield-fill"></i>
        BANK SAMPAH
        <div class="small fw-normal mt-1">Spider-Man Edition</div>
    </div>

    <nav class="menu">
        <a href="<?= htmlspecialchars($dashboardUrl) ?>">
            <i class="bi bi-house-door-fill me-2"></i>
            Dashboard
        </a>

        <a href="../nasabah/index.php">
            <i class="bi bi-people-fill me-2"></i>
            Data Nasabah
        </a>

        <a href="../sampah/index.php">
            <i class="bi bi-recycle me-2"></i>
            Jenis Sampah
        </a>

        <a href="../transaksi/index.php">
            <i class="bi bi-box-seam-fill me-2"></i>
            Setoran
        </a>

        <a href="index.php" class="active">
            <i class="bi bi-wallet-fill me-2"></i>
            Penarikan
        </a>

        <a href="../laporan/index.php">
            <i class="bi bi-bar-chart-fill me-2"></i>
            Laporan
        </a>

        <a
            href="../logout.php"
            class="logout"
            onclick="return confirm('Yakin ingin logout?')"
        >
            <i class="bi bi-box-arrow-right me-2"></i>
            Logout
        </a>
    </nav>
</aside>

<!-- KONTEN UTAMA -->
<main class="content">

    <!-- NOTIFIKASI -->
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['success']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Tutup"
            ></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_GET['error']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Tutup"
            ></button>
        </div>
    <?php endif; ?>

    <!-- HEADER -->
    <section class="topbar">
        <div>
            <h2>
                <i class="bi bi-wallet-fill me-2"></i>
                Data Penarikan
            </h2>

            <p class="text-muted">
                Riwayat penarikan saldo nasabah
            </p>
        </div>

        <?php if ($bolehTambah): ?>
            <a href="tambah.php" class="btn-tambah">
                <i class="bi bi-plus-circle-fill me-1"></i>
                Tambah Penarikan
            </a>
        <?php endif; ?>
    </section>

    <!-- TABEL PENARIKAN -->
    <section class="card-table">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 class="fw-bold mb-0" style="color: #123c9c;">
                <i class="bi bi-clock-history me-2"></i>
                Riwayat Transaksi Penarikan
            </h5>

            <span class="badge text-bg-primary">
                <?= number_format(mysqli_num_rows($query), 0, ',', '.') ?> transaksi
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Kode Nasabah</th>
                        <th>Nama Nasabah</th>
                        <th>Jumlah Penarikan</th>

                        <?php if ($bolehHapus): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($query) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= !empty($row['tanggal'])
                                    ? date('d-m-Y H:i', strtotime($row['tanggal']))
                                    : '-' ?>
                            </td>

                            <td>
                                <span class="kode-badge">
                                    <?= htmlspecialchars($row['kode_nasabah']) ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['nama']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="badge-uang">
                                    Rp <?= number_format(
                                        (float)$row['jumlah'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </span>
                            </td>

                            <?php if ($bolehHapus): ?>
                                <td>
                                    <a
                                        href="hapus.php?id=<?= (int)$row['id_penarikan'] ?>"
                                        class="btn-hapus"
                                        onclick="return confirm('Yakin ingin menghapus penarikan ini? Pastikan proses hapus mengembalikan saldo nasabah dengan benar.');"
                                    >
                                        <i class="bi bi-trash-fill me-1"></i>
                                        Hapus
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td
                            colspan="<?= $bolehHapus ? 6 : 5 ?>"
                            class="empty"
                        >
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <strong>Belum ada data penarikan.</strong>
                            <div class="small mt-1">
                                Data penarikan akan tampil di sini setelah transaksi dibuat.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer">
        Bank Sampah &bull; Spider-Man Edition
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>