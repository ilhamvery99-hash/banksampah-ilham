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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tambah.php");
    exit();
}

$nama_sampah = trim($_POST['nama_sampah'] ?? '');
$harga_per_kg = $_POST['harga_per_kg'] ?? '';

if (
    $nama_sampah === '' ||
    !is_numeric($harga_per_kg) ||
    (float)$harga_per_kg <= 0
) {
    die("Nama sampah atau harga tidak valid. <a href='tambah.php'>Kembali</a>");
}

$stmt = mysqli_prepare(
    $koneksi,
    "INSERT INTO jenis_sampah (nama_sampah, harga_per_kg) VALUES (?, ?)"
);

mysqli_stmt_bind_param($stmt, "sd", $nama_sampah, $harga_per_kg);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: index.php?pesan=tambah_sukses");
    exit();
}

$error = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

die("Gagal menyimpan data: " . htmlspecialchars($error) .
    " <a href='tambah.php'>Kembali</a>");
?>