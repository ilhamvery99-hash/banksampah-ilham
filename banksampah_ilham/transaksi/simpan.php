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
$id_sampah  = (int)($_POST['id_sampah'] ?? 0);
$berat      = (float)($_POST['berat'] ?? 0);


/* ==========================
   VALIDASI
========================== */

if ($id_nasabah <= 0) {
    header("Location: tambah.php?error=Nasabah belum dipilih");
    exit();
}

if ($id_sampah <= 0) {
    header("Location: tambah.php?error=Jenis sampah belum dipilih");
    exit();
}

if ($berat <= 0) {
    header("Location: tambah.php?error=Berat harus lebih dari 0");
    exit();
}


/* ==========================
   CEK NASABAH
========================== */

$cekNasabah = mysqli_query(
    $koneksi,
    "SELECT id_nasabah
     FROM nasabah
     WHERE id_nasabah=$id_nasabah"
);

if (!$cekNasabah || mysqli_num_rows($cekNasabah) == 0) {

    header("Location: tambah.php?error=Nasabah tidak ditemukan");
    exit();

}


/* ==========================
   AMBIL HARGA SAMPAH
========================== */

$querySampah = mysqli_query(
    $koneksi,
    "SELECT nama_sampah, harga_per_kg
     FROM jenis_sampah
     WHERE id_sampah=$id_sampah"
);

if (!$querySampah) {

    header(
        "Location: tambah.php?error=" .
        urlencode(mysqli_error($koneksi))
    );

    exit();
}

$sampah = mysqli_fetch_assoc($querySampah);

if (!$sampah) {

    header("Location: tambah.php?error=Jenis sampah tidak ditemukan");
    exit();

}

$harga_per_kg = (float)$sampah['harga_per_kg'];


/* ==========================
   HITUNG TOTAL
========================== */

$total = $berat * $harga_per_kg;


/* ==========================
   TRANSAKSI DATABASE
========================== */

mysqli_begin_transaction($koneksi);

try {

    /* SIMPAN TRANSAKSI */

    $insert = mysqli_query(
        $koneksi,
        "INSERT INTO transaksi
        (
            id_nasabah,
            id_sampah,
            berat,
            harga_per_kg,
            total
        )
        VALUES
        (
            $id_nasabah,
            $id_sampah,
            $berat,
            $harga_per_kg,
            $total
        )"
    );

    if (!$insert) {

        throw new Exception(
            "Gagal menyimpan setoran: " .
            mysqli_error($koneksi)
        );

    }


    /* TAMBAH SALDO NASABAH */

    $updateSaldo = mysqli_query(
        $koneksi,
        "UPDATE nasabah
         SET saldo = saldo + $total
         WHERE id_nasabah=$id_nasabah"
    );

    if (!$updateSaldo) {

        throw new Exception(
            "Gagal menambahkan saldo: " .
            mysqli_error($koneksi)
        );

    }


    if (mysqli_affected_rows($koneksi) < 1) {

        throw new Exception(
            "Saldo nasabah gagal diperbarui."
        );

    }


    /* BERHASIL */

    mysqli_commit($koneksi);

    header(
        "Location: index.php?success=" .
        urlencode(
            "Setoran berhasil disimpan. Saldo nasabah bertambah Rp " .
            number_format($total, 0, ',', '.')
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