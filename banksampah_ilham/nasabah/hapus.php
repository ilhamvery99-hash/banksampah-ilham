<?php

include "../koneksi.php";

$id = $_GET['id'];

$query = mysqli_query(
    $koneksi,
    "DELETE FROM nasabah
     WHERE id_nasabah='$id'"
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