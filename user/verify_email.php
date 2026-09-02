<?php
$page_title = 'Verifikasi Email - KSM Education';
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verifikasi Email - KSM Education</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <!--
      ===== DARK MODE — INIT AWAL (anti-flash) =====
      Halaman auth tidak memakai user/components/header.php, jadi init
      tema harus ditulis ulang di sini.
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
    <link rel="stylesheet" href="../styles/base/fonts.css" />

    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico" />
    <link rel="stylesheet" href="../styles/user/login_user.css" />
    <!-- dark_mode_p4.css dimuat setelah login_user.css supaya override menang -->
    <link rel="stylesheet" href="../styles/base/dark_mode/dark_mode_p4.css?v=20260801" />
    <script src="../js/config.js?v=20260325"></script>

    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .user-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 10px;
        }
        #otpCode {
            letter-spacing: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
        }
        .btn-resend {
            background: none;
            border: none;
            color: #0369a1;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }
        .btn-resend:disabled {
            color: #94a3b8;
            cursor: not-allowed;
        }
        .resend-row {
            text-align: center;
            margin-top: 14px;
            font-size: 14px;
        }
    </style>
  </head>

  <body>
    <div class="login-wrapper">
      <!-- Header -->
      <div class="login-header">
        <img src="../assets/main_logo.png" alt="KSM Education Logo" class="login-logo" />
        <br>
        <span class="user-badge">Verifikasi Email</span>
        <h2>Masukkan Kode OTP</h2>
        <p>Kami telah mengirim kode 6 digit ke email Anda. Kode berlaku 5 menit.</p>
      </div>

      <!-- Alert Box -->
      <div id="alertBox" class="alert"></div>

      <!-- Verify Form -->
      <form id="verifyForm" class="login-form">
        <div class="form-group">
          <input
            type="email"
            id="verifyEmail"
            placeholder="Alamat Email"
            autocomplete="email"
            required
          />
        </div>

        <div class="form-group">
          <input
            type="text"
            id="otpCode"
            placeholder="000000"
            inputmode="numeric"
            maxlength="6"
            pattern="\d{6}"
            autocomplete="one-time-code"
            required
          />
        </div>

        <button type="submit" class="btn-login">
          <span class="btn-text">Verifikasi</span>
          <div class="loading"></div>
        </button>
      </form>

      <div class="resend-row">
        Tidak menerima kode?
        <button type="button" id="resendBtn" class="btn-resend">Kirim Ulang OTP</button>
      </div>

      <!-- Footer Links -->
      <div class="signup-link">
        Sudah terverifikasi? <a href="login">Masuk di sini</a>
      </div>

      <div class="back-to-home">
        <a href="dashboard">
          <i data-feather="arrow-left"></i>
          Kembali ke Beranda
        </a>
      </div>
    </div>

    <script src="../js/auth_storage.js?v=20260801"></script>
    <script src="../js/verify_email.js?v=20260802"></script>
    <script>
      feather.replace();
    </script>
  </body>
</html>
