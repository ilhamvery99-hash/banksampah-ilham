
<?php
session_start();
require_once "koneksi.php";

// ===============================
// CEK LOGIN DAN ROLE SISWA
// ===============================
if (
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true
) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: login.php");
    exit();
}

// ===============================
// FUNGSI BANTU
// ===============================
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// ===============================
// AMBIL IDENTITAS SISWA
// ===============================
$username = $_SESSION['username'] ?? '';
$id_nasabah = (int)($_SESSION['id_nasabah'] ?? 0);

// Jika ID nasabah belum disimpan di session,
// ambil dari tabel users berdasarkan username.
if ($id_nasabah <= 0 && $username !== '') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_nasabah FROM users
         WHERE username = ? AND role = 'siswa'
         LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $resultUser = mysqli_stmt_get_result($stmt);
        $userData = mysqli_fetch_assoc($resultUser);

        if ($userData) {
            $id_nasabah = (int)($userData['id_nasabah'] ?? 0);
            $_SESSION['id_nasabah'] = $id_nasabah;
        }

        mysqli_stmt_close($stmt);
    }
}

// ===============================
// AMBIL DATA NASABAH
// ===============================
$nasabah = null;

if ($id_nasabah > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_nasabah, kode_nasabah, nama,
                alamat, no_hp, saldo
         FROM nasabah
         WHERE id_nasabah = ?
         LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
        mysqli_stmt_execute($stmt);
        $resultNasabah = mysqli_stmt_get_result($stmt);
        $nasabah = mysqli_fetch_assoc($resultNasabah);
        mysqli_stmt_close($stmt);
    }
}

if (!$nasabah) {
    $namaSiswa = $_SESSION['nama'] ?? $username ?: 'Siswa';
    $saldo = 0;
    $kodeNasabah = '-';
    $alamat = '-';
    $noHp = '-';
} else {
    $namaSiswa = $nasabah['nama'];
    $saldo = (float)$nasabah['saldo'];
    $kodeNasabah = $nasabah['kode_nasabah'];
    $alamat = $nasabah['alamat'];
    $noHp = $nasabah['no_hp'];
}

// ===============================
// STATISTIK SETORAN
// ===============================
$totalSetoran = 0;
$totalBerat = 0;

if ($id_nasabah > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS jumlah,
                COALESCE(SUM(berat), 0) AS berat_total,
                COALESCE(SUM(total), 0) AS uang_total
         FROM transaksi
         WHERE id_nasabah = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
        mysqli_stmt_execute($stmt);
        $stat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($stat) {
            $totalSetoran = (int)$stat['jumlah'];
            $totalBerat = (float)$stat['berat_total'];
        }

        mysqli_stmt_close($stmt);
    }
}

// ===============================
// TOTAL PENARIKAN
// ===============================
$totalPenarikan = 0;

if ($id_nasabah > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM penarikan
         WHERE id_nasabah = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
        mysqli_stmt_execute($stmt);
        $dataTarik = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($dataTarik) {
            $totalPenarikan = (float)$dataTarik['total'];
        }

        mysqli_stmt_close($stmt);
    }
}

// ===============================
// RIWAYAT SETORAN TERBARU
// ===============================
$riwayatSetoran = [];

if ($id_nasabah > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT t.tanggal, j.nama_sampah,
                t.berat, t.harga_per_kg, t.total
         FROM transaksi t
         LEFT JOIN jenis_sampah j
                ON t.id_sampah = j.id_sampah
         WHERE t.id_nasabah = ?
         ORDER BY t.tanggal DESC
         LIMIT 5"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
        mysqli_stmt_execute($stmt);
        $resultSetoran = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($resultSetoran)) {
            $riwayatSetoran[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
}

// ===============================
// RIWAYAT PENARIKAN TERBARU
// ===============================
$riwayatPenarikan = [];

if ($id_nasabah > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT tanggal, jumlah
         FROM penarikan
         WHERE id_nasabah = ?
         ORDER BY tanggal DESC
         LIMIT 5"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
        mysqli_stmt_execute($stmt);
        $resultTarik = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($resultTarik)) {
            $riwayatPenarikan[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
}

$halaman = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Siswa | Bank Sampah Spider-Man</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --spidey-red: #d71932;
            --spidey-dark-red: #9e1024;
            --spidey-blue: #123d79;
            --spidey-dark-blue: #092650;
            --spidey-white: #ffffff;
            --spidey-bg: #f2f5fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--spidey-bg);
            color: #18263b;
            font-family: Arial, Helvetica, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 255px;
            overflow-y: auto;
            padding: 24px 16px;
            color: white;
            background:
                radial-gradient(circle at 100% 0%,
                    rgba(255,255,255,.12), transparent 35%),
                linear-gradient(165deg,
                    var(--spidey-red), var(--spidey-dark-red) 52%,
                    var(--spidey-dark-blue));
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 7px 25px;
            border-bottom: 1px solid rgba(255,255,255,.22);
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            border: 2px solid white;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 26px;
            background: var(--spidey-blue);
            box-shadow: 0 0 0 4px rgba(255,255,255,.12);
        }

        .brand h5 {
            margin: 0;
            font-weight: 800;
            font-size: 17px;
        }

        .brand small {
            color: #ffe1e5;
            font-size: 11px;
        }

        .menu-label {
            margin: 25px 10px 10px;
            color: #ffd8de;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            margin: 5px 0;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            transition: .2s;
        }

        .menu-link i {
            font-size: 18px;
        }

        .menu-link:hover,
        .menu-link.active {
            color: var(--spidey-dark-blue);
            background: white;
            transform: translateX(3px);
        }

        .sidebar-note {
            margin-top: 30px;
            padding: 14px;
            border-radius: 12px;
            background: rgba(8, 30, 65, .45);
            border: 1px solid rgba(255,255,255,.2);
            font-size: 12px;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 255px;
            padding: 28px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h4 {
            margin: 0 0 5px;
            font-weight: 800;
        }

        .topbar p {
            margin: 0;
            color: #6c7890;
            font-size: 13px;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            background: white;
            border: 1px solid #e3e9f3;
            border-radius: 14px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            color: white;
            background: var(--spidey-blue);
            border-radius: 50%;
            font-size: 19px;
        }

        .welcome {
            position: relative;
            overflow: hidden;
            padding: 30px;
            margin-bottom: 24px;
            color: white;
            border-radius: 20px;
            background:
                repeating-linear-gradient(45deg,
                    transparent 0 35px,
                    rgba(255,255,255,.045) 36px 38px),
                linear-gradient(115deg,
                    var(--spidey-red) 0%,
                    #b71535 42%,
                    var(--spidey-blue) 100%);
            box-shadow: 0 10px 28px rgba(18,61,121,.14);
        }

        .welcome h2 {
            font-weight: 900;
            margin: 10px 0;
        }

        .welcome p {
            max-width: 540px;
            margin-bottom: 0;
            color: #fff0f2;
            font-size: 14px;
            line-height: 1.7;
        }

        .hero-badge {
            display: inline-block;
            padding: 6px 11px;
            border: 1px solid rgba(255,255,255,.45);
            border-radius: 30px;
            background: rgba(255,255,255,.12);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .hero-icon {
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 95px;
            opacity: .17;
        }

        .stat-card {
            height: 100%;
            padding: 21px;
            border: 1px solid #e7ecf4;
            border-radius: 17px;
            background: white;
            box-shadow: 0 5px 18px rgba(25,49,85,.035);
            transition: transform .2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 47px;
            height: 47px;
            margin-bottom: 17px;
            color: white;
            background: var(--spidey-blue);
            border-radius: 13px;
            font-size: 22px;
        }

        .stat-icon.red {
            background: var(--spidey-red);
        }

        .stat-icon.light {
            color: var(--spidey-blue);
            background: #e8efff;
        }

        .stat-label {
            color: #748097;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .stat-value {
            color: #172842;
            font-size: clamp(19px, 2vw, 25px);
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .section-card {
            height: 100%;
            background: white;
            border: 1px solid #e5eaf2;
            border-radius: 17px;
            overflow: hidden;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 20px 21px;
            border-bottom: 1px solid #edf0f6;
        }

        .section-heading h5 {
            margin: 0;
            font-weight: 800;
            font-size: 15px;
        }

        .section-heading i {
            color: var(--spidey-red);
        }

        .table-responsive {
            padding: 0 15px 12px;
        }

        .table {
            font-size: 12px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table thead th {
            padding-top: 15px;
            padding-bottom: 13px;
            color: #728098;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
            background: #f8faff;
        }

        .table tbody td {
            padding-top: 13px;
            padding-bottom: 13px;
        }

        .money {
            color: var(--spidey-blue);
            font-weight: 800;
        }

        .empty-state {
            padding: 28px 12px;
            color: #7c8799;
            text-align: center;
            font-size: 13px;
        }

        .profile-item {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 13px 20px;
            border-bottom: 1px solid #f0f2f7;
            font-size: 13px;
        }

        .profile-item:last-child {
            border-bottom: 0;
        }

        .profile-item span {
            color: #78849a;
        }

        .profile-item strong {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .btn-spidey {
            color: white;
            border: none;
            background: var(--spidey-red);
            border-radius: 9px;
            padding: 9px 13px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-spidey:hover {
            color: white;
            background: var(--spidey-dark-red);
        }

        .footer {
            padding: 25px 0 5px;
            text-align: center;
            color: #8993a6;
            font-size: 11px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 76px;
                padding: 18px 9px;
            }

            .brand {
                justify-content: center;
                padding: 4px 0 22px;
            }

            .brand-logo {
                width: 43px;
                height: 43px;
            }

            .brand-text,
            .menu-label,
            .menu-text,
            .sidebar-note {
                display: none;
            }

            .menu-link {
                justify-content: center;
                padding: 13px 8px;
            }

            .main-content {
                margin-left: 76px;
                padding: 22px 17px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 17px 12px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h4 {
                font-size: 18px;
            }

            .user-chip {
                padding: 7px;
            }

            .user-info {
                display: none;
            }

            .welcome {
                padding: 23px 19px;
            }

            .welcome h2 {
                font-size: 23px;
            }

            .hero-icon {
                right: 8px;
                font-size: 65px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo">
            <i class="bi bi-shield-fill-check"></i>
        </div>
        <div class="brand-text">
            <h5>BANK SAMPAH</h5>
            <small>SPIDER-MAN EDITION</small>
        </div>
    </div>

    <div class="menu-label">MENU SISWA</div>

    <a href="dashboard_siswa.php"
       class="menu-link active"
       title="Dashboard">
        <i class="bi bi-grid-1x2-fill"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="#profil" class="menu-link" title="Profil">
        <i class="bi bi-person-circle"></i>
        <span class="menu-text">Profil Saya</span>
    </a>

    <a href="#setoran" class="menu-link" title="Riwayat Setoran">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Riwayat Setoran</span>
    </a>

    <a href="#penarikan" class="menu-link" title="Penarikan">
        <i class="bi bi-wallet2"></i>
        <span class="menu-text">Riwayat Penarikan</span>
    </a>

    <div class="menu-label">AKUN</div>

    <a href="logout.php"
       class="menu-link"
       title="Logout"
       onclick="return confirm('Yakin ingin keluar dari akun?')">
        <i class="bi bi-box-arrow-left"></i>
        <span class="menu-text">Logout</span>
    </a>

    <div class="sidebar-note">
        <i class="bi bi-lightbulb-fill"></i>
        <strong>Be Your Own Hero!</strong>
        <br>
        Pilah sampah, jaga bumi, dan kumpulkan saldo tabunganmu.
    </div>
</aside>

<!-- KONTEN UTAMA -->
<main class="main-content">

    <div class="topbar">
        <div>
            <h4>Dashboard Siswa</h4>
            <p>Selamat datang di Bank Sampah sekolah!</p>
        </div>

        <div class="user-chip">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-info">
                <div class="fw-bold small"><?= e($namaSiswa) ?></div>
                <div class="text-secondary" style="font-size:11px">
                    Siswa
                </div>
            </div>
        </div>
    </div>

    <?php if (!$nasabah): ?>
        <div class="alert alert-warning">
            <strong>Data nasabah belum terhubung.</strong>
            Hubungkan akun siswa pada tabel <code>users</code> dengan
            nasabah yang sesuai melalui kolom <code>id_nasabah</code>.
            Setelah terhubung, saldo dan riwayat akan tampil.
        </div>
    <?php endif; ?>

    <!-- HERO -->
    <section class="welcome">
        <span class="hero-badge">
            <i class="bi bi-lightning-charge-fill"></i>
            SPIDER-MAN STUDENT PORTAL
        </span>

        <h2>Halo, <?= e($namaSiswa) ?>! <span>🕷️</span></h2>

        <p>
            Jadilah pahlawan bagi bumi! Setorkan sampah yang bisa didaur
            ulang, pantau tabunganmu, dan mulai kebiasaan ramah lingkungan
            dari sekolah.
        </p>

        <i class="bi bi-shield-shaded hero-icon"></i>
    </section>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-wallet-fill"></i>
                </div>
                <div class="stat-label">Saldo Tabungan</div>
                <div class="stat-value"><?= rupiah($saldo) ?></div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-recycle"></i>
                </div>
                <div class="stat-label">Jumlah Setoran</div>
                <div class="stat-value">
                    <?= number_format($totalSetoran, 0, ',', '.') ?>
                    <small class="fs-6 fw-normal">kali</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon light">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-label">Total Berat Sampah</div>
                <div class="stat-value">
                    <?= number_format($totalBerat, 2, ',', '.') ?>
                    <small class="fs-6 fw-normal">kg</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label">Total Penarikan</div>
                <div class="stat-value"><?= rupiah($totalPenarikan) ?></div>
            </div>
        </div>

    </div>

    <div class="row g-4 mb-4">

<!-- RIWAYAT SETORAN -->
<div class="col-12">
    <section class="section-card" id="setoran">

        <div class="section-heading">
            <h5>
                <i class="bi bi-recycle me-2"></i>
                Riwayat Setoran Terbaru
            </h5>

            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-primary">
                    5 Terbaru
                </span>

                <a href="setor_sampah.php"
                   class="btn btn-spidey">
                    <i class="bi bi-plus-circle-fill me-1"></i>
                    Setor Sampah
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                    <?php if (count($riwayatSetoran) > 0): ?>

                        <?php foreach ($riwayatSetoran as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= e(date(
                                        'd-m-Y H:i',
                                        strtotime($row['tanggal'])
                                    )) ?>
                                </td>

                                <td>
                                    <?= e($row['nama_sampah'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$row['berat'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?> kg
                                </td>

                                <td>
                                    <?= rupiah($row['harga_per_kg']) ?>
                                </td>

                                <td class="money">
                                    <?= rupiah($row['total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Belum ada riwayat setoran.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>
</div>


<!-- RIWAYAT PENARIKAN -->
<div class="col-12 col-xl-7">
    <section class="section-card" id="penarikan">

        <div class="section-heading">
            <h5>
                <i class="bi bi-wallet2 me-2"></i>
                Riwayat Penarikan
            </h5>

            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-danger">
                    5 Terbaru
                </span>

                <a href="tarik_saldo.php"
                   class="btn btn-spidey">
                    <i class="bi bi-cash-stack me-1"></i>
                    Tarik Saldo
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Jumlah Penarikan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($riwayatPenarikan) > 0): ?>

                        <?php foreach ($riwayatPenarikan as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= e(date(
                                        'd-m-Y H:i',
                                        strtotime($row['tanggal'])
                                    )) ?>
                                </td>

                                <td class="money">
                                    <?= rupiah($row['jumlah']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Belum ada riwayat penarikan.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>
</div>

        <!-- PROFIL -->
        <div class="col-12 col-xl-5">
            <section class="section-card" id="profil">
                <div class="section-heading">
                    <h5>
                        <i class="bi bi-person-vcard me-2"></i>
                        Profil Nasabah
                    </h5>
                    <i class="bi bi-shield-fill-check fs-5"></i>
                </div>

                <div class="profile-item">
                    <span>Nama</span>
                    <strong><?= e($namaSiswa) ?></strong>
                </div>

                <div class="profile-item">
                    <span>Kode Nasabah</span>
                    <strong><?= e($kodeNasabah) ?></strong>
                </div>

                <div class="profile-item">
                    <span>Alamat</span>
                    <strong><?= e($alamat) ?></strong>
                </div>

                <div class="profile-item">
                    <span>No. HP</span>
                    <strong><?= e($noHp) ?></strong>
                </div>

                <div class="p-3">
                    <a href="logout.php"
                       class="btn btn-spidey w-100"
                       onclick="return confirm('Yakin ingin keluar dari akun?')">
                        <i class="bi bi-box-arrow-right me-2"></i>
                        Keluar dari Akun
                    </a>
                </div>
            </section>
        </div>

    </div>

    <footer class="footer">
        <div>
            <i class="bi bi-shield-fill-check me-1"></i>
            Bank Sampah Spider-Man Edition
        </div>
        <div class="mt-1">
            Dengan memilah sampah, kamu ikut menjaga bumi.
        </div>
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
