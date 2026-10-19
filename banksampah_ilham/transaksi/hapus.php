<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

// Ambil ID transaksi
$id_transaksi = $_GET['id'] ?? '';

if ($id_transaksi === '') {
    die("ID transaksi tidak ditemukan.");
}

$id_transaksi = mysqli_real_escape_string($koneksi, $id_transaksi);


// ==================================================
// AMBIL DATA TRANSAKSI
// ==================================================

$query = mysqli_query($koneksi, "
    SELECT id_nasabah, total
    FROM transaksi
    WHERE id_transaksi = '$id_transaksi'
");

if (!$query) {
    die("Query transaksi gagal: " . mysqli_error($koneksi));
}

$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data transaksi tidak ditemukan.");
}

$id_nasabah = $data['id_nasabah'];
$total = (float)$data['total'];


// ==================================================
// CEK SALDO NASABAH
// ==================================================

$querySaldo = mysqli_query($koneksi, "
    SELECT saldo
    FROM nasabah
    WHERE id_nasabah = '$id_nasabah'
");

if (!$querySaldo) {
    die("Query saldo gagal: " . mysqli_error($koneksi));
}

$dataNasabah = mysqli_fetch_assoc($querySaldo);

if (!$dataNasabah) {
    die("Data nasabah tidak ditemukan.");
}

$saldo = (float)$dataNasabah['saldo'];


// Jangan sampai saldo menjadi minus
if ($saldo < $total) {
    die("Transaksi tidak dapat dihapus karena saldo nasabah saat ini lebih kecil dari nilai transaksi.");
}


// ==================================================
// MULAI TRANSAKSI DATABASE
// ==================================================

mysqli_begin_transaction($koneksi);

try {

    // Kurangi saldo nasabah
    $updateSaldo = mysqli_query($koneksi, "
        UPDATE nasabah
        SET saldo = saldo - $total
        WHERE id_nasabah = '$id_nasabah'
    ");

    if (!$updateSaldo) {
        throw new Exception(
            "Gagal mengurangi saldo: " . mysqli_error($koneksi)
        );
    }


    // Hapus transaksi
    $hapus = mysqli_query($koneksi, "
        DELETE FROM transaksi
        WHERE id_transaksi = '$id_transaksi'
    ");

    if (!$hapus) {
        throw new Exception(
            "Gagal menghapus transaksi: " . mysqli_error($koneksi)
        );
    }


    // Pastikan transaksi benar-benar terhapus
    if (mysqli_affected_rows($koneksi) < 1) {
        throw new Exception("Transaksi tidak ditemukan atau sudah dihapus.");
    }


    // Simpan semua perubahan
    mysqli_commit($koneksi);

    // Kembali ke halaman transaksi
    header("Location: index.php");
    exit();

} catch (Exception $e) {

    // Batalkan perubahan jika ada error
    mysqli_rollback($koneksi);

    die("Gagal menghapus transaksi: " . $e->getMessage());
}

?>