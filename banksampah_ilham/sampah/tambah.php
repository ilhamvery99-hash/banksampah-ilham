```php
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Tambah Jenis Sampah - Bank Sampah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f0f4ff;
            font-family: Arial, sans-serif;
            color: #172554;
        }

        .topbar {
            background: linear-gradient(135deg, #c1121f, #e63946);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 15px rgba(193, 18, 31, .2);
        }

        .brand {
            font-size: 23px;
            font-weight: 800;
        }

        .topbar small {
            color: #ffe8e8;
        }

        .page-container {
            max-width: 850px;
            margin: 35px auto;
            padding: 0 15px;
        }

        .back-link {
            display: inline-block;
            color: #123c9c;
            font-weight: 700;
            text-decoration: none;
            margin-bottom: 18px;
        }

        .back-link:hover {
            color: #1877f2;
        }

        .form-card {
            background: white;
            border: 1px solid #e6edfa;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(25, 55, 120, .09);
        }

        .form-header {
            background: linear-gradient(135deg, #123c9c, #1877f2);
            color: white;
            padding: 25px 30px;
        }

        .form-header h3 {
            font-weight: 800;
            margin-bottom: 6px;
        }

        .form-header p {
            margin-bottom: 0;
            color: #e4edff;
        }

        .form-body {
            padding: 30px;
        }

        .form-label {
            color: #172554;
            font-weight: 700;
        }

        .form-control {
            border: 1px solid #d5def0;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .form-control:focus {
            border-color: #1877f2;
            box-shadow: 0 0 0 .2rem rgba(24, 119, 242, .15);
        }

        .info-box {
            background: #eef5ff;
            border-left: 4px solid #1877f2;
            border-radius: 8px;
            padding: 13px 15px;
            color: #1e3a8a;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .btn {
            padding: 11px 19px;
            border-radius: 10px;
            font-weight: 700;
        }

        .btn-simpan {
            background: #1877f2;
            border: none;
            color: white;
        }

        .btn-simpan:hover {
            background: #123c9c;
            color: white;
        }

        .btn-kembali {
            background: #e63946;
            border: none;
            color: white;
        }

        .btn-kembali:hover {
            background: #c1121f;
            color: white;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 20px;
        }

        @media (max-width: 576px) {
            .topbar {
                padding: 18px;
            }

            .form-body {
                padding: 20px;
            }

            .form-header {
                padding: 22px 20px;
            }

            .form-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <div class="brand">
            <i class="bi bi-recycle me-2"></i>Bank Sampah
        </div>
        <small>Sistem Pengelolaan Data Sampah</small>
    </div>

    <div class="fw-bold">
        <i class="bi bi-person-circle me-1"></i>
        <?= htmlspecialchars(
            $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
</header>

<main class="page-container">

    <a href="index.php" class="back-link">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali ke Data Jenis Sampah
    </a>

    <div class="form-card">

        <div class="form-header">
            <h3>
                <i class="bi bi-plus-circle-fill me-2"></i>
                Tambah Jenis Sampah
            </h3>
            <p>Masukkan nama sampah dan harga per kilogram.</p>
        </div>

        <div class="form-body">

            <div class="info-box">
                <i class="bi bi-info-circle-fill me-2"></i>
                Pastikan nama sampah dan harga yang dimasukkan sudah benar.
            </div>

            <form action="simpan.php" method="POST">

                <div class="mb-4">
                    <label for="nama_sampah" class="form-label">
                        Nama Sampah <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        id="nama_sampah"
                        name="nama_sampah"
                        class="form-control"
                        placeholder="Contoh: Botol Plastik"
                        maxlength="100"
                        required>
                </div>

                <div class="mb-4">
                    <label for="harga_per_kg" class="form-label">
                        Harga per Kg (Rp) <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">Rp</span>
                        <input
                            type="number"
                            id="harga_per_kg"
                            name="harga_per_kg"
                            class="form-control"
                            placeholder="Contoh: 3000"
                            min="0.01"
                            step="0.01"
                            required>
                    </div>

                    <div class="form-text">
                        Masukkan harga untuk setiap satu kilogram sampah.
                    </div>
                </div>

                <hr class="mb-4">

                <div class="form-actions d-flex gap-2">
                    <button type="submit" class="btn btn-simpan">
                        <i class="bi bi-save me-1"></i>
                        Simpan Sampah
                    </button>

                    <a href="index.php" class="btn btn-kembali">
                        <i class="bi bi-x-circle me-1"></i>
                        Batal
                    </a>
                </div>

            </form>
        </div>
    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> Bank Sampah — Sistem Pengelolaan Sampah
    </div>

</main>

</body>
</html>
```