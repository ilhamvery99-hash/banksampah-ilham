<?php
session_start();
require_once "koneksi.php";

// ========================================
// CEK LOGIN SISWA
// ========================================
if (
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true ||
    ($_SESSION['role'] ?? '') !== 'siswa'
) {
    header("Location: login.php");
    exit();
}

// ========================================
// FUNGSI BANTU
// ========================================
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($angka) {
    return 'Rp ' . number_format(
        (float)$angka,
        0,
        ',',
        '.'
    );
}

// ========================================
// AMBIL ID NASABAH
// ========================================
$username = $_SESSION['username'] ?? '';
$id_nasabah = (int)($_SESSION['id_nasabah'] ?? 0);

if ($id_nasabah <= 0 && $username !== '') {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_nasabah
         FROM users
         WHERE username = ? AND role = 'siswa'
         LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user) {
            $id_nasabah = (int)$user['id_nasabah'];
            $_SESSION['id_nasabah'] = $id_nasabah;
        }

        mysqli_stmt_close($stmt);
    }
}

if ($id_nasabah <= 0) {
    die("Akun siswa belum terhubung dengan data nasabah.");
}

// ========================================
// TOKEN KEAMANAN FORM
// ========================================
if (empty($_SESSION['csrf_tarik_saldo'])) {
    $_SESSION['csrf_tarik_saldo'] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION['csrf_tarik_saldo'];

$pesan = '';
$jenisPesan = 'danger';

// ========================================
// PROSES PENARIKAN SALDO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($csrfToken, $token)
    ) {
        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {

        $nominalInput = $_POST['jumlah'] ?? '';

        // Nominal wajib berupa angka bulat positif.
        if (
            !is_string($nominalInput) ||
            !preg_match('/^[0-9]+$/', $nominalInput) ||
            (float)$nominalInput <= 0 ||
            (float)$nominalInput > 2147483647
        ) {
            $pesan = "Masukkan nominal penarikan yang valid.";
        } else {

            $jumlah = (int)$nominalInput;

            try {
                // Mulai transaksi database.
                mysqli_begin_transaction($koneksi);

                // Kunci data saldo agar tidak terjadi
                // penarikan bersamaan yang melebihi saldo.
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT saldo
                     FROM nasabah
                     WHERE id_nasabah = ?
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Gagal memeriksa saldo nasabah."
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id_nasabah
                );

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new Exception(
                        "Gagal memeriksa saldo nasabah."
                    );
                }

                $resultSaldo = mysqli_stmt_get_result($stmt);
                $dataSaldo = mysqli_fetch_assoc($resultSaldo);

                mysqli_stmt_close($stmt);

                if (!$dataSaldo) {
                    throw new Exception(
                        "Data nasabah tidak ditemukan."
                    );
                }

                $saldoSekarang = (float)$dataSaldo['saldo'];

                // Periksa saldo sebelum penarikan.
                if ($jumlah > $saldoSekarang) {

                    mysqli_rollback($koneksi);

                    $pesan = "Saldo tidak mencukupi. Saldo kamu saat ini " .
                             rupiah($saldoSekarang) . ".";

                } else {

                    // Kurangi saldo dengan syarat saldo
                    // masih mencukupi.
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "UPDATE nasabah
                         SET saldo = saldo - ?
                         WHERE id_nasabah = ?
                         AND saldo >= ?"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Gagal menyiapkan perubahan saldo."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "idi",
                        $jumlah,
                        $id_nasabah,
                        $jumlah
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        throw new Exception(
                            "Gagal memperbarui saldo."
                        );
                    }

                    $saldoBerubah = mysqli_stmt_affected_rows($stmt);

                    mysqli_stmt_close($stmt);

                    if ($saldoBerubah !== 1) {
                        throw new Exception(
                            "Saldo tidak berhasil diperbarui."
                        );
                    }

                    // Simpan riwayat penarikan.
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO penarikan
                            (id_nasabah, tanggal, jumlah)
                         VALUES (?, NOW(), ?)"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Gagal menyiapkan riwayat penarikan."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ii",
                        $id_nasabah,
                        $jumlah
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        throw new Exception(
                            "Gagal menyimpan riwayat penarikan."
                        );
                    }

                    mysqli_stmt_close($stmt);

                    // Saldo dan riwayat harus berhasil
                    // disimpan bersama-sama.
                    mysqli_commit($koneksi);

                    $pesan = "Penarikan sebesar " .
                             rupiah($jumlah) .
                             " berhasil diproses.";

                    $jenisPesan = 'success';

                    // Perbarui token agar form lama tidak
                    // dapat digunakan kembali.
                    $_SESSION['csrf_tarik_saldo'] = bin2hex(
                        random_bytes(32)
                    );

                    $csrfToken = $_SESSION['csrf_tarik_saldo'];
                }
            } catch (Throwable $ex) {

                mysqli_rollback($koneksi);

                // Detail kesalahan dicatat di log server.
                error_log(
                    "Kesalahan penarikan saldo: " .
                    $ex->getMessage()
                );

                $pesan = "Penarikan gagal diproses. " .
                         "Silakan periksa database atau hubungi admin.";
            }
        }
    }
}

// ========================================
// AMBIL DATA TERBARU
// ========================================
$namaSiswa = $_SESSION['nama'] ??
             $_SESSION['username'] ??
             'Siswa';

$saldo = 0;

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT nama, saldo
     FROM nasabah
     WHERE id_nasabah = ?
     LIMIT 1"
);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_nasabah
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $nasabah = mysqli_fetch_assoc($result);

    if ($nasabah) {
        $namaSiswa = $nasabah['nama'];
        $saldo = (float)$nasabah['saldo'];
    }

    mysqli_stmt_close($stmt);
}

// ========================================
// RIWAYAT PENARIKAN
// ========================================
$riwayat = [];

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT tanggal, jumlah
     FROM penarikan
     WHERE id_nasabah = ?
     ORDER BY tanggal DESC
     LIMIT 5"
);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_nasabah
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $riwayat[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Tarik Saldo | Bank Sampah Spider-Man</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --red: #d71932;
            --dark-red: #9e1024;
            --blue: #123d79;
            --dark-blue: #092650;
            --bg: #f2f5fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: #18263b;
            font-family: Arial, Helvetica, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 245px;
            padding: 25px 17px;
            color: white;
            background: linear-gradient(
                165deg,
                var(--red),
                var(--dark-red) 52%,
                var(--dark-blue)
            );
        }

        .brand {
            padding: 8px 5px 25px;
            border-bottom: 1px solid #ffffff40;
        }

        .brand h4 {
            margin: 0;
            font-weight: 900;
        }

        .brand small {
            color: #ffe0e5;
            font-size: 11px;
        }

        .menu-label {
            margin: 25px 8px 10px;
            color: #ffd8de;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 5px 0;
            padding: 12px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
        }

        .menu-link:hover,
        .menu-link.active {
            color: var(--dark-blue);
            background: white;
        }

        .main {
            margin-left: 245px;
            padding: 30px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h3 {
            margin: 0 0 5px;
            font-weight: 900;
        }

        .topbar p {
            margin: 0;
            color: #77839a;
            font-size: 13px;
        }

        .card-custom {
            padding: 25px;
            background: white;
            border: 1px solid #e5eaf2;
            border-radius: 18px;
            box-shadow: 0 6px 22px #1931550a;
        }

        .balance-card {
            position: relative;
            overflow: hidden;
            padding: 28px;
            color: white;
            background: linear-gradient(
                120deg,
                var(--red),
                #b71535 48%,
                var(--blue)
            );
            border-radius: 18px;
        }

        .balance-card .label {
            color: #ffe5e9;
            font-size: 13px;
        }

        .balance-card .amount {
            margin-top: 8px;
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .balance-icon {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 75px;
            opacity: .2;
        }

        .section-title {
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 800;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
        }

        .form-control {
            padding: 12px 14px;
            border-radius: 10px;
        }

        .btn-spidey {
            padding: 12px 16px;
            color: white;
            background: var(--red);
            border: 0;
            border-radius: 10px;
            font-weight: 700;
        }

        .btn-spidey:hover {
            color: white;
            background: var(--dark-red);
        }

        .btn-back {
            padding: 12px 16px;
            color: var(--blue);
            background: #eaf0fc;
            border: 0;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-back:hover {
            color: var(--dark-blue);
            background: #dce6fa;
        }

        .table {
            font-size: 13px;
            vertical-align: middle;
        }

        .table thead th {
            padding: 14px;
            color: #728098;
            background: #f8faff;
            font-size: 11px;
            text-transform: uppercase;
        }

        .table tbody td {
            padding: 14px;
        }

        .money {
            color: var(--blue);
            font-weight: 800;
        }

        .footer {
            padding: 25px 0 5px;
            color: #8993a6;
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .brand h4 {
                font-size: 12px;
                overflow-wrap: anywhere;
            }

            .brand small,
            .menu-label,
            .menu-text {
                display: none;
            }

            .menu-link {
                justify-content: center;
                padding: 13px 5px;
            }

            .main {
                margin-left: 70px;
                padding: 20px 12px;
            }

            .card-custom {
                padding: 18px;
            }

            .topbar h3 {
                font-size: 20px;
            }
        }

        @media (max-width: 450px) {
            .balance-card {
                padding: 22px 18px;
            }

            .balance-icon {
                right: 10px;
                font-size: 50px;
            }

            .topbar {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="brand">
        <h4>
            <i class="bi bi-shield-fill-check"></i>
            BANK SAMPAH
        </h4>

        <small>SPIDER-MAN EDITION</small>
    </div>

    <div class="menu-label">MENU SISWA</div>

    <a href="dashboard_siswa.php" class="menu-link">
        <i class="bi bi-grid-1x2-fill"></i>
        <span class="menu-text">Dashboard</span>
    </a>

    <a href="dashboard_siswa.php#profil" class="menu-link">
        <i class="bi bi-person-circle"></i>
        <span class="menu-text">Profil Saya</span>
    </a>

    <a href="dashboard_siswa.php#setoran" class="menu-link">
        <i class="bi bi-recycle"></i>
        <span class="menu-text">Riwayat Setoran</span>
    </a>

    <a href="dashboard_siswa.php#penarikan"
       class="menu-link active">
        <i class="bi bi-wallet2"></i>
        <span class="menu-text">Riwayat Penarikan</span>
    </a>

    <div class="menu-label">AKUN</div>

    <a href="logout.php"
       class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-left"></i>
        <span class="menu-text">Logout</span>
    </a>

</aside>

<!-- KONTEN -->
<main class="main">

    <div class="topbar">
        <div>
            <h3>Tarik Saldo</h3>
            <p>Kelola penarikan tabungan Bank Sampah.</p>
        </div>

        <a href="dashboard_siswa.php"
           class="btn-back">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali
        </a>
    </div>

    <!-- SALDO -->
    <div class="balance-card mb-4">

        <div class="label">
            <i class="bi bi-wallet-fill me-1"></i>
            Saldo Tabungan Kamu
        </div>

        <div class="amount">
            <?= rupiah($saldo) ?>
        </div>

        <div class="mt-2 small">
            <i class="bi bi-person-circle me-1"></i>
            <?= e($namaSiswa) ?>
        </div>

        <i class="bi bi-cash-stack balance-icon"></i>

    </div>

    <!-- FORM PENARIKAN -->
    <div class="card-custom mb-4">

        <h5 class="section-title">
            <i class="bi bi-cash-coin me-2"
               style="color:var(--red)"></i>
            Form Penarikan Saldo
        </h5>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= e($jenisPesan) ?>"
                 role="alert">
                <i class="bi bi-info-circle me-1"></i>
                <?= e($pesan) ?>
            </div>
        <?php endif; ?>

        <?php if ($saldo <= 0): ?>

            <div class="alert alert-warning">
                Saldo kamu masih kosong. Silakan setor sampah
                terlebih dahulu untuk menambah saldo tabungan.
            </div>

        <?php else: ?>

            <form method="POST"
                  action="tarik_saldo.php"
                  onsubmit="return confirm('Apakah kamu yakin ingin menarik saldo sesuai nominal yang dimasukkan?');">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($csrfToken) ?>">

                <div class="mb-3">

                    <label for="jumlah" class="form-label">
                        Nominal Penarikan (Rp)
                    </label>

                    <input
                        type="number"
                        name="jumlah"
                        id="jumlah"
                        class="form-control"
                        min="1"
                        max="<?= e((string)min(
                            floor($saldo),
                            2147483647
                        )) ?>"
                        step="1"
                        placeholder="Contoh: 10000"
                        required
                    >

                    <div class="form-text">
                        Saldo tersedia:
                        <strong><?= rupiah($saldo) ?></strong>
                    </div>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <button type="submit"
                            class="btn btn-spidey">
                        <i class="bi bi-check-circle me-1"></i>
                        Proses Penarikan
                    </button>

                    <a href="dashboard_siswa.php"
                       class="btn-back">
                        Batal
                    </a>

                </div>

            </form>

        <?php endif; ?>

    </div>

    <!-- RIWAYAT PENARIKAN -->
    <div class="card-custom">

        <h5 class="section-title">
            <i class="bi bi-clock-history me-2"
               style="color:var(--red)"></i>
            Riwayat Penarikan Terbaru
        </h5>

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Jumlah Penarikan</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($riwayat) > 0): ?>

                        <?php foreach ($riwayat as $i => $row): ?>

                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= e(date(
                                        'd-m-Y H:i',
                                        strtotime($row['tanggal'])
                                    )) ?>
                                </td>

                                <td class="money">
                                    <?= rupiah($row['jumlah']) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3"
                                class="text-center text-secondary py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Belum ada riwayat penarikan.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <footer class="footer">
        <div>
            <i class="bi bi-shield-fill-check me-1"></i>
            Bank Sampah Spider-Man Edition
        </div>

        <div class="mt-1">
            Dengan memilah sampah, kamu ikut menjaga bumi.
        </div>
    </footer>

</main>

</body>
</html>