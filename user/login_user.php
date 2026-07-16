<?php
require_once __DIR__ . '/../services/env_loader.php';
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
    <link rel="stylesheet" href="../styles/fonts.css" />
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico" />
    <link rel="stylesheet" href="../styles/login_user.css" />
    <script src="../js/config.js?v=20260325"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
      window.GOOGLE_CLIENT_ID = "<?php echo htmlspecialchars($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? ''); ?>";
    </script>
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
          <a href="#" class="forgot-password">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login">
          <span class="btn-text">Masuk</span>
          <div class="loading"></div>
        </button>
      </form>

      <!-- Divider -->
      <div class="divider">
        <span>OR</span>
      </div>

      <!-- Social Login -->
      <div class="social-login" style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
        <!-- Official Google Sign-In Button Container -->
        <div id="googleButtonContainer" style="width: 100%; display: flex; justify-content: center; min-height: 40px;"></div>

        <button type="button" class="social-btn" id="googleLogin" style="display: none;">
          Google
        </button>

        <button type="button" class="social-btn" id="facebookLogin" style="display: none;">
          Facebook
        </button>
      </div>

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

    <script src="../js/login_user.js?v=20260325"></script>
    <script>
      feather.replace();
    </script>
  </body>
</html>
