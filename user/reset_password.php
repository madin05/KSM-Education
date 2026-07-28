<?php $page_title = 'Reset Password - KSM Education'; ?>
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
      <h2>Atur Password Baru</h2>
      <p>Tautan reset hanya berlaku selama 30 menit.</p>
    </div>
    <div id="alertBox" class="alert"></div>
    <form id="resetPasswordForm" class="login-form">
      <div class="form-group"><input type="password" id="newPassword" placeholder="Password baru (minimal 8 karakter)" minlength="8" required /></div>
      <div class="form-group"><input type="password" id="confirmPassword" placeholder="Konfirmasi password baru" minlength="8" required /></div>
      <button type="submit" class="btn-login">Simpan Password</button>
    </form>
    <div class="signup-link"><a href="login_user.php">Kembali ke login</a></div>
  </main>
  <script src="../js/password_reset.js"></script>
</body>
</html>