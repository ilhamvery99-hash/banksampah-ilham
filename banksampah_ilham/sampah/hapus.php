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

$id = $_GET['id'] ?? '';

if ($id == '') {
    header("Location: index.php");
    exit();
}

$id = mysqli_real_escape_string(
    $koneksi,
    $id
);

$query = mysqli_query(
    $koneksi,
    "DELETE FROM jenis_sampah
     WHERE id_sampah = '$id'"
);

if ($query) {

    header("Location: index.php");
    exit();

} else {

    die(
        "Gagal menghapus data: "
        . mysqli_error($koneksi)
    );

}

?>