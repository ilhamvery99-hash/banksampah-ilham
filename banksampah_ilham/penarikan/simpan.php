<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
$jumlah = (float)($_POST['jumlah'] ?? 0);


/* ==========================
   VALIDASI
========================== */

if ($id_nasabah <= 0) {
    header("Location: tambah.php?error=Nasabah belum dipilih");
    exit();
}

if ($jumlah <= 0) {
    header("Location: tambah.php?error=Jumlah penarikan harus lebih dari 0");
    exit();
}


/* ==========================
   CEK NASABAH DAN SALDO
========================== */

$queryNasabah = mysqli_query(
    $koneksi,
    "SELECT id_nasabah, nama, saldo
     FROM nasabah
     WHERE id_nasabah=$id_nasabah"
);

if (!$queryNasabah) {

    header(
        "Location: tambah.php?error=" .
        urlencode(mysqli_error($koneksi))
    );

    exit();
}

$nasabah = mysqli_fetch_assoc($queryNasabah);

if (!$nasabah) {

    header("Location: tambah.php?error=Nasabah tidak ditemukan");
    exit();

}

$saldo = (float)$nasabah['saldo'];


/* ==========================
   CEK SALDO
========================== */

if ($jumlah > $saldo) {

    $pesan = "Saldo tidak mencukupi. Saldo tersedia Rp " .
             number_format($saldo, 0, ',', '.') .
             ", sedangkan penarikan Rp " .
             number_format($jumlah, 0, ',', '.');

    header(
        "Location: tambah.php?error=" .
        urlencode($pesan)
    );

    exit();
}


/* ==========================
   TRANSAKSI DATABASE
========================== */

mysqli_begin_transaction($koneksi);

try {

    /* SIMPAN PENARIKAN */

    $insert = mysqli_query(
        $koneksi,
        "INSERT INTO penarikan
        (
            id_nasabah,
            jumlah
        )
        VALUES
        (
            $id_nasabah,
            $jumlah
        )"
    );

    if (!$insert) {

        throw new Exception(
            "Gagal menyimpan penarikan: " .
            mysqli_error($koneksi)
        );

    }


    /* KURANGI SALDO */

    $updateSaldo = mysqli_query(
        $koneksi,
        "UPDATE nasabah
         SET saldo = saldo - $jumlah
         WHERE id_nasabah=$id_nasabah
         AND saldo >= $jumlah"
    );

    if (!$updateSaldo) {

        throw new Exception(
            "Gagal mengurangi saldo: " .
            mysqli_error($koneksi)
        );

    }


    if (mysqli_affected_rows($koneksi) < 1) {

        throw new Exception(
            "Saldo tidak mencukupi atau data nasabah tidak ditemukan."
        );

    }


    /* BERHASIL */

    mysqli_commit($koneksi);

    header(
        "Location: index.php?success=" .
        urlencode(
            "Penarikan berhasil. Saldo " .
            $nasabah['nama'] .
            " berkurang Rp " .
            number_format($jumlah, 0, ',', '.')
        )
    );

    exit();


} catch (Exception $e) {

    mysqli_rollback($koneksi);

    header(
        "Location: tambah.php?error=" .
        urlencode($e->getMessage())
    );

    exit();

}

?>