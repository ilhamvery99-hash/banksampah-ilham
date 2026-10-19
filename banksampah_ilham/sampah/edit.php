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

$id = mysqli_real_escape_string($koneksi, $id);

$query = mysqli_query($koneksi, "
    SELECT *
    FROM jenis_sampah
    WHERE id_sampah = '$id'
");

if (!$query) {
    die("Query gagal: " . mysqli_error($koneksi));
}

$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data jenis sampah tidak ditemukan.");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Edit Jenis Sampah</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7f6;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;

            width: 250px;
            height: 100vh;

            background: linear-gradient(
                180deg,
                #087f5b,
                #075e45
            );

            color: white;
            padding: 25px 15px;

            box-shadow: 4px 0 15px rgba(0,0,0,.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 65px;
            height: 65px;

            margin: auto;
            margin-bottom: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: rgba(255,255,255,.15);

            border-radius: 18px;

            font-size: 32px;
        }

        .logo h4 {
            margin: 0;
            font-weight: bold;
        }

        .logo small {
            opacity: .75;
        }

        .menu {
            margin-top: 20px;
        }

        .menu a {
            display: block;

            color: white;
            text-decoration: none;

            padding: 13px 15px;

            margin-bottom: 7px;

            border-radius: 10px;

            transition: .2s;
        }

        .menu a:hover {
            background: rgba(255,255,255,.15);
            transform: translateX(4px);
        }

        .menu a.active {
            background: rgba(255,255,255,.20);
            font-weight: bold;
        }

        .menu .logout {
            margin-top: 30px;
            background: rgba(255,255,255,.10);
        }

        .content {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            background: white;

            padding: 18px 25px;

            border-radius: 15px;

            box-shadow: 0 4px 20px rgba(0,0,0,.05);

            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }

        .topbar h3 {
            margin: 0;
            font-weight: bold;
            color: #075e45;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-icon {
            width: 42px;
            height: 42px;

            border-radius: 50%;

            background: #d8f3dc;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
        }

        .form-card {
            max-width: 700px;

            background: white;

            padding: 30px;

            border-radius: 18px;

            box-shadow: 0 4px 20px rgba(0,0,0,.05);
        }

        .form-card h4 {
            color: #075e45;
            font-weight: bold;

            margin-bottom: 25px;
        }

        .form-label {
            font-weight: bold;
        }

        .form-control {
            padding: 12px;

            border-radius: 10px;
        }

        .btn {
            border-radius: 9px;
            padding: 10px 18px;
        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;

                width: 100%;
                height: auto;
            }

            .content {
                margin-left: 0;
                padding: 15px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu a {
                margin: 0;
            }

            .menu .logout {
                margin-top: 0;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

        }

    </style>

</head>

<body>


<div class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            ♻️
        </div>

        <h4>Bank Sampah</h4>

        <small>Management System</small>

    </div>


    <div class="menu">

        <a href="../dashboard.php">
            🏠 Dashboard
        </a>

        <a href="../nasabah/index.php">
            👥 Nasabah
        </a>

        <a href="index.php" class="active">
            ♻️ Jenis Sampah
        </a>

        <a href="../transaksi/index.php">
            📦 Setoran
        </a>

        <a href="../penarikan/index.php">
            💰 Penarikan
        </a>

        <a href="../laporan/index.php">
            📊 Laporan
        </a>

        <a href="../logout.php"
           class="logout"
           onclick="return confirm('Yakin ingin logout?')">

            🚪 Logout

        </a>

    </div>

</div>


<div class="content">

    <div class="topbar">

        <div>

            <h3>
                Edit Jenis Sampah
            </h3>

            <small class="text-muted">
                Ubah data jenis sampah
            </small>

        </div>

        <div class="admin">

            <div class="admin-icon">
                👤
            </div>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $_SESSION['nama']
                        ?? $_SESSION['username']
                        ?? 'Admin'
                    ); ?>
                </strong>

                <br>

                <small class="text-muted">
                    Administrator
                </small>

            </div>

        </div>

    </div>


    <div class="form-card">

        <h4>
            ✏️ Edit Data Sampah
        </h4>


        <form action="update.php" method="POST">

            <input
                type="hidden"
                name="id_sampah"
                value="<?= $data['id_sampah']; ?>"
            >


            <div class="mb-3">

                <label class="form-label">
                    Nama Sampah
                </label>

                <input
                    type="text"
                    name="nama_sampah"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $data['nama_sampah']
                    ); ?>"
                    required
                >

            </div>


            <div class="mb-4">

                <label class="form-label">
                    Harga per Kg
                </label>

                <input
                    type="number"
                    name="harga_per_kg"
                    class="form-control"
                    value="<?= $data['harga_per_kg']; ?>"
                    min="0"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-success">

                💾 Update

            </button>


            <a
                href="index.php"
                class="btn btn-secondary">

                ← Kembali

            </a>

        </form>

    </div>

</div>

</body>
</html>