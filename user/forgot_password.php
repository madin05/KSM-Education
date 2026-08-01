<?php $page_title = 'Lupa Password - KSM Education'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
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
  <link rel="stylesheet" href="../styles/fonts.css" />

  <link rel="stylesheet" href="../styles/login_user.css" />
  <!-- dark_mode_p4.css dimuat setelah login_user.css supaya override menang -->
  <link rel="stylesheet" href="../styles/dark_mode_p4.css?v=20260801" />
  <script src="../js/config.js"></script>

</head>
<body>
  <main class="login-wrapper">
    <div class="login-header">
      <img src="../assets/main_logo.png" alt="KSM Education" class="login-logo" />
      <h2>Lupa Password</h2>
      <p>Masukkan email akun Anda.</p>
    </div>
    <div id="alertBox" class="alert"></div>
    <form id="forgotPasswordForm" class="login-form">
      <div class="form-group"><input type="email" id="resetEmail" placeholder="Email" required /></div>
      <button type="submit" class="btn-login">Kirim Tautan Reset</button>
    </form>
    <div class="signup-link"><a href="login_user.php">Kembali ke login</a></div>
  </main>
  <script src="../js/password_reset.js"></script>
</body>
</html>