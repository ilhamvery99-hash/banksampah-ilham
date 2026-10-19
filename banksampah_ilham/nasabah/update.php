<?php

include "../koneksi.php";

$id = $_POST['id_nasabah'];

$kode_nasabah = $_POST['kode_nasabah'];
$nama = $_POST['nama'];
$alamat = $_POST['alamat'];
$no_hp = $_POST['no_hp'];

$kode_nasabah = mysqli_real_escape_string(
    $koneksi,
    $kode_nasabah
);

$nama = mysqli_real_escape_string(
    $koneksi,
    $nama
);

$alamat = mysqli_real_escape_string(
    $koneksi,
    $alamat
);

$no_hp = mysqli_real_escape_string(
    $koneksi,
    $no_hp
);

$query = mysqli_query(
    $koneksi,
    "UPDATE nasabah SET

        kode_nasabah='$kode_nasabah',
        nama='$nama',
        alamat='$alamat',
        no_hp='$no_hp'

     WHERE id_nasabah='$id'"
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