```php
<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'admin') {
    if ($role === 'siswa') {
        header("Location: dashboard_siswa.php");
    } elseif ($role === 'guru') {
        header("Location: dashboard_guru.php");
    } elseif ($role === 'pengepul') {
        header("Location: portal.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

$namaAdmin = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin';
$pesan = '';
$tipePesan = 'success';

function e($nilai) {
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

// PROSES PERSETUJUAN ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id_pengajuan', FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';

    if (!$id || !in_array($aksi, ['Disetujui', 'Ditolak'], true)) {
        $pesan = 'Data pengajuan tidak valid.';
        $tipePesan = 'danger';
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Kunci pengajuan agar tidak diproses dua kali.
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT id_pengajuan, id_nasabah, id_sampah, berat, status
                 FROM pengajuan_setoran
                 WHERE id_pengajuan = ?
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception("Gagal memeriksa pengajuan.");
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $hasil = mysqli_stmt_get_result($stmt);
            $pengajuan = mysqli_fetch_assoc($hasil);
            mysqli_stmt_close($stmt);

            if (!$pengajuan) {
                throw new Exception("Pengajuan tidak ditemukan.");
            }

            if ($pengajuan['status'] !== 'Menunggu') {
                throw new Exception("Pengajuan ini sudah diproses.");
            }

            // Jika disetujui, hitung nilai dan tambahkan saldo.
            if ($aksi === 'Disetujui') {
                $idNasabah = (int)$pengajuan['id_nasabah'];
                $idSampah = (int)$pengajuan['id_sampah'];
                $berat = (float)$pengajuan['berat'];

                if ($berat <= 0) {
                    throw new Exception("Berat sampah tidak valid.");
                }

                // Pastikan nasabah tersedia.
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT id_nasabah
                     FROM nasabah
                     WHERE id_nasabah = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param($stmt, "i", $idNasabah);
                mysqli_stmt_execute($stmt);
                $hasilNasabah = mysqli_stmt_get_result($stmt);
                $nasabah = mysqli_fetch_assoc($hasilNasabah);
                mysqli_stmt_close($stmt);

                if (!$nasabah) {
                    throw new Exception("Data nasabah tidak ditemukan.");
                }

                // Ambil harga sampah.
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT harga_per_kg
                     FROM jenis_sampah
                     WHERE id_sampah = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $idSampah);
                mysqli_stmt_execute($stmt);
                $hasilSampah = mysqli_stmt_get_result($stmt);
                $sampah = mysqli_fetch_assoc($hasilSampah);
                mysqli_stmt_close($stmt);

                if (!$sampah) {
                    throw new Exception("Jenis sampah tidak ditemukan.");
                }

                $harga = (float)$sampah['harga_per_kg'];
                $totalNilai = (int)round($berat * $harga);

                if ($totalNilai <= 0) {
                    throw new Exception("Nilai setoran tidak valid.");
                }

                // Saldo bertambah hanya ketika admin menyetujui.
                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE nasabah
                     SET saldo = saldo + ?
                     WHERE id_nasabah = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ii",
                    $totalNilai,
                    $idNasabah
                );

                if (!mysqli_stmt_execute($stmt) ||
                    mysqli_stmt_affected_rows($stmt) !== 1) {
                    mysqli_stmt_close($stmt);
                    throw new Exception("Saldo nasabah gagal diperbarui.");
                }

                mysqli_stmt_close($stmt);
            }

            // Ubah status pengajuan.
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE pengajuan_setoran
                 SET status = ?
                 WHERE id_pengajuan = ?
                   AND status = 'Menunggu'"
            );

            mysqli_stmt_bind_param($stmt, "si", $aksi, $id);

            if (!mysqli_stmt_execute($stmt) ||
                mysqli_stmt_affected_rows($stmt) !== 1) {
                mysqli_stmt_close($stmt);
                throw new Exception("Status pengajuan gagal diperbarui.");
            }

            mysqli_stmt_close($stmt);
            mysqli_commit($koneksi);

            $pesan = $aksi === 'Disetujui'
                ? "Pengajuan berhasil disetujui dan saldo nasabah ditambahkan."
                : "Pengajuan berhasil ditolak.";

            $tipePesan = 'success';

        } catch (Throwable $err) {
            mysqli_rollback($koneksi);
            $pesan = $err->getMessage();
            $tipePesan = 'danger';
        }
    }
}

// AMBIL DATA DARI TABEL YANG SAMA DENGAN PENGEPUL
$sql = "
    SELECT
        p.id_pengajuan,
        p.id_nasabah,
        p.id_sampah,
        p.berat,
        p.tanggal,
        p.keterangan,
        p.status,
        n.nama AS nama_nasabah,
        n.kode_nasabah,
        s.nama_sampah,
        s.harga_per_kg
    FROM pengajuan_setoran p
    LEFT JOIN nasabah n
        ON p.id_nasabah = n.id_nasabah
    LEFT JOIN jenis_sampah s
        ON p.id_sampah = s.id_sampah
    ORDER BY
        CASE WHEN p.status = 'Menunggu' THEN 0 ELSE 1 END,
        p.id_pengajuan DESC
";

$query = mysqli_query($koneksi, $sql);

if (!$query) {
    die("Gagal mengambil pengajuan: " . e(mysqli_error($koneksi)));
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
    <title>Persetujuan Pengajuan - Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
    :root {
        --spidey-red: #e62429;
        --spidey-red-dark: #b5121b;
        --spidey-blue: #0866c6;
        --spidey-blue-dark: #06478b;
        --spidey-white: #ffffff;
        --spidey-bg: #eef4fb;
        --spidey-text: #172b4d;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background:
            linear-gradient(135deg, #eef4fb 25%, transparent 25%) -20px 0 / 40px 40px,
            linear-gradient(225deg, #f7faff 25%, transparent 25%) -20px 0 / 40px 40px,
            var(--spidey-bg);
        font-family: Arial, sans-serif;
        color: var(--spidey-text);
    }

    /* SIDEBAR */
    .sidebar {
        width: 245px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        padding: 24px 16px;
        color: white;
        background: linear-gradient(
            160deg,
            var(--spidey-blue),
            var(--spidey-blue-dark)
        );
        border-right: 5px solid var(--spidey-red);
        overflow-y: auto;
        z-index: 1000;
    }

    .brand {
        text-align: center;
        padding-bottom: 24px;
        margin-bottom: 22px;
        border-bottom: 2px solid #ffffff50;
    }

    .brand-icon {
        width: 65px;
        height: 65px;
        border: 3px solid white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        margin-bottom: 10px;
        background: var(--spidey-red);
        box-shadow: 0 0 0 5px #ffffff25;
    }

    .brand h5 {
        font-weight: 900;
        letter-spacing: 1px;
        margin: 0;
    }

    .brand small {
        color: #e2efff;
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
        border: 1px solid transparent;
        transition: .2s;
    }

    .menu-link:hover,
    .menu-link.active {
        background: var(--spidey-red);
        color: white;
        border-color: white;
        transform: translateX(3px);
        box-shadow: 0 4px 12px #001c4435;
    }

    .menu-link i {
        font-size: 19px;
    }

    /* KONTEN UTAMA */
    .main {
        margin-left: 245px;
        padding: 28px;
    }

    .topbar,
    .panel,
    .stat {
        background: var(--spidey-white);
        border-radius: 16px;
        box-shadow: 0 5px 20px #12345b12;
        border: 1px solid #dce6f2;
    }

    .topbar {
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        border-top: 5px solid var(--spidey-red);
        border-left: 5px solid var(--spidey-blue);
    }

    .topbar h4 {
        font-weight: 900;
        color: var(--spidey-blue-dark);
        margin: 0 0 5px;
    }

    .topbar p {
        margin: 0;
        color: #687991;
    }

    .avatar {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        background: var(--spidey-red);
        border: 3px solid #ffdfe0;
        border-radius: 50%;
        font-size: 22px;
    }

    /* KARTU STATISTIK */
    .stat {
        padding: 20px;
        height: 100%;
        border-bottom: 4px solid var(--spidey-blue);
        transition: .2s;
    }

    .stat:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px #12345b20;
    }

    .stat-icon {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 21px;
        margin-bottom: 12px;
    }

    .stat h3 {
        font-size: 27px;
        font-weight: 900;
        color: var(--spidey-blue-dark);
        margin: 0 0 5px;
    }

    .stat p {
        color: #687991;
        margin: 0;
    }

    .blue {
        background: #e1efff;
        color: var(--spidey-blue);
    }

    .orange {
        background: #fff0e8;
        color: #d95b18;
    }

    .green {
        background: #e2f7ec;
        color: #16834b;
    }

    .red {
        background: #ffe5e7;
        color: var(--spidey-red);
    }

    /* TABEL PENGAJUAN */
    .panel {
        padding: 22px;
        margin-top: 24px;
        border-top: 4px solid var(--spidey-blue);
    }

    .panel h5 {
        font-weight: 900;
        color: var(--spidey-blue-dark);
    }

    .form-select {
        border: 1px solid #c9d8ea;
        border-radius: 9px;
    }

    .form-select:focus {
        border-color: var(--spidey-blue);
        box-shadow: 0 0 0 .2rem #0866c625;
    }

    .table {
        --bs-table-hover-bg: #f0f6ff;
    }

    .table thead th {
        background: var(--spidey-blue);
        color: white;
        white-space: nowrap;
        font-size: 13px;
        padding: 14px 12px;
        border-color: #ffffff30;
    }

    .table td {
        vertical-align: middle;
        font-size: 14px;
        padding: 13px 12px;
    }

    .table tbody tr {
        border-bottom: 1px solid #e4ebf4;
    }

    .status {
        display: inline-block;
        padding: 6px 11px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: bold;
        white-space: nowrap;
    }

    .status-menunggu {
        background: #fff0d5;
        color: #8b5500;
    }

    .status-disetujui {
        background: #dff7e8;
        color: #13753f;
    }

    .status-ditolak {
        background: #ffe5e7;
        color: var(--spidey-red-dark);
    }

    /* TOMBOL */
    .btn-success {
        background: var(--spidey-blue);
        border-color: var(--spidey-blue);
    }

    .btn-success:hover {
        background: var(--spidey-blue-dark);
        border-color: var(--spidey-blue-dark);
    }

    .btn-danger {
        background: var(--spidey-red);
        border-color: var(--spidey-red);
    }

    .btn-danger:hover {
        background: var(--spidey-red-dark);
        border-color: var(--spidey-red-dark);
    }

    .btn {
        border-radius: 8px;
        font-weight: bold;
    }

    .alert-success {
        background: #e9f3ff;
        border-color: #bdd9ff;
        color: var(--spidey-blue-dark);
    }

    .footer {
        text-align: center;
        color: #718198;
        font-size: 13px;
        padding: 24px 0 5px;
    }

    /* TAMPILAN HP */
    @media (max-width: 768px) {
        .sidebar {
            width: 72px;
            padding: 20px 8px;
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
            margin-left: 72px;
            padding: 14px;
        }

        .topbar {
            padding: 15px;
        }

        .topbar h4 {
            font-size: 18px;
        }

        .panel {
            padding: 14px;
        }
    }
    </style>
</head>

<body>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-recycle"></i></div>
        <h5>BANK SAMPAH</h5>
        <small>Panel Administrator</small>
    </div>

    <a href="dashboard.php" class="menu-link">
        <i class="bi bi-speedometer2"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="persetujuan.php" class="menu-link active">
        <i class="bi bi-clipboard-check"></i>
        <span class="menu-text">Persetujuan</span>
    </a>

    <a href="nasabah/index.php" class="menu-link">
        <i class="bi bi-people"></i>
        <span class="menu-text">Data Nasabah</span>
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
            <h4>Persetujuan Pengajuan Setoran</h4>
            <p>Periksa pengajuan siswa dan proses persetujuannya.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="text-end">
                <strong><?= e($namaAdmin) ?></strong>
                <div class="small text-secondary">Administrator</div>
            </div>
            <div class="avatar"><i class="bi bi-person-fill"></i></div>
        </div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= e($tipePesan) ?> alert-dismissible fade show">
            <?= e($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon blue"><i class="bi bi-inbox-fill"></i></div>
                <h3><?= number_format($total) ?></h3>
                <p>Total Pengajuan</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon orange"><i class="bi bi-clock-fill"></i></div>
                <h3><?= number_format($menunggu) ?></h3>
                <p>Menunggu</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <h3><?= number_format($disetujui) ?></h3>
                <p>Disetujui</p>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat">
                <div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <h3><?= number_format($ditolak) ?></h3>
                <p>Ditolak</p>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h5 class="mb-1">Daftar Pengajuan Siswa</h5>
                <div class="text-secondary small">
                    Pengajuan dari tabel pengajuan_setoran.
                </div>
            </div>

            <form method="GET">
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
                        <th>Aksi</th>
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
                            <strong><?= e($row['nama_nasabah'] ?: 'Nasabah tidak ditemukan') ?></strong>
                            <div class="small text-secondary">
                                <?= e($row['kode_nasabah'] ?? '-') ?>
                            </div>
                        </td>
                        <td><?= e($row['nama_sampah'] ?: 'Jenis tidak ditemukan') ?></td>
                        <td><?= number_format((float)$row['berat'], 2, ',', '.') ?> kg</td>
                        <td>Rp <?= number_format($nilai, 0, ',', '.') ?></td>
                        <td><?= e($row['tanggal']) ?></td>
                        <td><?= e($row['keterangan'] ?: '-') ?></td>
                        <td>
                            <span class="status <?= $kelasStatus ?>">
                                <?= e($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Menunggu'): ?>
                                <div class="d-flex gap-1">
                                    <form method="POST"
                                          onsubmit="return confirm('Setujui pengajuan dan tambahkan saldo nasabah?')">
                                        <input type="hidden" name="id_pengajuan"
                                               value="<?= (int)$row['id_pengajuan'] ?>">
                                        <input type="hidden" name="aksi"
                                               value="Disetujui">
                                        <button type="submit"
                                                class="btn btn-success btn-sm"
                                                title="Setujui">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>

                                    <form method="POST"
                                          onsubmit="return confirm('Tolak pengajuan ini?')">
                                        <input type="hidden" name="id_pengajuan"
                                               value="<?= (int)$row['id_pengajuan'] ?>">
                                        <input type="hidden" name="aksi"
                                               value="Ditolak">
                                        <button type="submit"
                                                class="btn btn-danger btn-sm"
                                                title="Tolak">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary small">Sudah diproses</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($nomor === 0): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">
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
            Saldo nasabah hanya bertambah setelah admin menyetujui pengajuan.
        </div>
    </section>

    <div class="footer">
        &copy; <?= date('Y') ?> Bank Sampah | Panel Administrator
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```