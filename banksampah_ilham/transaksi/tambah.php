<?php
session_start();
require_once "../koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'guru', 'pengepul'], true)) {
    header("Location: ../login.php");
    exit();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$pesan = "";
$error = "";

$nasabah = mysqli_query(
    $koneksi,
    "SELECT id_nasabah, kode_nasabah, nama
     FROM nasabah ORDER BY nama ASC"
);

$sampah = mysqli_query(
    $koneksi,
    "SELECT id_sampah, nama_sampah, harga_per_kg
     FROM jenis_sampah ORDER BY nama_sampah ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $id_sampah  = (int)($_POST['id_sampah'] ?? 0);
    $berat      = (float)($_POST['berat'] ?? 0);
    $tanggal    = trim($_POST['tanggal'] ?? '');

    if (
        $id_nasabah <= 0 ||
        $id_sampah <= 0 ||
        $berat <= 0 ||
        $tanggal === ''
    ) {
        $error = "Semua data wajib diisi dan berat harus lebih dari 0.";
    } else {
        // Validasi tanggal
        $waktu = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal);

        if (!$waktu || $waktu->format('Y-m-d\TH:i') !== $tanggal) {
            $error = "Format tanggal tidak valid.";
        } else {
            $tanggalDB = $waktu->format('Y-m-d H:i:s');

            // Pastikan nasabah dan jenis sampah tersedia
            $stmtCek = mysqli_prepare(
                $koneksi,
                "SELECT n.id_nasabah, s.id_sampah
                 FROM nasabah n
                 CROSS JOIN jenis_sampah s
                 WHERE n.id_nasabah = ? AND s.id_sampah = ?"
            );

            mysqli_stmt_bind_param(
                $stmtCek,
                "ii",
                $id_nasabah,
                $id_sampah
            );

            mysqli_stmt_execute($stmtCek);
            $hasilCek = mysqli_stmt_get_result($stmtCek);
            $dataCek = mysqli_fetch_assoc($hasilCek);
            mysqli_stmt_close($stmtCek);

            if (!$dataCek) {
                $error = "Nasabah atau jenis sampah tidak ditemukan.";
            } else {
                // Masukkan ke pengajuan, BUKAN transaksi final.
                // Saldo tidak bertambah sebelum disetujui admin.
                $keterangan = "Pengajuan setoran oleh guru";

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO pengajuan_setoran
                    (id_nasabah, id_sampah, berat, tanggal, keterangan, status)
                    VALUES (?, ?, ?, ?, ?, 'Menunggu')"
                );

                if (!$stmt) {
                    $error = "Gagal menyiapkan pengajuan: " .
                             mysqli_error($koneksi);
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "iidss",
                        $id_nasabah,
                        $id_sampah,
                        $berat,
                        $tanggalDB,
                        $keterangan
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $pesan = "Pengajuan setoran berhasil dikirim. Menunggu persetujuan admin.";
                        $_POST = [];
                    } else {
                        $error = "Gagal menyimpan pengajuan: " .
                                 mysqli_stmt_error($stmt);
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Muat ulang pilihan agar form tetap tersedia
$nasabah = mysqli_query(
    $koneksi,
    "SELECT id_nasabah, kode_nasabah, nama
     FROM nasabah ORDER BY nama ASC"
);

$sampah = mysqli_query(
    $koneksi,
    "SELECT id_sampah, nama_sampah, harga_per_kg
     FROM jenis_sampah ORDER BY nama_sampah ASC"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setor Sampah - Bank Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
```css
body {
    background: linear-gradient(135deg, #eaf4ff, #ffffff, #ffe9ed);
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #172554;
    min-height: 100vh;
}

.navbar {
    background: linear-gradient(90deg, #b51224, #e31b35) !important;
    box-shadow: 0 4px 15px rgba(20, 50, 100, 0.2);
}

.navbar-brand {
    font-weight: 800;
    letter-spacing: 0.5px;
}

.card {
    background: #ffffff;
    border: 2px solid #d8e7ff;
    border-top: 6px solid #d71935;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(25, 65, 130, 0.15);
}

.judul {
    color: #d71935;
    font-weight: 800;
}

.form-label {
    color: #173b75;
    font-weight: 700;
}

.form-control,
.form-select {
    border: 1.5px solid #b8cdeb;
    border-radius: 10px;
    padding: 11px 13px;
}

.form-control:focus,
.form-select:focus {
    border-color: #1769d2;
    box-shadow: 0 0 0 0.2rem rgba(23, 105, 210, 0.15);
}

.btn-success {
    background: linear-gradient(90deg, #1458b5, #1976e8);
    border: none;
    color: #ffffff;
    font-weight: 700;
    border-radius: 10px;
    transition: 0.25s;
}

.btn-success:hover {
    background: linear-gradient(90deg, #b51224, #e31b35);
    transform: translateY(-2px);
}

.btn-outline-secondary {
    border: 2px solid #1769d2;
    color: #1769d2;
    font-weight: 700;
    border-radius: 10px;
}

.btn-outline-secondary:hover {
    background: #1769d2;
    color: #ffffff;
}

.alert-success {
    background: #e7f1ff;
    border: 1px solid #1769d2;
    color: #12458b;
    border-radius: 10px;
}

.alert-danger {
    border-radius: 10px;
}

.text-muted {
    color: #64748b !important;
}

.saldo {
    color: #1769d2;
    font-weight: 800;
}
```
    </style>
</head>
<body>
<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard_guru.php">♻ Bank Sampah</a>
        <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4 p-md-5">
                <h2 class="judul mb-2">♻ Setor Sampah</h2>
                <p class="text-muted">Catat sampah yang disetorkan nasabah. Saldo dihitung otomatis dari berat dan harga per kilogram.</p>

                <?php if ($pesan): ?>
                    <div class="alert alert-success"><?= e($pesan) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (mysqli_num_rows($nasabah) === 0 || mysqli_num_rows($sampah) === 0): ?>
                    <div class="alert alert-warning">
                        Pastikan data nasabah dan jenis sampah sudah tersedia sebelum melakukan setoran.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Pilih Nasabah</label>
                        <select name="id_nasabah" class="form-select" required>
                            <option value="">-- Pilih Nasabah --</option>
                            <?php while ($n = mysqli_fetch_assoc($nasabah)): ?>
                                <option value="<?= (int)$n['id_nasabah'] ?>"
                                    <?= (($_POST['id_nasabah'] ?? '') == $n['id_nasabah']) ? 'selected' : '' ?>>
                                    <?= e($n['kode_nasabah']) ?> - <?= e($n['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Sampah</label>
                        <select name="id_sampah" id="id_sampah" class="form-select" required>
                            <option value="">-- Pilih Jenis Sampah --</option>
                            <?php while ($s = mysqli_fetch_assoc($sampah)): ?>
                                <option
                                    value="<?= (int)$s['id_sampah'] ?>"
                                    data-harga="<?= e($s['harga_per_kg']) ?>"
                                    <?= (($_POST['id_sampah'] ?? '') == $s['id_sampah']) ? 'selected' : '' ?>>
                                    <?= e($s['nama_sampah']) ?> - Rp <?= number_format((float)$s['harga_per_kg'], 0, ',', '.') ?>/kg
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Berat Sampah (kg)</label>
                        <input type="number" name="berat" id="berat" class="form-control"
                               min="0.01" step="0.01" required
                               value="<?= e($_POST['berat'] ?? '') ?>" placeholder="Contoh: 2.5">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Perkiraan Total Setoran</label>
                        <input type="text" id="totalTampil" class="form-control bg-light"
                               value="Rp 0" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Tanggal Setoran</label>
                        <input type="datetime-local" name="tanggal" class="form-control" required
                               value="<?= e($_POST['tanggal'] ?? date('Y-m-d\TH:i')) ?>">
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2"
                        <?= (mysqli_num_rows($nasabah) === 0 || mysqli_num_rows($sampah) === 0) ? 'disabled' : '' ?>>
                        Simpan Setoran Sampah
                    </button>
                    <a href="../dashboard_guru.php" class="btn btn-outline-secondary w-100 mt-2">Kembali ke Dashboard</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const pilihSampah = document.getElementById('id_sampah');
const inputBerat = document.getElementById('berat');
const totalTampil = document.getElementById('totalTampil');

function hitungTotal() {
    const opsi = pilihSampah.options[pilihSampah.selectedIndex];
    const harga = parseFloat(opsi?.dataset?.harga || 0);
    const berat = parseFloat(inputBerat.value || 0);
    const total = harga * berat;

    totalTampil.value = 'Rp ' + Math.round(total).toLocaleString('id-ID');
}

pilihSampah.addEventListener('change', hitungTotal);
inputBerat.addEventListener('input', hitungTotal);
hitungTotal();
</script>
</body>
</html>
```