```php
<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'pengepul') {
    if ($role === 'admin') {
        header("Location: persetujuan.php");
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

function e($nilai) {
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

// Periksa tabel pengajuan
$cekTabel = mysqli_query($koneksi, "SHOW TABLES LIKE 'pengajuan_setoran'");

if (!$cekTabel || mysqli_num_rows($cekTabel) === 0) {
    die("Tabel pengajuan_setoran belum tersedia. Periksa database.");
}

// Ambil data pengajuan
$sql = "
    SELECT
        p.id_pengajuan,
        p.id_nasabah,
        p.id_sampah,
        p.berat,
        p.tanggal,
        p.keterangan,
        p.status,
        p.dibuat_pada,
        n.nama AS nama_nasabah,
        n.kode_nasabah,
        s.nama_sampah,
        s.harga_per_kg
    FROM pengajuan_setoran p
    LEFT JOIN nasabah n ON p.id_nasabah = n.id_nasabah
    LEFT JOIN jenis_sampah s ON p.id_sampah = s.id_sampah
    ORDER BY
        CASE WHEN p.status = 'Menunggu' THEN 0 ELSE 1 END,
        p.id_pengajuan DESC
";

$query = mysqli_query($koneksi, $sql);

if (!$query) {
    die("Gagal mengambil data: " . e(mysqli_error($koneksi)));
}

$daftar = [];
$total = 0;
$menunggu = 0;
$disetujui = 0;
$ditolak = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $daftar[] = $row;
    $total++;

    if ($row['status'] === 'Menunggu') {
        $menunggu++;
    } elseif ($row['status'] === 'Disetujui') {
        $disetujui++;
    } elseif ($row['status'] === 'Ditolak') {
        $ditolak++;
    }
}

$filter = $_GET['status'] ?? 'Semua';

if (!in_array($filter, ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'], true)) {
    $filter = 'Semua';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengajuan Setoran - Pengepul</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --hijau: #198754;
            --hijau-gelap: #126b40;
            --latar: #f2f8f4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--latar);
            font-family: Arial, sans-serif;
            color: #243247;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 24px 16px;
            color: white;
            background: linear-gradient(160deg, #198754, #126b40);
        }

        .brand {
            text-align: center;
            padding: 5px 0 25px;
            border-bottom: 1px solid #ffffff45;
            margin-bottom: 22px;
        }

        .brand-icon {
            width: 62px;
            height: 62px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            border-radius: 50%;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .brand h5 {
            font-weight: bold;
            margin: 0;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .menu-link:hover,
        .menu-link.active {
            background: #ffffff25;
            color: white;
        }

        .menu-link i {
            font-size: 19px;
        }

        .main {
            margin-left: 250px;
            padding: 28px;
        }

        .topbar, .panel, .stat {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px #183b5c0b;
        }

        .topbar {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 24px;
        }

        .topbar h4 {
            font-weight: bold;
            margin: 0 0 5px;
        }

        .topbar p {
            margin: 0;
            color: #718096;
        }

        .avatar {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: var(--hijau);
            border-radius: 50%;
            font-size: 22px;
        }

        .stat {
            padding: 20px;
            height: 100%;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 21px;
            margin-bottom: 12px;
        }

        .stat h3 {
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 5px;
        }

        .stat p {
            color: #718096;
            margin: 0;
        }

        .blue {
            background: #e6f1ff;
            color: #145da0;
        }

        .orange {
            background: #fff2dc;
            color: #9a5a00;
        }

        .green {
            background: #e5f8ed;
            color: #16834b;
        }

        .red {
            background: #ffebee;
            color: #d92536;
        }

        .panel {
            padding: 22px;
            margin-top: 24px;
        }

        .panel h5 {
            font-weight: bold;
        }

        .table thead th {
            background: #e7f4eb;
            color: #24563b;
            white-space: nowrap;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #fff2dc;
            color: #9a5a00;
        }

        .status-disetujui {
            background: #e5f8ed;
            color: #16834b;
        }

        .status-ditolak {
            background: #ffebee;
            color: #d92536;
        }

        .btn-primary {
            background: var(--hijau);
            border-color: var(--hijau);
        }

        .footer {
            text-align: center;
            color: #8290a3;
            font-size: 13px;
            padding: 24px 0 5px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 75px;
                padding: 20px 10px;
            }

            .brand h5,
            .brand small,
            .menu-text {
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

            .main {
                margin-left: 75px;
                padding: 14px;
            }

            .topbar {
                padding: 15px;
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
        <small>Portal Pengepul</small>
    </div>

    <a href="portal.php" class="menu-link">
        <i class="bi bi-speedometer2"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="pengajuan_pengepul.php" class="menu-link active">
        <i class="bi bi-inbox-fill"></i>
        <span class="menu-text">Pengajuan Setoran</span>
    </a>

    <a href="jenis_sampah_pengepul.php" class="menu-link">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Jenis Sampah</span>
    </a>

    <a href="logout.php" class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-right"></i>
        <span class="menu-text">Logout</span>
    </a>
</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h4>Pengajuan Setoran Sampah</h4>
            <p>Lihat pengajuan setoran siswa dan status pemeriksaannya.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="text-end">
                <strong><?= e($nama) ?></strong>
                <div class="small text-secondary">Pengepul</div>
            </div>
            <div class="avatar">
                <i class="bi bi-person-fill"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon blue">
                    <i class="bi bi-inbox-fill"></i>
                </div>
                <h3><?= number_format($total) ?></h3>
                <p>Total Pengajuan</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon orange">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <h3><?= number_format($menunggu) ?></h3>
                <p>Menunggu</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3><?= number_format($disetujui) ?></h3>
                <p>Disetujui</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon red">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <h3><?= number_format($ditolak) ?></h3>
                <p>Ditolak</p>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h5 class="mb-1">Daftar Pengajuan</h5>
                <div class="text-secondary small">
                    Status persetujuan dikelola oleh admin.
                </div>
            </div>

            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select"
                        onchange="this.form.submit()">
                    <?php foreach (['Semua', 'Menunggu', 'Disetujui', 'Ditolak'] as $opsi): ?>
                        <option value="<?= e($opsi) ?>"
                            <?= $filter === $opsi ? 'selected' : '' ?>>
                            <?= e($opsi) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Siswa/Nasabah</th>
                        <th>Jenis Sampah</th>
                        <th>Berat</th>
                        <th>Perkiraan Nilai</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $nomor = 0;

                foreach ($daftar as $row):
                    if ($filter !== 'Semua' && $row['status'] !== $filter) {
                        continue;
                    }

                    $nomor++;
                    $nilai = (float)$row['berat'] *
                             (float)($row['harga_per_kg'] ?? 0);

                    $kelasStatus = 'status-menunggu';

                    if ($row['status'] === 'Disetujui') {
                        $kelasStatus = 'status-disetujui';
                    } elseif ($row['status'] === 'Ditolak') {
                        $kelasStatus = 'status-ditolak';
                    }
                ?>
                    <tr>
                        <td><?= $nomor ?></td>

                        <td>
                            <strong>
                                <?= e($row['nama_nasabah'] ?: 'Nasabah tidak ditemukan') ?>
                            </strong>
                            <div class="small text-secondary">
                                <?= e($row['kode_nasabah'] ?? '-') ?>
                            </div>
                        </td>

                        <td>
                            <?= e($row['nama_sampah'] ?: 'Jenis tidak ditemukan') ?>
                        </td>

                        <td>
                            <?= number_format((float)$row['berat'], 2, ',', '.') ?> kg
                        </td>

                        <td>
                            Rp <?= number_format($nilai, 0, ',', '.') ?>
                        </td>

                        <td><?= e($row['tanggal']) ?></td>

                        <td><?= e($row['keterangan'] ?: '-') ?></td>

                        <td>
                            <span class="status <?= $kelasStatus ?>">
                                <?= e($row['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($nomor === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Belum ada pengajuan untuk status ini.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-success mb-0">
            <i class="bi bi-info-circle-fill me-2"></i>
            Pengepul hanya dapat melihat pengajuan. Persetujuan dan penolakan
            dilakukan melalui halaman admin.
        </div>
    </section>

    <div class="footer">
        &copy; <?= date('Y') ?> Bank Sampah | Portal Pengepul
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```