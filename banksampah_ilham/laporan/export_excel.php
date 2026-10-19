<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

/* ==============================
   FILTER TANGGAL
============================== */

$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

$whereTransaksi = "";
$wherePenarikan = "";

if ($tanggal_mulai != '' && $tanggal_akhir != '') {

    $tanggal_mulai = mysqli_real_escape_string(
        $koneksi,
        $tanggal_mulai
    );

    $tanggal_akhir = mysqli_real_escape_string(
        $koneksi,
        $tanggal_akhir
    );

    $whereTransaksi = "
        WHERE DATE(transaksi.tanggal)
        BETWEEN '$tanggal_mulai'
        AND '$tanggal_akhir'
    ";

    $wherePenarikan = "
        WHERE DATE(penarikan.tanggal)
        BETWEEN '$tanggal_mulai'
        AND '$tanggal_akhir'
    ";
}


/* ==============================
   DATA SETORAN
============================== */

$queryTransaksi = mysqli_query($koneksi, "
    SELECT
        transaksi.tanggal,
        nasabah.kode_nasabah,
        nasabah.nama,
        jenis_sampah.nama_sampah,
        transaksi.berat,
        transaksi.harga_per_kg,
        transaksi.total
    FROM transaksi

    INNER JOIN nasabah
        ON transaksi.id_nasabah = nasabah.id_nasabah

    INNER JOIN jenis_sampah
        ON transaksi.id_sampah = jenis_sampah.id_sampah

    $whereTransaksi

    ORDER BY transaksi.tanggal DESC
");

if (!$queryTransaksi) {
    die("Query transaksi gagal: " . mysqli_error($koneksi));
}


/* ==============================
   DATA PENARIKAN
============================== */

$queryPenarikan = mysqli_query($koneksi, "
    SELECT
        penarikan.tanggal,
        nasabah.kode_nasabah,
        nasabah.nama,
        penarikan.jumlah
    FROM penarikan

    INNER JOIN nasabah
        ON penarikan.id_nasabah = nasabah.id_nasabah

    $wherePenarikan

    ORDER BY penarikan.tanggal DESC
");

if (!$queryPenarikan) {
    die("Query penarikan gagal: " . mysqli_error($koneksi));
}


/* ==============================
   NAMA FILE
============================== */

$namaFile = "laporan_bank_sampah";

if ($tanggal_mulai != '' && $tanggal_akhir != '') {
    $namaFile .= "_" . $tanggal_mulai . "_sampai_" . $tanggal_akhir;
}

$namaFile .= ".xls";


/* ==============================
   HEADER EXCEL
============================== */

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$namaFile\"");
header("Pragma: no-cache");
header("Expires: 0");

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<title>Laporan Bank Sampah</title>

</head>

<body>

<h2>LAPORAN BANK SAMPAH</h2>

<?php if ($tanggal_mulai != '' && $tanggal_akhir != ''): ?>

<p>
    Periode:
    <?= htmlspecialchars($tanggal_mulai) ?>
    sampai
    <?= htmlspecialchars($tanggal_akhir) ?>
</p>

<?php else: ?>

<p>
    Periode: Semua Data
</p>

<?php endif; ?>


<br>


<h3>DATA SETORAN</h3>

<table border="1">

    <tr>

        <th>No</th>

        <th>Tanggal</th>

        <th>Kode Nasabah</th>

        <th>Nama Nasabah</th>

        <th>Jenis Sampah</th>

        <th>Berat (Kg)</th>

        <th>Harga/Kg</th>

        <th>Total</th>

    </tr>

    <?php

    $no = 1;

    $totalBerat = 0;
    $totalSetoran = 0;

    while ($data = mysqli_fetch_assoc($queryTransaksi)):

        $totalBerat += $data['berat'];
        $totalSetoran += $data['total'];

    ?>

    <tr>

        <td>
            <?= $no++ ?>
        </td>

        <td>
            <?= $data['tanggal'] ?>
        </td>

        <td>
            <?= htmlspecialchars($data['kode_nasabah']) ?>
        </td>

        <td>
            <?= htmlspecialchars($data['nama']) ?>
        </td>

        <td>
            <?= htmlspecialchars($data['nama_sampah']) ?>
        </td>

        <td>
            <?= $data['berat'] ?>
        </td>

        <td>
            <?= $data['harga_per_kg'] ?>
        </td>

        <td>
            <?= $data['total'] ?>
        </td>

    </tr>

    <?php endwhile; ?>

    <tr>

        <td colspan="5">
            <strong>TOTAL</strong>
        </td>

        <td>
            <strong>
                <?= $totalBerat ?>
            </strong>
        </td>

        <td>
            -
        </td>

        <td>
            <strong>
                <?= $totalSetoran ?>
            </strong>
        </td>

    </tr>

</table>


<br>
<br>


<h3>DATA PENARIKAN</h3>

<table border="1">

    <tr>

        <th>No</th>

        <th>Tanggal</th>

        <th>Kode Nasabah</th>

        <th>Nama Nasabah</th>

        <th>Jumlah Penarikan</th>

    </tr>

    <?php

    $no = 1;

    $totalPenarikan = 0;

    while ($data = mysqli_fetch_assoc($queryPenarikan)):

        $totalPenarikan += $data['jumlah'];

    ?>

    <tr>

        <td>
            <?= $no++ ?>
        </td>

        <td>
            <?= $data['tanggal'] ?>
        </td>

        <td>
            <?= htmlspecialchars($data['kode_nasabah']) ?>
        </td>

        <td>
            <?= htmlspecialchars($data['nama']) ?>
        </td>

        <td>
            <?= $data['jumlah'] ?>
        </td>

    </tr>

    <?php endwhile; ?>

    <tr>

        <td colspan="4">
            <strong>TOTAL PENARIKAN</strong>
        </td>

        <td>
            <strong>
                <?= $totalPenarikan ?>
            </strong>
        </td>

    </tr>

</table>


<br>
<br>


<h3>RINGKASAN</h3>

<table border="1">

    <tr>
        <td>Total Berat Setoran</td>
        <td><?= $totalBerat ?> Kg</td>
    </tr>

    <tr>
        <td>Total Nilai Setoran</td>
        <td>Rp <?= $totalSetoran ?></td>
    </tr>

    <tr>
        <td>Total Penarikan</td>
        <td>Rp <?= $totalPenarikan ?></td>
    </tr>

    <tr>
        <td>Saldo Bersih Periode</td>
        <td>
            Rp <?= $totalSetoran - $totalPenarikan ?>
        </td>
    </tr>

</table>

</body>

</html>