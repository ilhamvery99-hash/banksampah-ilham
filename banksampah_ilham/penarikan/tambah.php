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
    "SELECT id_nasabah, kode_nasabah, nama, saldo FROM nasabah ORDER BY nama ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $jumlah = (float)($_POST['jumlah'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d\TH:i'));

    if ($id_nasabah <= 0 || $jumlah <= 0 || $tanggal === '') {
        $error = "Pilih nasabah dan masukkan jumlah penarikan yang valid.";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Kunci baris saldo agar tidak terjadi penarikan melebihi saldo
            $stmtCek = mysqli_prepare(
                $koneksi,
                "SELECT saldo FROM nasabah WHERE id_nasabah = ? FOR UPDATE"
            );
            mysqli_stmt_bind_param($stmtCek, "i", $id_nasabah);
            mysqli_stmt_execute($stmtCek);
            $hasil = mysqli_stmt_get_result($stmtCek);
            $data = mysqli_fetch_assoc($hasil);
            mysqli_stmt_close($stmtCek);

            if (!$data) {
                throw new Exception("Data nasabah tidak ditemukan.");
            }

            $saldo = (float)$data['saldo'];

            if ($jumlah > $saldo) {
                throw new Exception(
                    "Saldo tidak mencukupi. Saldo saat ini Rp " .
                    number_format($saldo, 0, ',', '.')
                );
            }

            $tanggalDB = date('Y-m-d H:i:s', strtotime($tanggal));

            $stmtTarik = mysqli_prepare(
    $koneksi,
    "INSERT INTO penarikan (id_nasabah, jumlah, tanggal, status)
     VALUES (?, ?, ?, 'menunggu')"
);
            mysqli_stmt_bind_param(
                $stmtTarik,
                "ids",
                $id_nasabah,
                $jumlah,
                $tanggalDB
            );

            if (!mysqli_stmt_execute($stmtTarik)) {
                throw new Exception("Gagal menyimpan data penarikan.");
            }
            mysqli_stmt_close($stmtTarik);

            $stmtUpdate = mysqli_prepare(
                $koneksi,
                "UPDATE nasabah SET saldo = saldo - ? WHERE id_nasabah = ?"
            );
            mysqli_stmt_bind_param($stmtUpdate, "di", $jumlah, $id_nasabah);

            if (!mysqli_stmt_execute($stmtUpdate)) {
                throw new Exception("Gagal memperbarui saldo nasabah.");
            }
            mysqli_stmt_close($stmtUpdate);

            mysqli_commit($koneksi);
            $pesan = "Penarikan saldo berhasil disimpan.";

            $nasabah = mysqli_query(
                $koneksi,
                "SELECT id_nasabah, kode_nasabah, nama, saldo FROM nasabah ORDER BY nama ASC"
            );
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarik Saldo - Bank Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
/* ===== TEMA SPIDER-MAN BIRU PUTIH MERAH ===== */

:root {
    --spidey-blue: #0756a8;
    --spidey-blue-dark: #062c63;
    --spidey-red: #e62429;
    --spidey-white: #ffffff;
    --spidey-bg: #eef5ff;
    --spidey-text: #172b4d;
}

body {
    margin: 0;
    background:
        radial-gradient(circle at 10% 10%, #d7e8ff 0, transparent 35%),
        var(--spidey-bg);
    color: var(--spidey-text);
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* SIDEBAR */
.sidebar {
    background: linear-gradient(
        180deg,
        var(--spidey-blue-dark),
        var(--spidey-blue)
    ) !important;
    color: white;
    border-right: 4px solid var(--spidey-red);
    box-shadow: 4px 0 18px rgba(6, 44, 99, 0.15);
}

.sidebar h4,
.sidebar h5,
.sidebar p {
    color: white;
}

.sidebar a {
    display: block;
    color: white !important;
    padding: 12px 15px;
    margin: 5px 0;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.25s;
}

.sidebar a:hover,
.sidebar a.active {
    background: var(--spidey-red) !important;
    color: white !important;
    transform: translateX(4px);
}

/* AREA KONTEN */
.main-content,
.content,
main {
    color: var(--spidey-text);
}

/* JUDUL */
h1, h2, h3, h4, h5 {
    font-weight: 700;
}

h1, h2, h3 {
    color: var(--spidey-blue-dark);
}

/* KARTU */
.card,
.stat-card {
    background: var(--spidey-white);
    border: 1px solid #d6e4f5;
    border-top: 4px solid var(--spidey-red);
    border-radius: 16px;
    box-shadow: 0 5px 18px rgba(6, 44, 99, 0.08);
    transition: 0.25s;
}

.card:hover,
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 24px rgba(6, 44, 99, 0.14);
}

/* TOMBOL */
.btn-primary,
.btn-success {
    background: var(--spidey-blue) !important;
    border-color: var(--spidey-blue) !important;
    color: white !important;
    border-radius: 9px;
}

.btn-primary:hover,
.btn-success:hover {
    background: var(--spidey-blue-dark) !important;
}

.btn-danger,
.btn-warning {
    background: var(--spidey-red) !important;
    border-color: var(--spidey-red) !important;
    color: white !important;
    border-radius: 9px;
}

.btn-outline-primary {
    color: var(--spidey-blue) !important;
    border-color: var(--spidey-blue) !important;
}

/* TABEL PERSETUJUAN */
.table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
}

.table thead th {
    background: var(--spidey-blue-dark) !important;
    color: white !important;
    border-color: #244b7b;
    padding: 14px;
}

.table tbody td {
    padding: 12px;
    vertical-align: middle;
}

.table tbody tr:nth-child(even) {
    background: #f2f7ff;
}

.table tbody tr:hover {
    background: #e2edff;
}

/* STATUS */
.badge.bg-success {
    background: #0756a8 !important;
}

.badge.bg-warning,
.badge.bg-danger {
    background: var(--spidey-red) !important;
    color: white !important;
}

/* INPUT FORM */
.form-control,
.form-select {
    border: 1px solid #c6d8f0;
    border-radius: 9px;
    padding: 10px 12px;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--spidey-blue);
    box-shadow: 0 0 0 3px rgba(7, 86, 168, 0.15);
}

/* GARIS AKSEN */
hr {
    border-top: 2px solid var(--spidey-red);
    opacity: 0.8;
}

/* TAMPILAN HP */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        position: relative;
    }

    .card,
    .stat-card {
        margin-bottom: 15px;
    }

    .table-responsive {
        overflow-x: auto;
    }
}

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
                <h2 class="judul mb-2">💰 Tarik Saldo</h2>
                <p class="text-muted">Catat penarikan saldo nasabah. Jumlah penarikan tidak boleh melebihi saldo yang tersedia.</p>

                <?php if ($pesan): ?>
                    <div class="alert alert-success"><?= e($pesan) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Pilih Nasabah</label>
                        <select name="id_nasabah" id="id_nasabah" class="form-select" required>
                            <option value="">-- Pilih Nasabah --</option>
                            <?php while ($n = mysqli_fetch_assoc($nasabah)): ?>
                                <option
                                    value="<?= (int)$n['id_nasabah'] ?>"
                                    data-saldo="<?= e($n['saldo']) ?>"
                                    <?= (($_POST['id_nasabah'] ?? '') == $n['id_nasabah']) ? 'selected' : '' ?>>
                                    <?= e($n['kode_nasabah']) ?> - <?= e($n['nama']) ?>
                                    (Saldo: Rp <?= number_format((float)$n['saldo'], 0, ',', '.') ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Saldo Tersedia</label>
                        <input type="text" id="saldoTampil" class="form-control bg-light" value="Rp 0" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Penarikan (Rp)</label>
                        <input type="number" name="jumlah" id="jumlah" class="form-control"
                               min="1" step="1" required
                               value="<?= e($_POST['jumlah'] ?? '') ?>" placeholder="Contoh: 10000">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sisa Saldo Setelah Penarikan</label>
                        <input type="text" id="sisaTampil" class="form-control bg-light" value="Rp 0" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Tanggal Penarikan</label>
                        <input type="datetime-local" name="tanggal" class="form-control" required
                               value="<?= e($_POST['tanggal'] ?? date('Y-m-d\TH:i')) ?>">
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2">
                        Simpan Penarikan
                    </button>
                    <a href="../dashboard_guru.php" class="btn btn-outline-secondary w-100 mt-2">Kembali ke Dashboard</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const pilihNasabah = document.getElementById('id_nasabah');
const inputJumlah = document.getElementById('jumlah');
const saldoTampil = document.getElementById('saldoTampil');
const sisaTampil = document.getElementById('sisaTampil');

function formatRupiah(angka) {
    return 'Rp ' + Math.max(0, angka).toLocaleString('id-ID');
}

function hitungSisa() {
    const opsi = pilihNasabah.options[pilihNasabah.selectedIndex];
    const saldo = parseFloat(opsi?.dataset?.saldo || 0);
    const jumlah = parseFloat(inputJumlah.value || 0);

    saldoTampil.value = formatRupiah(saldo);
    sisaTampil.value = jumlah > saldo
        ? 'Saldo tidak mencukupi'
        : formatRupiah(saldo - jumlah);
}

pilihNasabah.addEventListener('change', hitungSisa);
inputJumlah.addEventListener('input', hitungSisa);
hitungSisa();
</script>
</body>
</html>