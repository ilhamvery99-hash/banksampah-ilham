<?php

session_start();

// Kalau sudah login, langsung ke dashboard
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit();
}

// Kalau belum login, ke halaman login
header("Location: login.php");
exit();

?>