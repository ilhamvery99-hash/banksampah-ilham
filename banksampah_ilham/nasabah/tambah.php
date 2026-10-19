```php
<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Tambah Nasabah - Bank Sampah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background: #f0f4ff;
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, #c1121f, #e63946);
            color: white;
            padding: 22px 0;
            box-shadow: 0 4px 15px rgba(193, 18, 31, 0.2);
        }

        .topbar h3 {
            font-weight: 800;
            margin: 0;
        }

        .page-container {
            max-width: 850px;
            margin: 35px auto;
            padding: 0 15px;
        }

        .form-card {
            background: white;
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(25, 55, 120, 0.10);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #123c9c, #1877f2);
            color: white;
            padding: 24px 30px;
        }

        .form-header h4 {
            font-weight: 800;
            margin-bottom: 5px;
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
            box-shadow: 0 0 0 0.2rem rgba(24, 119, 242, 0.15);
        }

        .btn {
            border-radius: 10px;
            padding: 11px 20px;
            font-weight: 700;
        }

        .btn-simpan {
            background: #1877f2;
            color: white;
            border: none;
        }

        .btn-simpan:hover {
            background: #123c9c;
            color: white;
        }

        .btn-batal {
            background: #e63946;
            color: white;
            border: none;
        }

        .btn-batal:hover {
            background: #c1121f;
            color: white;
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

        @media (max-width: 576px) {
            .form-body {
                padding: 20px;
            }

            .form-header {
                padding: 20px;
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

<header class="topbar">
    <div class="container">
        <h3>
            <i class="bi bi-recycle me-2"></i>
            Bank Sampah
        </h3>
        <div class="mt-1">Sistem Pengelolaan Data Nasabah</div>
    </div>
</header>

<main class="page-container">

    <div class="mb-3">
        <a href="index.php" class="text-decoration-none fw-semibold"
           style="color:#123c9c;">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali ke Data Nasabah
        </a>
    </div>

    <div class="form-card">

        <div class="form-header">
            <h4>
                <i class="bi bi-person-plus-fill me-2"></i>
                Tambah Nasabah
            </h4>
            <div>Masukkan informasi nasabah baru dengan benar.</div>
        </div>

        <div class="form-body">

            <div class="info-box">
                <i class="bi bi-info-circle-fill me-2"></i>
                Pastikan kode nasabah belum digunakan oleh nasabah lain.
            </div>

            <form action="simpan.php" method="POST">

                <div class="mb-3">
                    <label for="kode_nasabah" class="form-label">
                        Kode Nasabah <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="kode_nasabah"
                        name="kode_nasabah"
                        class="form-control"
                        placeholder="Contoh: NSB001"
                        maxlength="50"
                        required>
                </div>

                <div class="mb-3">
                    <label for="nama" class="form-label">
                        Nama Nasabah <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        class="form-control"
                        placeholder="Masukkan nama lengkap"
                        maxlength="150"
                        required>
                </div>

                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea
                        id="alamat"
                        name="alamat"
                        class="form-control"
                        rows="3"
                        placeholder="Masukkan alamat lengkap"></textarea>
                </div>

                <div class="mb-4">
                    <label for="no_hp" class="form-label">Nomor HP</label>
                    <input
                        type="tel"
                        id="no_hp"
                        name="no_hp"
                        class="form-control"
                        placeholder="Contoh: 081234567890"
                        maxlength="20">
                </div>

                <hr class="mb-4">

                <div class="form-actions d-flex gap-2">
                    <button type="submit" class="btn btn-simpan">
                        <i class="bi bi-save me-1"></i>
                        Simpan Nasabah
                    </button>

                    <a href="index.php" class="btn btn-batal">
                        <i class="bi bi-x-circle me-1"></i>
                        Batal
                    </a>
                </div>

            </form>

        </div>
    </div>

    <div class="text-center text-secondary mt-4 small">
        &copy; <?= date('Y') ?> Bank Sampah
    </div>

</main>

</body>
</html>
```