<?php
$page_title = 'User Login - KSM Education';
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Login - KSM Education</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <!--
      ===== DARK MODE — INIT AWAL (anti-flash) =====
      Halaman auth tidak memakai user/components/header.php, jadi init
      tema harus ditulis ulang di sini. Tanpa ini, user yang sudah memilih
      dark mode akan melihat halaman login tetap putih.
    -->
    <script>
      (function () {
        try {
          if (localStorage.getItem('ksm_theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
          }
        } catch (e) {
          /* localStorage tidak tersedia — abaikan, default light */
        }
      })();
    </script>
    <link rel="stylesheet" href="../styles/fonts.css" />

    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico" />
    <link rel="stylesheet" href="../styles/login_user.css" />
    <!-- dark_mode_p4.css dimuat setelah login_user.css supaya override menang -->
    <link rel="stylesheet" href="../styles/dark_mode_p4.css?v=20260801" />
    <script src="../js/config.js?v=20260325"></script>

    <script src="https://unpkg.com/feather-icons"></script>
  </head>

  <body>
    <div class="login-wrapper">
      <!-- Header -->
      <div class="login-header">
        <img src="../assets/main_logo.png" alt="KSM Education Logo" class="login-logo" />
        <br>
        <h2>Selamat Datang!</h2>
        <p>Masuk ke akun pengguna Anda</p>
      </div>

      <!-- Alert Box -->
      <div id="alertBox" class="alert"></div>

      <!-- Login Form -->
      <form id="loginForm" class="login-form">
        <div class="form-group">
          <input
            type="email"
            id="loginEmail"
            placeholder="Masukan Email"
            required
          />
        </div>

        <div class="form-group password-group">
          <input
            type="password"
            id="loginPassword"
            placeholder="Masukan Password"
            required
          />
          <button type="button" class="toggle-password" id="togglePassword">
            <i data-feather="eye"></i>
          </button>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" id="rememberMe" />
            <span>Remember Me</span>
          </label>
          <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login">
          <span class="btn-text">Masuk</span>
          <div class="loading"></div>
        </button>
      </form>

      <!-- OAuth buttons stay hidden until provider credentials, callback
           validation, account linking, and backend endpoints are configured. -->

      <!-- Footer Links -->
      <div class="signup-link">
        Belum punya akun? <a href="register_user.php">Daftar Sekarang</a>
      </div>

      <div class="back-to-home">
        <a href="../user/dashboard_user.php">
          <i data-feather="arrow-left"></i>
          Kembali ke Beranda
        </a>
      </div>
    </div>

    <script src="../js/auth_storage.js?v=20260801"></script>
    <script src="../js/login_user.js?v=20260801"></script>
    <script>
      feather.replace();
    </script>
  </body>
</html>
