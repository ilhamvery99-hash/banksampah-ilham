<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";


// ==================================================
// AMBIL ID PENARIKAN
// ==================================================

$id_penarikan = $_GET['id'] ?? '';

if ($id_penarikan === '') {
    die("ID penarikan tidak ditemukan.");
}

$id_penarikan = (int)$id_penarikan;

if ($id_penarikan <= 0) {
    die("ID penarikan tidak valid.");
}


// ==================================================
// AMBIL DATA PENARIKAN
// ==================================================

$query = mysqli_query($koneksi, "
    SELECT
        id_penarikan,
        id_nasabah,
        jumlah
    FROM penarikan
    WHERE id_penarikan = $id_penarikan
");

if (!$query) {
    die("Query penarikan gagal: " . mysqli_error($koneksi));
}

$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data penarikan tidak ditemukan.");
}

$id_nasabah = (int)$data['id_nasabah'];
$jumlah = (float)$data['jumlah'];


// ==================================================
// MULAI TRANSAKSI DATABASE
// ==================================================

mysqli_begin_transaction($koneksi);

try {

    // ------------------------------------------------
    // KEMBALIKAN JUMLAH KE SALDO NASABAH
    // ------------------------------------------------

    $updateSaldo = mysqli_query($koneksi, "
        UPDATE nasabah
        SET saldo = saldo + $jumlah
        WHERE id_nasabah = $id_nasabah
    ");

    if (!$updateSaldo) {
        throw new Exception(
            "Gagal mengembalikan saldo: " .
            mysqli_error($koneksi)
        );
    }


    // Pastikan nasabah ditemukan
    if (mysqli_affected_rows($koneksi) < 1) {
        throw new Exception(
            "Nasabah tidak ditemukan."
        );
    }


    // ------------------------------------------------
    // HAPUS DATA PENARIKAN
    // ------------------------------------------------

    $hapus = mysqli_query($koneksi, "
        DELETE FROM penarikan
        WHERE id_penarikan = $id_penarikan
    ");

    if (!$hapus) {
        throw new Exception(
            "Gagal menghapus penarikan: " .
            mysqli_error($koneksi)
        );
    }


    // Pastikan data benar-benar terhapus
    if (mysqli_affected_rows($koneksi) < 1) {
        throw new Exception(
            "Data penarikan tidak ditemukan atau sudah dihapus."
        );
    }


    // ------------------------------------------------
    // SIMPAN SEMUA PERUBAHAN
    // ------------------------------------------------

    mysqli_commit($koneksi);


    // Kembali ke halaman penarikan
    header("Location: index.php");
    exit();


} catch (Exception $e) {

    // Batalkan semua perubahan jika ada error
    mysqli_rollback($koneksi);

    die(
        "Gagal menghapus penarikan: " .
        $e->getMessage()
    );
}

?>