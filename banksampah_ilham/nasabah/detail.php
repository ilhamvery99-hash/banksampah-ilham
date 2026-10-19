<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?error=Data nasabah tidak valid");
    exit();
}

// Ambil data nasabah
$stmt = mysqli_prepare($koneksi, "
    SELECT *
    FROM nasabah
    WHERE id_nasabah = ?
");

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultNasabah = mysqli_stmt_get_result($stmt);
$nasabah = mysqli_fetch_assoc($resultNasabah);

if (!$nasabah) {
    header("Location: index.php?error=Nasabah tidak ditemukan");
    exit();
}

// Riwayat setoran
$stmtSetoran = mysqli_prepare($koneksi, "
    SELECT
        transaksi.*,
        jenis_sampah.nama_sampah
    FROM transaksi
    INNER JOIN jenis_sampah
        ON transaksi.id_sampah = jenis_sampah.id_sampah
    WHERE transaksi.id_nasabah = ?
    ORDER BY transaksi.id_transaksi DESC
");

mysqli_stmt_bind_param($stmtSetoran, "i", $id);
mysqli_stmt_execute($stmtSetoran);
$querySetoran = mysqli_stmt_get_result($stmtSetoran);

// Riwayat penarikan
$stmtPenarikan = mysqli_prepare($koneksi, "
    SELECT *
    FROM penarikan
    WHERE id_nasabah = ?
    ORDER BY id_penarikan DESC
");

mysqli_stmt_bind_param($stmtPenarikan, "i", $id);
mysqli_stmt_execute($stmtPenarikan);
$queryPenarikan = mysqli_stmt_get_result($stmtPenarikan);

// Statistik setoran
$stmtStatistik = mysqli_prepare($koneksi, "
    SELECT
        COUNT(*) AS jumlah_setoran,
        COALESCE(SUM(berat), 0) AS total_berat,
        COALESCE(SUM(total), 0) AS total_setoran
    FROM transaksi
    WHERE id_nasabah = ?
");

mysqli_stmt_bind_param($stmtStatistik, "i", $id);
mysqli_stmt_execute($stmtStatistik);

$statistik = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtStatistik)
);

// Statistik penarikan
$stmtTotalPenarikan = mysqli_prepare($koneksi, "
    SELECT
        COUNT(*) AS jumlah_penarikan,
        COALESCE(SUM(jumlah), 0) AS total_penarikan
    FROM penarikan
    WHERE id_nasabah = ?
");

mysqli_stmt_bind_param($stmtTotalPenarikan, "i", $id);
mysqli_stmt_execute($stmtTotalPenarikan);

$statPenarikan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtTotalPenarikan)
);

$namaAdmin = $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Admin';

$role = $_SESSION['role'] ?? 'admin';

if ($role === 'pengepul') {
    $dashboardUrl = "../dashboard_pengepul.php";
} elseif ($role === 'guru') {
    $dashboardUrl = "../dashboard_guru.php";
} elseif ($role === 'siswa') {
    $dashboardUrl = "../dashboard_siswa.php";
} else {
    $dashboardUrl = "../dashboard.php";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Detail Nasabah - Bank Sampah</title>

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

        .brand {
            color: white;
            text-align: center;
            font-size: 21px;
            font-weight: bold;
            padding: 8px 0 25px;
            border-bottom: 1px solid rgba(255,255,255,.2);
            margin-bottom: 22px;
        }

        .brand i {
            display: block;
            font-size: 36px;
            margin-bottom: 5px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 14px;
            margin-bottom: 8px;
            border-radius: 10px;
            transition: .2s;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,.16);
            transform: translateX(3px);
        }

        .sidebar a.active {
            background: white;
            color: var(--spider-red);
            font-weight: bold;
        }

        .sidebar .logout {
            margin-top: 25px;
            background: rgba(255,255,255,.12);
        }

        /* CONTENT */
        .content {
            margin-left: 250px;
            padding: 28px;
            min-height: 100vh;
        }

        .header-box,
        .profile-card,
        .stat-card,
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(18, 60, 156, .08);
        }

        .header-box {
            padding: 22px 25px;
            margin-bottom: 22px;
            border-top: 5px solid var(--spider-red);
        }

        .header-box h2 {
            color: var(--spider-red);
            font-weight: bold;
            margin: 0 0 5px;
        }

        .btn-back {
            display: inline-block;
            padding: 10px 17px;
            color: white;
            background: var(--spider-blue);
            text-decoration: none;
            border-radius: 9px;
            font-weight: bold;
        }

        .btn-back:hover {
            background: var(--spider-blue-light);
            color: white;
        }

        /* PROFILE */
        .profile-card {
            padding: 25px;
            margin-bottom: 22px;
            border-left: 5px solid var(--spider-blue);
        }

        .profile-top {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-icon {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #e2eaff;
            color: var(--spider-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
        }

        .profile-name {
            font-size: 25px;
            font-weight: bold;
            color: var(--spider-blue);
            overflow-wrap: anywhere;
        }

        .profile-code {
            color: #6c757d;
            margin-top: 5px;
        }

        .info-row {
            padding: 13px 0;
            border-top: 1px solid #e9edf5;
            overflow-wrap: anywhere;
        }

        .info-row i {
            color: var(--spider-red);
        }

        .balance {
            color: var(--spider-blue);
            font-size: 22px;
            font-weight: bold;
        }

        /* STATISTIK */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 20px;
            border-bottom: 4px solid var(--spider-blue);
            min-width: 0;
        }

        .stat-card:nth-child(even) {
            border-bottom-color: var(--spider-red);
        }

        .stat-icon {
            font-size: 28px;
            color: var(--spider-red);
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
            margin-top: 9px;
        }

        .stat-value {
            color: var(--spider-blue);
            font-size: 21px;
            font-weight: bold;
            margin-top: 5px;
            overflow-wrap: anywhere;
        }

        /* TABLE */
        .report-card {
            padding: 22px;
            margin-bottom: 25px;
            border-top: 4px solid var(--spider-blue);
        }

        .report-card h4 {
            color: var(--spider-red);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            background: var(--spider-blue);
            color: white;
            padding: 13px 12px;
            white-space: nowrap;
            border: none;
        }

        .table tbody td {
            padding: 12px;
            white-space: nowrap;
        }

        .table tbody tr:hover td {
            background: #f0f4ff;
        }

        .empty-row {
            color: #6c757d;
            padding: 25px !important;
        }

        .money {
            color: var(--spider-blue);
            font-weight: bold;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .header-box {
                padding: 18px;
            }

            .profile-card,
            .report-card {
                padding: 17px;
            }

            .stat-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .profile-top {
                align-items: flex-start;
            }

            .profile-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .profile-name {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <i class="bi bi-shield-fill"></i>
        BANK SAMPAH
        <div class="small fw-normal mt-1">Spider-Man Edition</div>
    </div>

    <a href="<?= htmlspecialchars($dashboardUrl) ?>">
        <i class="bi bi-house-door-fill me-2"></i>
        Dashboard
    </a>

    <a href="index.php" class="active">
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

    <a href="../penarikan/index.php">
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
</aside>

<!-- CONTENT -->
<main class="content">

    <!-- HEADER -->
    <div class="header-box">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2>
                    <i class="bi bi-person-vcard-fill me-2"></i>
                    Detail Nasabah
                </h2>

                <p class="text-muted mb-0">
                    Informasi dan riwayat transaksi nasabah
                </p>
            </div>

            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left me-1"></i>
                Kembali
            </a>
        </div>
    </div>

    <!-- PROFILE -->
    <section class="profile-card">
        <div class="profile-top">
            <div class="profile-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <div>
                <div class="profile-name">
                    <?= htmlspecialchars($nasabah['nama'] ?? '-') ?>
                </div>

                <div class="profile-code">
                    Kode Nasabah:
                    <strong>
                        <?= htmlspecialchars($nasabah['kode_nasabah'] ?? '-') ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="info-row">
            <strong>
                <i class="bi bi-geo-alt-fill me-2"></i>
                Alamat
            </strong>

            <div class="mt-1">
                <?= nl2br(htmlspecialchars($nasabah['alamat'] ?: '-')) ?>
            </div>
        </div>

        <div class="info-row">
            <strong>
                <i class="bi bi-telephone-fill me-2"></i>
                Nomor HP
            </strong>

            <div class="mt-1">
                <?= htmlspecialchars($nasabah['no_hp'] ?: '-') ?>
            </div>
        </div>

        <div class="info-row">
            <strong>
                <i class="bi bi-wallet2 me-2"></i>
                Saldo Saat Ini
            </strong>

            <div class="balance mt-1">
                Rp <?= number_format((float)$nasabah['saldo'], 0, ',', '.') ?>
            </div>
        </div>
    </section>

    <!-- STATISTIK -->
    <section class="stat-grid">

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-box-seam-fill"></i>
            </div>

            <div class="stat-label">Jumlah Setoran</div>

            <div class="stat-value">
                <?= number_format((int)$statistik['jumlah_setoran'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-speedometer2"></i>
            </div>

            <div class="stat-label">Total Berat Sampah</div>

            <div class="stat-value">
                <?= number_format((float)$statistik['total_berat'], 2, ',', '.') ?> kg
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-cash-stack"></i>
            </div>

            <div class="stat-label">Total Nilai Setoran</div>

            <div class="stat-value">
                Rp <?= number_format((float)$statistik['total_setoran'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-wallet-fill"></i>
            </div>

            <div class="stat-label">Total Penarikan</div>

            <div class="stat-value">
                Rp <?= number_format((float)$statPenarikan['total_penarikan'], 0, ',', '.') ?>
            </div>
        </div>

    </section>

    <!-- RIWAYAT SETORAN -->
    <section class="report-card">
        <h4>
            <i class="bi bi-box-seam-fill me-2"></i>
            Riwayat Setoran
        </h4>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Jenis Sampah</th>
                        <th>Berat</th>
                        <th>Harga/Kg</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($querySetoran) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($querySetoran)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= !empty($data['tanggal'])
                                    ? date('d-m-Y H:i', strtotime($data['tanggal']))
                                    : '-' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($data['nama_sampah']) ?>
                            </td>

                            <td>
                                <?= number_format((float)$data['berat'], 2, ',', '.') ?> kg
                            </td>

                            <td>
                                Rp <?= number_format((float)$data['harga_per_kg'], 0, ',', '.') ?>
                            </td>

                            <td class="money">
                                Rp <?= number_format((float)$data['total'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center empty-row">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Belum ada riwayat setoran.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- RIWAYAT PENARIKAN -->
    <section class="report-card">
        <h4>
            <i class="bi bi-wallet-fill me-2"></i>
            Riwayat Penarikan
        </h4>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Jumlah Penarikan</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($queryPenarikan) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($queryPenarikan)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= !empty($data['tanggal'])
                                    ? date('d-m-Y H:i', strtotime($data['tanggal']))
                                    : '-' ?>
                            </td>

                            <td class="money">
                                Rp <?= number_format((float)$data['jumlah'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center empty-row">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Belum ada riwayat penarikan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="text-center text-muted small mt-4 mb-2">
        Bank Sampah &bull; Spider-Man Edition
    </div>

</main>

</body>
</html>