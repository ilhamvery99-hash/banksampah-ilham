<?php

session_start();
include "../koneksi.php";

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

$query = mysqli_query(
    $koneksi,
    "SELECT * FROM nasabah
     WHERE id_nasabah='$id'"
);

$data = mysqli_fetch_assoc($query);

if (!$data) {

    die("Data nasabah tidak ditemukan.");

}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Edit Nasabah</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="card shadow-sm border-0">

        <div class="card-body p-4">

            <h3 class="fw-bold mb-4">
                Edit Nasabah
            </h3>

            <form
            action="update.php"
            method="POST">

                <input
                type="hidden"
                name="id_nasabah"
                value="<?= $data['id_nasabah'] ?>">


                <div class="mb-3">

                    <label class="form-label">
                        Kode Nasabah
                    </label>

                    <input
                    type="text"
                    name="kode_nasabah"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $data['kode_nasabah']
                    ) ?>"
                    required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Nama Nasabah
                    </label>

                    <input
                    type="text"
                    name="nama"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $data['nama']
                    ) ?>"
                    required>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Alamat
                    </label>

                    <textarea
                    name="alamat"
                    class="form-control"
                    rows="3"><?= htmlspecialchars(
                        $data['alamat']
                    ) ?></textarea>

                </div>


                <div class="mb-3">

                    <label class="form-label">
                        Nomor HP
                    </label>

                    <input
                    type="text"
                    name="no_hp"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $data['no_hp']
                    ) ?>">

                </div>


                <button
                type="submit"
                class="btn btn-success">

                    Update

                </button>

                <a
                href="index.php"
                class="btn btn-secondary">

                    Batal

                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>