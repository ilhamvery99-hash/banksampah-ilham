<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once "../koneksi.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

$id_sampah = $_POST['id_sampah'] ?? '';
$nama_sampah = $_POST['nama_sampah'] ?? '';
$harga_per_kg = $_POST['harga_per_kg'] ?? '';

if (
    $id_sampah == '' ||
    $nama_sampah == '' ||
    $harga_per_kg == ''
) {
    die("Data belum lengkap.");
}

$id_sampah = mysqli_real_escape_string(
    $koneksi,
    $id_sampah
);

$nama_sampah = mysqli_real_escape_string(
    $koneksi,
    $nama_sampah
);

$harga_per_kg = mysqli_real_escape_string(
    $koneksi,
    $harga_per_kg
);

$query = mysqli_query(
    $koneksi,
    "UPDATE jenis_sampah
     SET
        nama_sampah = '$nama_sampah',
        harga_per_kg = '$harga_per_kg'
     WHERE id_sampah = '$id_sampah'"
);

if ($query) {

    header("Location: index.php");
    exit();

} else {

    die(
        "Gagal mengupdate data: "
        . mysqli_error($koneksi)
    );

}

?>