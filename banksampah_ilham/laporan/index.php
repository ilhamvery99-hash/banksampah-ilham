```php
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

$dashboard = match ($role) {
    'siswa' => '../dashboard_siswa.php',
    'guru' => '../dashboard_guru.php',
    'pengepul' => '../dashboard_pengepul.php',
    default => '../dashboard.php'
};

// Filter tanggal
$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

$tanggalValid = (
    $tanggal_mulai !== '' &&
    $tanggal_akhir !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir) &&
    strtotime($tanggal_mulai) !== false &&
    strtotime($tanggal_akhir) !== false &&
    $tanggal_mulai <= $tanggal_akhir
);

$whereTransaksi = "";
$wherePenarikan = "";

if ($tanggalValid) {
    $mulai = mysqli_real_escape_string($koneksi, $tanggal_mulai);
    $akhir = mysqli_real_escape_string($koneksi, $tanggal_akhir);

    $whereTransaksi = "
        WHERE transaksi.tanggal >= '$mulai 00:00:00'
        AND transaksi.tanggal <= '$akhir 23:59:59'
    ";

    $wherePenarikan = "
        WHERE penarikan.tanggal >= '$mulai 00:00:00'
        AND penarikan.tanggal <= '$akhir 23:59:59'
    ";
}

// Total nasabah
$queryNasabah = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS jumlah FROM nasabah"
);

$totalNasabah = mysqli_fetch_assoc($queryNasabah)['jumlah'] ?? 0;

// Statistik setoran sesuai periode
$queryTransaksi = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(transaksi.berat), 0) AS berat,
        COALESCE(SUM(transaksi.total), 0) AS total
    FROM transaksi
    $whereTransaksi
");

$statTransaksi = mysqli_fetch_assoc($queryTransaksi);

$totalSetoran = $statTransaksi['jumlah'] ?? 0;
$totalBerat = $statTransaksi['berat'] ?? 0;
$totalRp = $statTransaksi['total'] ?? 0;

// Statistik penarikan sesuai periode
$queryPenarikan = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(penarikan.jumlah), 0) AS total
    FROM penarikan
    $wherePenarikan
");

$statPenarikan = mysqli_fetch_assoc($queryPenarikan);

$totalPenarikan = $statPenarikan['jumlah'] ?? 0;
$totalRpTarik = $statPenarikan['total'] ?? 0;

// Saldo saat ini seluruh nasabah
$querySaldo = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(saldo), 0) AS saldo
    FROM nasabah
");

$totalSaldo = mysqli_fetch_assoc($querySaldo)['saldo'] ?? 0;

// Selisih nilai setoran dan penarikan selama periode
$saldoBersih = $totalRp - $totalRpTarik;

// Data setoran
$queryDataTransaksi = mysqli_query($koneksi, "
    SELECT
        transaksi.id_transaksi,
        transaksi.berat,
        transaksi.harga_per_kg,
        transaksi.total,
        transaksi.tanggal,
        nasabah.kode_nasabah,
        nasabah.nama,
        jenis_sampah.nama_sampah
    FROM transaksi
    INNER JOIN nasabah
        ON transaksi.id_nasabah = nasabah.id_nasabah
    INNER JOIN jenis_sampah
        ON transaksi.id_sampah = jenis_sampah.id_sampah
    $whereTransaksi
    ORDER BY transaksi.id_transaksi DESC
    LIMIT 50
");

if (!$queryDataTransaksi) {
    die("Query transaksi gagal: " . mysqli_error($koneksi));
}

// Data penarikan
$queryDataPenarikan = mysqli_query($koneksi, "
    SELECT
        penarikan.id_penarikan,
        penarikan.jumlah,
        penarikan.tanggal,
        nasabah.kode_nasabah,
        nasabah.nama
    FROM penarikan
    INNER JOIN nasabah
        ON penarikan.id_nasabah = nasabah.id_nasabah
    $wherePenarikan
    ORDER BY penarikan.id_penarikan DESC
    LIMIT 50
");

if (!$queryDataPenarikan) {
    die("Query penarikan gagal: " . mysqli_error($koneksi));
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Laporan Bank Sampah</title>

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

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    padding: 25px 15px;
    overflow-y: auto;
    background: linear-gradient(180deg, #c1121f, #780000);
    box-shadow: 4px 0 15px rgba(0,0,0,.12);
    z-index: 1000;
}

.logo {
    text-align: center;
    color: white;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 8px;
}

.logo-icon {
    display: block;
    font-size: 40px;
    margin-bottom: 8px;
}

.sidebar-subtitle {
    text-align: center;
    color: #ffe3e3;
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
    font-weight: 500;
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

/* KONTEN */
.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

.judul {
    background: white;
    padding: 23px 25px;
    border-radius: 16px;
    margin-bottom: 22px;
    border-left: 5px solid #c1121f;
    box-shadow: 0 5px 20px rgba(18,60,156,.07);
}

.judul h2 {
    margin: 0;
    color: #c1121f;
    font-size: 26px;
    font-weight: bold;
}

.judul p {
    margin-top: 7px;
}

/* TOMBOL */
.btn-cetak,
.btn-excel,
.btn-filter {
    border: none;
    padding: 11px 17px;
    border-radius: 9px;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    cursor: pointer;
    transition: .2s;
}

.btn-cetak {
    background: #c1121f;
    color: white;
}

.btn-cetak:hover {
    background: #97000b;
    color: white;
}

.btn-excel {
    background: #123c9c;
    color: white;
}

.btn-excel:hover {
    background: #0b2c76;
    color: white;
}

.btn-filter {
    background: #1877f2;
    color: white;
}

.btn-filter:hover {
    background: #125dcc;
}

/* FILTER */
.filter-box {
    background: white;
    padding: 22px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(18,60,156,.06);
}

.filter-box form {
    display: flex;
    align-items: end;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.filter-group label {
    font-size: 14px;
    font-weight: bold;
    color: #123c9c;
}

.filter-group input {
    min-width: 190px;
    padding: 10px 12px;
    border: 1px solid #d5dced;
    border-radius: 8px;
    outline: none;
}

.filter-group input:focus {
    border-color: #1877f2;
    box-shadow: 0 0 0 3px rgba(24,119,242,.12);
}

.btn-reset {
    background: #e9edf5;
    color: #263238;
    padding: 11px 17px;
    border-radius: 9px;
    text-decoration: none;
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
    background: white;
    padding: 21px;
    border-radius: 15px;
    border-top: 4px solid #1877f2;
    box-shadow: 0 5px 20px rgba(18,60,156,.06);
    min-width: 0;
}

.stat-card:nth-child(4n + 1) {
    border-top-color: #c1121f;
}

.stat-card:nth-child(4n + 2) {
    border-top-color: #1877f2;
}

.stat-card:nth-child(4n + 3) {
    border-top-color: #123c9c;
}

.stat-card:nth-child(4n) {
    border-top-color: #e63946;
}

.stat-card .icon {
    font-size: 28px;
    color: #c1121f;
    margin-bottom: 10px;
}

.stat-card .label {
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 7px;
}

.stat-card .value {
    color: #123c9c;
    font-size: 22px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

/* TABEL */
.report-card {
    background: white;
    padding: 22px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(18,60,156,.06);
}

.report-card h4 {
    color: #c1121f;
    font-size: 19px;
    font-weight: bold;
    margin-bottom: 18px;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background: #123c9c;
    color: white;
    padding: 13px 12px;
    text-align: left;
    white-space: nowrap;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #e8edf5;
    white-space: nowrap;
}

table tbody tr:hover {
    background: #f0f4ff;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #777;
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

    .stat-grid {
        grid-template-columns: 1fr;
    }

    .filter-box form {
        align-items: stretch;
    }

    .filter-group input {
        width: 100%;
        min-width: 0;
    }

    .judul h2 {
        font-size: 22px;
    }
}

/* CETAK */
@media print {
    body {
        background: white;
    }

    .sidebar,
    .filter-box,
    .btn-cetak,
    .btn-excel,
    .no-print {
        display: none !important;
    }

    .content {
        margin: 0 !important;
        padding: 5px !important;
    }

    .judul,
    .stat-card,
    .report-card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }

    .stat-grid {
        grid-template-columns: repeat(4, 1fr);
    }

    table {
        font-size: 10px;
    }

    table th {
        background: #123c9c !important;
        color: white !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">
        <span class="logo-icon">🕷️</span>
        BANK SAMPAH
    </div>
    <div class="sidebar-subtitle">SPIDER-MAN EDITION</div>

    <a href="<?= htmlspecialchars($dashboard) ?>">
        <i class="bi bi-house-door-fill"></i> Dashboard
    </a>

    <a href="../nasabah/index.php">
        <i class="bi bi-people-fill"></i> Nasabah
    </a>

    <a href="../sampah/index.php">
        <i class="bi bi-recycle"></i> Jenis Sampah
    </a>

    <a href="../transaksi/index.php">
        <i class="bi bi-box-seam-fill"></i> Setoran
    </a>

    <a href="../penarikan/index.php">
        <i class="bi bi-wallet-fill"></i> Penarikan
    </a>

    <a href="index.php" class="active">
        <i class="bi bi-bar-chart-fill"></i> Laporan
    </a>

    <a href="../logout.php" class="logout"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- KONTEN -->
<div class="content">

    <div class="judul">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="bi bi-bar-chart-fill"></i> Laporan Bank Sampah</h2>
                <p class="text-muted mb-0">
                    Ringkasan data dan aktivitas Bank Sampah
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button onclick="window.print()" class="btn-cetak">
                    <i class="bi bi-printer-fill"></i> Cetak
                </button>

                <a
                    href="export_excel.php?tanggal_mulai=<?= urlencode($tanggal_mulai) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>"
                    class="btn-excel"
                >
                    <i class="bi bi-file-earmark-excel-fill"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- FILTER TANGGAL -->
    <div class="filter-box">
        <form method="GET">
            <div class="filter-group">
                <label for="tanggal_mulai">Tanggal Mulai</label>
                <input
                    type="date"
                    id="tanggal_mulai"
                    name="tanggal_mulai"
                    value="<?= htmlspecialchars($tanggal_mulai) ?>"
                >
            </div>

            <div class="filter-group">
                <label for="tanggal_akhir">Tanggal Akhir</label>
                <input
                    type="date"
                    id="tanggal_akhir"
                    name="tanggal_akhir"
                    value="<?= htmlspecialchars($tanggal_akhir) ?>"
                >
            </div>

            <button type="submit" class="btn-filter">
                <i class="bi bi-search"></i> Tampilkan
            </button>

            <a href="index.php" class="btn-reset">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
        </form>
    </div>

    <?php if ($tanggal_mulai !== '' || $tanggal_akhir !== ''): ?>
        <?php if ($tanggalValid): ?>
            <div class="alert alert-primary">
                <i class="bi bi-calendar-event"></i>
                Menampilkan laporan dari
                <strong><?= date('d-m-Y', strtotime($tanggal_mulai)) ?></strong>
                sampai
                <strong><?= date('d-m-Y', strtotime($tanggal_akhir)) ?></strong>.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Tanggal tidak valid. Isi tanggal mulai dan akhir dengan benar,
                serta pastikan tanggal mulai tidak melewati tanggal akhir.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="stat-grid">

        <div class="stat-card">
            <div class="icon"><i class="bi bi-people-fill"></i></div>
            <div class="label">Total Nasabah</div>
            <div class="value"><?= number_format($totalNasabah, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-box-seam-fill"></i></div>
            <div class="label">Jumlah Setoran</div>
            <div class="value"><?= number_format($totalSetoran, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-speedometer2"></i></div>
            <div class="label">Total Berat</div>
            <div class="value"><?= number_format($totalBerat, 2, ',', '.') ?> kg</div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-cash-stack"></i></div>
            <div class="label">Nilai Setoran</div>
            <div class="value"><?= rupiah($totalRp) ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-wallet2"></i></div>
            <div class="label">Jumlah Penarikan</div>
            <div class="value"><?= number_format($totalPenarikan, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-cash-coin"></i></div>
            <div class="label">Total Penarikan</div>
            <div class="value"><?= rupiah($totalRpTarik) ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-bank"></i></div>
            <div class="label">Saldo Nasabah Saat Ini</div>
            <div class="value"><?= rupiah($totalSaldo) ?></div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="label">Selisih Setoran - Penarikan</div>
            <div class="value"><?= rupiah($saldoBersih) ?></div>
        </div>

    </div>

    <!-- TABEL SETORAN -->
    <div class="report-card">
        <h4><i class="bi bi-box-seam-fill"></i> Data Setoran</h4>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Kode Nasabah</th>
                        <th>Nama</th>
                        <th>Jenis Sampah</th>
                        <th>Berat</th>
                        <th>Harga/Kg</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; ?>

                <?php if (mysqli_num_rows($queryDataTransaksi) > 0): ?>
                    <?php while ($data = mysqli_fetch_assoc($queryDataTransaksi)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($data['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($data['kode_nasabah']) ?></td>
                            <td><?= htmlspecialchars($data['nama']) ?></td>
                            <td><?= htmlspecialchars($data['nama_sampah']) ?></td>
                            <td><?= number_format($data['berat'], 2, ',', '.') ?> kg</td>
                            <td><?= rupiah($data['harga_per_kg']) ?></td>
                            <td><strong><?= rupiah($data['total']) ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Belum ada data setoran.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted d-block mt-2">
            Maksimal 50 transaksi terbaru ditampilkan.
        </small>
    </div>

    <!-- TABEL PENARIKAN -->
    <div class="report-card">
        <h4><i class="bi bi-wallet-fill"></i> Data Penarikan</h4>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Kode Nasabah</th>
                        <th>Nama</th>
                        <th>Jumlah Penarikan</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; ?>

                <?php if (mysqli_num_rows($queryDataPenarikan) > 0): ?>
                    <?php while ($data = mysqli_fetch_assoc($queryDataPenarikan)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($data['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($data['kode_nasabah']) ?></td>
                            <td><?= htmlspecialchars($data['nama']) ?></td>
                            <td><strong><?= rupiah($data['jumlah']) ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Belum ada data penarikan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted d-block mt-2">
            Maksimal 50 transaksi penarikan terbaru ditampilkan.
        </small>
    </div>

    <div class="text-center text-muted small mb-3">
        &copy; <?= date('Y') ?> Bank Sampah — Spider-Man Edition
    </div>

</div>

</body>
</html>
```