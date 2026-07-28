<?php $page_title = 'Lupa Password - KSM Education'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="../styles/fonts.css" />
  <link rel="stylesheet" href="../styles/login_user.css" />
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