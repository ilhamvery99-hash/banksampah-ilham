
<?php
session_start();
require_once "koneksi.php";

$error = "";
$username = "";

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header("Location: dashboard.php");
            exit();

        case 'siswa':
            header("Location: dashboard_siswa.php");
            exit();

        case 'guru':
            header("Location: dashboard_guru.php");
            exit();

        case 'pengepul':
            header("Location: portal.php");
            exit();
    }
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi!";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT * FROM users WHERE username = ? LIMIT 1"
        );

        if (!$stmt) {
            $error = "Terjadi kesalahan saat memproses login.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            /*
             * Mendukung password yang disimpan dengan
             * password_hash() maupun password teks biasa
             * untuk kompatibilitas dengan database lama.
             */
            $validPassword = false;

            if ($user) {
                $storedPassword = (string)$user['password'];

                $info = password_get_info($storedPassword);

                if (($info['algo'] ?? null) !== null &&
                    ($info['algo'] ?? 0) !== 0) {
                    $validPassword = password_verify(
                        $password,
                        $storedPassword
                    );
                } else {
                    $validPassword = hash_equals(
                        $storedPassword,
                        $password
                    );
                }
            }

            if ($user && $validPassword) {
                $role = strtolower(trim($user['role'] ?? ''));

                $destinations = [
                    'admin'    => 'dashboard.php',
                    'siswa'    => 'dashboard_siswa.php',
                    'guru'     => 'dashboard_guru.php',
                    'pengepul' => 'portal.php'
                ];

                if (!isset($destinations[$role])) {
                    $error = "Role akun tidak dikenali.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['login'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $role;
                    $_SESSION['nama'] =
                        $user['nama'] ?? $user['username'];

                    if ($role === 'siswa') {
                        $_SESSION['id_nasabah'] =
                            (int)($user['id_nasabah'] ?? 0);
                    }

                    header(
                        "Location: " . $destinations[$role]
                    );
                    exit();
                }
            } else {
                $error = "Username atau password salah!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | Bank Sampah Spider-Man</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --red: #d71932;
            --red-dark: #9e1024;
            --blue: #123d79;
            --blue-dark: #071d40;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px 15px;
            font-family: Arial, Helvetica, sans-serif;
            color: #17243b;
            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(215,25,50,.9),
                    transparent 34%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(24,91,170,.9),
                    transparent 38%
                ),
                linear-gradient(135deg, #071d40, #123d79);
            overflow-x: hidden;
            position: relative;
        }

        /* Pola jaring laba-laba */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .15;
            background-image:
                repeating-linear-gradient(
                    0deg,
                    transparent 0 47px,
                    #fff 48px 49px,
                    transparent 50px 70px
                ),
                repeating-linear-gradient(
                    60deg,
                    transparent 0 47px,
                    #fff 48px 49px,
                    transparent 50px 70px
                ),
                repeating-linear-gradient(
                    120deg,
                    transparent 0 47px,
                    #fff 48px 49px,
                    transparent 50px 70px
                );
            mask-image: linear-gradient(
                135deg, black, transparent 75%
            );
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            animation: appear .7s ease;
        }

        .login-card {
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0,0,0,.35);
        }

        .login-header {
            position: relative;
            overflow: hidden;
            padding: 32px 25px 27px;
            color: #fff;
            text-align: center;
            background: linear-gradient(
                130deg, var(--red), var(--red-dark) 55%, var(--blue)
            );
        }

        .login-header::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 50%;
            right: -80px;
            top: -95px;
            box-shadow:
                0 0 0 15px rgba(255,255,255,.04),
                0 0 0 30px rgba(255,255,255,.04);
        }

        .logo {
            position: relative;
            z-index: 1;
            width: 76px;
            height: 76px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            color: var(--red);
            background: #fff;
            border: 4px solid rgba(255,255,255,.65);
            border-radius: 50%;
            font-size: 39px;
            box-shadow: 0 8px 20px rgba(0,0,0,.2);
        }

        .login-header h2 {
            position: relative;
            z-index: 1;
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: .7px;
        }

        .login-header p {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 12px;
            color: #fff0f2;
        }

        .login-body {
            padding: 30px;
        }

        .welcome-title {
            margin-bottom: 5px;
            font-size: 21px;
            font-weight: 800;
        }

        .welcome-subtitle {
            margin-bottom: 25px;
            color: #7a8598;
            font-size: 13px;
        }

        .form-label {
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .input-group {
            margin-bottom: 19px;
        }

        .input-group-text {
            color: var(--blue);
            background: #f0f4fb;
            border-color: #dfe6f1;
            border-radius: 10px 0 0 10px;
        }

        .form-control {
            min-height: 47px;
            border-color: #dfe6f1;
            font-size: 13px;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 .2rem rgba(18,61,121,.1);
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .toggle-password {
            min-width: 45px;
            border: 1px solid #dfe6f1;
            border-left: 0;
            color: var(--blue);
            background: #fff;
            border-radius: 0 10px 10px 0;
        }

        .toggle-password:hover {
            background: #f0f4fb;
        }

        .password-input {
            border-radius: 0 !important;
        }

        .btn-login {
            width: 100%;
            min-height: 49px;
            margin-top: 5px;
            color: white;
            background: linear-gradient(
                100deg, var(--red), var(--red-dark)
            );
            border: 0;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 7px 16px rgba(215,25,50,.23);
            transition: .2s;
        }

        .btn-login:hover {
            color: white;
            background: linear-gradient(
                100deg, var(--blue), var(--blue-dark)
            );
            transform: translateY(-2px);
        }

        .login-footer {
            margin-top: 23px;
            padding-top: 18px;
            border-top: 1px solid #edf0f6;
            color: #7d8799;
            font-size: 11px;
            text-align: center;
            line-height: 1.7;
        }

        .login-footer i {
            color: var(--red);
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            .login-body {
                padding: 24px 21px;
            }

            .login-header {
                padding: 28px 20px 24px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-header">
            <div class="logo">
                <i class="bi bi-shield-fill-check"></i>
            </div>

            <h2>BANK SAMPAH</h2>
            <p>SPIDER-MAN EDITION · SAVE OUR PLANET</p>
        </div>

        <div class="login-body">

            <h4 class="welcome-title">Selamat Datang!</h4>
            <p class="welcome-subtitle">
                Masuk ke akun Bank Sampah kamu.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">

                <label for="username" class="form-label">
                    Username
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person-fill"></i>
                    </span>

                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="form-control"
                        placeholder="Masukkan username"
                        value="<?= htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <label for="password" class="form-label">
                    Password
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control password-input"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="btn toggle-password"
                        id="togglePassword"
                        aria-label="Tampilkan password"
                    >
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    LOGIN SEKARANG
                </button>

            </form>

            <div class="login-footer">
                <i class="bi bi-shield-fill-check"></i>
                <strong> Bank Sampah Spider-Man</strong>
                <br>
                Pilah Sampah · Jaga Bumi · Jadi Pahlawan
            </div>

        </div>
    </div>
</div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';

        this.innerHTML = isPassword
            ? '<i class="bi bi-eye-slash-fill"></i>'
            : '<i class="bi bi-eye-fill"></i>';

        this.setAttribute(
            'aria-label',
            isPassword ? 'Sembunyikan password' : 'Tampilkan password'
        );
    });
</script>

</body>
</html>
