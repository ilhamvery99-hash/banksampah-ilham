
<?php
session_start();
require_once "koneksi.php";

if (
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true ||
    ($_SESSION['role'] ?? '') !== 'siswa'
) {
    header("Location: login.php");
    exit();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['username'] ?? '';
$id_nasabah = (int)($_SESSION['id_nasabah'] ?? 0);
$nama = $_SESSION['nama'] ?? $username;
$pesan = '';
$jenisSampah = [];

// Cari ID nasabah jika belum tersedia di session
if ($id_nasabah <= 0 && $username !== '') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_nasabah FROM users
         WHERE username = ? AND role = 'siswa' LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($data) {
            $id_nasabah = (int)($data['id_nasabah'] ?? 0);
            $_SESSION['id_nasabah'] = $id_nasabah;
        }

        mysqli_stmt_close($stmt);
    }
}

// Ambil jenis sampah dari database
$result = mysqli_query(
    $koneksi,
    "SELECT id_sampah, nama_sampah, harga_per_kg
     FROM jenis_sampah ORDER BY nama_sampah ASC"
);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $jenisSampah[] = $row;
    }
}

// Proses pengajuan setoran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sampah = (int)($_POST['id_sampah'] ?? 0);
    $berat = filter_var($_POST['berat'] ?? '', FILTER_VALIDATE_FLOAT);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id_nasabah <= 0) {
        $pesan = "Akun siswa belum terhubung dengan data nasabah.";
    } elseif ($id_sampah <= 0 || $berat === false ||
              $berat <= 0 || $berat > 10000) {
        $pesan = "Pilih jenis sampah dan masukkan berat yang valid.";
    } elseif (strlen($keterangan) > 500) {
        $pesan = "Keterangan maksimal 500 karakter.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ||
              strtotime($tanggal) === false ||
              date('Y-m-d', strtotime($tanggal)) !== $tanggal ||
              $tanggal > date('Y-m-d')) {
        $pesan = "Tanggal setoran tidak valid.";
    } else {
        // Pastikan jenis sampah memang terdaftar
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id_sampah FROM jenis_sampah WHERE id_sampah = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_sampah);
        mysqli_stmt_execute($stmt);
        $validSampah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$validSampah) {
            $pesan = "Jenis sampah tidak ditemukan.";
        } else {
            /*
             * Tabel pengajuan dibuat terpisah dari transaksi.
             * Pengajuan belum menambah saldo siswa.
             */
            $buatTabel = "
                CREATE TABLE IF NOT EXISTS pengajuan_setoran (
                    id_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
                    id_nasabah INT NOT NULL,
                    id_sampah INT NOT NULL,
                    berat DECIMAL(10,2) NOT NULL,
                    tanggal DATE NOT NULL,
                    keterangan VARCHAR(500) DEFAULT NULL,
                    status ENUM(
                        'Menunggu',
                        'Disetujui',
                        'Ditolak'
                    ) NOT NULL DEFAULT 'Menunggu',
                    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_nasabah (id_nasabah),
                    INDEX idx_sampah (id_sampah)
                ) ENGINE=InnoDB
            ";

            if (!mysqli_query($koneksi, $buatTabel)) {
                $pesan = "Gagal menyiapkan tabel pengajuan setoran.";
            } else {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO pengajuan_setoran
                     (id_nasabah, id_sampah, berat, tanggal, keterangan)
                     VALUES (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $pesan = "Gagal menyiapkan pengajuan setoran.";
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "iidss",
                        $id_nasabah,
                        $id_sampah,
                        $berat,
                        $tanggal,
                        $keterangan
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header("Location: setor_sampah.php?berhasil=1");
                        exit();
                    } else {
                        $pesan = "Pengajuan gagal disimpan. Silakan coba lagi.";
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}

$berhasil = isset($_GET['berhasil']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Setor Sampah | Spider-Man</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --red: #d71932;
            --red-dark: #9e1024;
            --blue: #123d79;
            --bg: #f2f5fb;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: #18263b;
            font-family: Arial, Helvetica, sans-serif;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 245px;
            padding: 25px 15px;
            background: linear-gradient(160deg, var(--red), var(--red-dark) 55%, #092650);
            color: white;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 7px 24px;
            border-bottom: 1px solid #ffffff44;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: white;
            color: var(--red);
            font-size: 25px;
        }

        .brand strong { font-size: 15px; }
        .brand small { display: block; font-size: 10px; color: #ffe0e5; }

        .menu-title {
            margin: 25px 10px 10px;
            font-size: 10px;
            letter-spacing: 1.5px;
            color: #ffdbe0;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 6px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 13px;
        }

        .menu-link:hover, .menu-link.active {
            color: var(--blue);
            background: white;
        }

        .menu-link i { font-size: 18px; }

        .main {
            margin-left: 245px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h3 {
            font-weight: 800;
            margin: 0 0 5px;
        }

        .muted {
            color: #78849a;
            font-size: 13px;
        }

        .user-chip {
            padding: 10px 14px;
            background: white;
            border: 1px solid #e4e9f2;
            border-radius: 12px;
            font-size: 13px;
        }

        .hero {
            padding: 27px;
            margin-bottom: 24px;
            border-radius: 18px;
            color: white;
            background: linear-gradient(110deg, var(--red), #a91634 48%, var(--blue));
            position: relative;
            overflow: hidden;
        }

        .hero h2 { font-weight: 900; }
        .hero p { margin: 0; font-size: 13px; line-height: 1.7; }
        .hero-icon {
            position: absolute;
            right: 30px;
            top: 15px;
            font-size: 85px;
            opacity: .17;
        }

        .form-card {
            max-width: 850px;
            background: white;
            border: 1px solid #e5eaf2;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 25px #102c5010;
        }

        .card-heading {
            padding: 20px 25px;
            border-bottom: 1px solid #edf0f6;
        }

        .card-heading h5 { margin: 0; font-weight: 800; }

        .form-content { padding: 25px; }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            min-height: 45px;
            border-color: #dce3ee;
            border-radius: 9px;
            font-size: 13px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 .2rem #123d7918;
        }

        .btn-spidey {
            background: var(--red);
            color: white;
            border: 0;
            border-radius: 9px;
            padding: 12px 18px;
            font-size: 13px;
            font-weight: 800;
        }

        .btn-spidey:hover {
            background: var(--blue);
            color: white;
        }

        .btn-cancel {
            padding: 12px 18px;
            border-radius: 9px;
            font-size: 13px;
        }

        .info-box {
            padding: 15px;
            border-radius: 12px;
            background: #edf3ff;
            color: #234574;
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 800px) {
            .sidebar { width: 70px; padding: 18px 8px; }
            .brand { justify-content: center; padding: 5px 0 22px; }
            .brand-text, .menu-title, .menu-text { display: none; }
            .menu-link { justify-content: center; padding: 13px 5px; }
            .main { margin-left: 70px; padding: 20px 13px; }
        }

        @media (max-width: 480px) {
            .topbar { align-items: flex-start; }
            .topbar h3 { font-size: 20px; }
            .user-chip { font-size: 11px; padding: 8px; }
            .hero { padding: 22px 18px; }
            .hero h2 { font-size: 23px; }
            .hero-icon { right: 5px; font-size: 55px; }
            .form-content { padding: 18px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-shield-fill-check"></i>
        </div>
        <div class="brand-text">
            <strong>BANK SAMPAH</strong>
            <small>SPIDER-MAN EDITION</small>
        </div>
    </div>

    <div class="menu-title">MENU SISWA</div>

    <a href="dashboard_siswa.php" class="menu-link">
        <i class="bi bi-grid-fill"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="setor_sampah.php" class="menu-link active">
        <i class="bi bi-plus-circle-fill"></i>
        <span class="menu-text">Setor Sampah</span>
    </a>

    <a href="dashboard_siswa.php#setoran" class="menu-link">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Riwayat Setoran</span>
    </a>

    <a href="dashboard_siswa.php#penarikan" class="menu-link">
        <i class="bi bi-wallet2"></i>
        <span class="menu-text">Riwayat Penarikan</span>
    </a>

    <div class="menu-title">AKUN</div>

    <a href="logout.php" class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-left"></i>
        <span class="menu-text">Logout</span>
    </a>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h3>Setor Sampah</h3>
            <div class="muted">Ajukan setoran sampah dengan mudah.</div>
        </div>

        <div class="user-chip">
            <i class="bi bi-person-circle me-1 text-primary"></i>
            <?= e($nama) ?>
        </div>
    </div>

    <section class="hero">
        <h2>Jadilah Pahlawan Bumi! 🕷️</h2>
        <p>
            Kumpulkan sampah yang dapat didaur ulang dan ajukan
            setoran kepada petugas Bank Sampah.
        </p>
        <i class="bi bi-shield-shaded hero-icon"></i>
    </section>

    <?php if ($berhasil): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            Pengajuan setoran berhasil dikirim. Silakan tunggu pemeriksaan petugas.
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-danger">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">
        <div class="card-heading">
            <h5>
                <i class="bi bi-recycle me-2 text-danger"></i>
                Formulir Pengajuan Setoran
            </h5>
        </div>

        <div class="form-content">
            <?php if ($id_nasabah <= 0): ?>
                <div class="alert alert-warning">
                    Akun siswa belum terhubung dengan nasabah.
                    Hubungi admin untuk menghubungkan akunmu sebelum mengajukan setoran.
                </div>
            <?php elseif (count($jenisSampah) === 0): ?>
                <div class="alert alert-warning">
                    Belum ada jenis sampah. Minta admin menambahkan data jenis sampah terlebih dahulu.
                </div>
            <?php else: ?>
                <form method="POST" action="setor_sampah.php">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Nama Siswa</label>
                            <input type="text" class="form-control"
                                   value="<?= e($nama) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tanggal Setoran</label>
                            <input type="date" name="tanggal"
                                   class="form-control"
                                   value="<?= date('Y-m-d') ?>"
                                   max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Jenis Sampah</label>
                            <select name="id_sampah" id="jenisSampah"
                                    class="form-select" required>
                                <option value="">-- Pilih Jenis Sampah --</option>
                                <?php foreach ($jenisSampah as $jenis): ?>
                                    <option
                                        value="<?= (int)$jenis['id_sampah'] ?>"
                                        data-harga="<?= (float)$jenis['harga_per_kg'] ?>"
                                        <?= (isset($_POST['id_sampah']) &&
                                            (int)$_POST['id_sampah'] ===
                                            (int)$jenis['id_sampah'])
                                            ? 'selected' : '' ?>>
                                        <?= e($jenis['nama_sampah']) ?>
                                        — Rp <?= number_format(
                                            (float)$jenis['harga_per_kg'],
                                            0, ',', '.'
                                        ) ?>/kg
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Berat Sampah (kg)</label>
                            <input type="number" name="berat" id="berat"
                                   class="form-control"
                                   min="0.01" max="10000" step="0.01"
                                   placeholder="Contoh: 2.5"
                                   value="<?= e($_POST['berat'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Perkiraan Nilai Setoran</label>
                            <input type="text" id="perkiraan"
                                   class="form-control"
                                   value="Rp 0" readonly>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Keterangan (opsional)</label>
                            <textarea name="keterangan"
                                      class="form-control"
                                      rows="3" maxlength="500"
                                      placeholder="Contoh: Botol plastik sudah dibersihkan."><?= e($_POST['keterangan'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="info-box">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Nilai yang tampil hanya perkiraan berdasarkan
                                harga saat ini. Petugas akan memeriksa berat
                                dan kondisi sampah sebelum transaksi disahkan.
                                Pengajuan ini belum menambah saldo tabungan.
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-spidey"
                                    onclick="return confirm('Kirim pengajuan setoran sampah?')">
                                <i class="bi bi-send-fill me-2"></i>
                                Kirim Pengajuan
                            </button>

                            <a href="dashboard_siswa.php"
                               class="btn btn-outline-secondary btn-cancel">
                                Kembali ke Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <div class="text-center text-secondary small mt-4">
        <i class="bi bi-shield-fill-check text-danger"></i>
        Bank Sampah Spider-Man Edition · Save Our Planet
    </div>
</main>

<script>
    const jenis = document.getElementById('jenisSampah');
    const berat = document.getElementById('berat');
    const perkiraan = document.getElementById('perkiraan');

    function hitungPerkiraan() {
        if (!jenis || !berat || !perkiraan) return;

        const pilihan = jenis.options[jenis.selectedIndex];
        const harga = Number(pilihan.dataset.harga || 0);
        const jumlahBerat = Number(berat.value || 0);
        const total = harga * jumlahBerat;

        perkiraan.value = total.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        });
    }

    if (jenis && berat) {
        jenis.addEventListener('change', hitungPerkiraan);
        berat.addEventListener('input', hitungPerkiraan);
        hitungPerkiraan();
    }
</script>

</body>
</html>
