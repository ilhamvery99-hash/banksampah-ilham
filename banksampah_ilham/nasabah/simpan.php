<?php

session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tambah.php");
    exit();
}

$kode_nasabah = trim($_POST['kode_nasabah'] ?? '');
$nama         = trim($_POST['nama'] ?? '');
$alamat       = trim($_POST['alamat'] ?? '');
$no_hp        = trim($_POST['no_hp'] ?? '');

if ($kode_nasabah === '' || $nama === '') {
    header("Location: tambah.php?error=Data nasabah belum lengkap");
    exit();
}

$kode_nasabah = mysqli_real_escape_string($koneksi, $kode_nasabah);
$nama         = mysqli_real_escape_string($koneksi, $nama);
$alamat       = mysqli_real_escape_string($koneksi, $alamat);
$no_hp        = mysqli_real_escape_string($koneksi, $no_hp);

$cek = mysqli_query(
    $koneksi,
    "SELECT id_nasabah
     FROM nasabah
     WHERE kode_nasabah='$kode_nasabah'"
);

if (mysqli_num_rows($cek) > 0) {
    header("Location: tambah.php?error=Kode nasabah sudah digunakan");
    exit();
}

$query = mysqli_query(
    $koneksi,
    "INSERT INTO nasabah
    (kode_nasabah, nama, alamat, no_hp, saldo)
    VALUES
    ('$kode_nasabah', '$nama', '$alamat', '$no_hp', 0)"
);

if ($query) {

    header("Location: index.php?success=Nasabah berhasil ditambahkan");
    exit();

} else {

    header(
        "Location: tambah.php?error=" .
        urlencode(mysqli_error($koneksi))
    );

    exit();
}

?>