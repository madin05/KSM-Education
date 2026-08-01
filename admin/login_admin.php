<?php
// admin/login_admin.php
// Halaman login khusus administrator. Tidak boleh memakai auth_guard
// (halaman ini justru tujuan redirect dari guard).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login sebagai admin, langsung ke dashboard.
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: dashboard_admin.php');
    exit;
}

// Halaman tujuan setelah login (whitelist nama file di folder admin).
$next = basename((string)($_GET['next'] ?? 'dashboard_admin.php'));
if ($next === '' || $next === 'login_admin.php' || !preg_match('/^[a-z0-9_\-]+\.php$/i', $next) || !file_exists(__DIR__ . '/' . $next)) {
    $next = 'dashboard_admin.php';
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login Admin - KSM Education</title>
    <link rel="stylesheet" href="../styles/fonts.css" />
    <link rel="stylesheet" href="../styles/login_admin.css" />
    <link rel="stylesheet" href="../styles/custom_alerts.css" />
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico" />
    <script src="../js/config.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
      /* Fallback layout minimal agar halaman tetap rapi walau login_admin.css berubah */
      .admin-login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        font-family: "Montserrat", system-ui, sans-serif;
        background: #f4f6fb;
      }
      .admin-login-card {
        width: 100%;
        max-width: 400px;
        background: #fff;
        border-radius: 16px;
        padding: 32px 28px;
        box-shadow: 0 12px 32px rgba(16, 24, 40, 0.12);
      }
      .admin-login-card .login-logo { width: 72px; display: block; margin: 0 auto 12px; }
      .admin-login-card h1 { font-size: 20px; text-align: center; margin: 0 0 4px; }
      .admin-login-card .subtitle { text-align: center; color: #667085; font-size: 13px; margin: 0 0 24px; }
      .admin-login-card label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
      .admin-login-card .form-group { margin-bottom: 16px; }
      .admin-login-card input[type="email"],
      .admin-login-card input[type="password"],
      .admin-login-card input[type="text"] {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        font-size: 14px;
      }
      .admin-login-card input:focus { outline: 2px solid #2563eb; border-color: #2563eb; }
      .password-group { position: relative; }
      .password-group .toggle-password {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        background: none; border: 0; cursor: pointer; padding: 6px; color: #667085;
      }
      .btn-login {
        width: 100%; padding: 12px; border: 0; border-radius: 8px;
        background: #2563eb; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer;
      }
      .btn-login[disabled] { opacity: .6; cursor: not-allowed; }
      .login-alert {
        display: none; margin-bottom: 16px; padding: 10px 12px; border-radius: 8px;
        font-size: 13px; background: #fee4e2; color: #b42318; border: 1px solid #fda29b;
      }
      .login-alert.is-visible { display: block; }
      .back-link { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: #475467; }
    </style>
  </head>
  <body>
    <main class="admin-login-wrapper">
      <div class="admin-login-card">
        <img src="../assets/main_logo.png" alt="KSM Education" class="login-logo" />
        <h1>Login Administrator</h1>
        <p class="subtitle">Masuk untuk mengelola panel KSM Education</p>

        <div class="login-alert" id="loginAlert" role="alert" aria-live="polite"></div>

        <form id="adminLoginForm" novalidate>
          <div class="form-group">
            <label for="adminEmail">Email</label>
            <input
              type="email"
              id="adminEmail"
              name="email"
              autocomplete="username"
              placeholder="admin@gmail.com"
              required
            />
          </div>
          <div class="form-group password-group">
            <label for="adminPassword">Password</label>
            <input
              type="password"
              id="adminPassword"
              name="password"
              autocomplete="current-password"
              placeholder="Masukkan password"
              required
            />
            <button
              type="button"
              class="toggle-password"
              id="togglePassword"
              aria-label="Tampilkan password"
            >
              <i data-feather="eye"></i>
            </button>
          </div>
          <button type="submit" class="btn-login" id="btnAdminLogin">MASUK</button>
        </form>

        <a class="back-link" href="../index.php">&larr; Kembali ke halaman utama</a>
      </div>
    </main>

    <script>
      window.ADMIN_LOGIN_NEXT = <?php echo json_encode($next, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="../js/auth_storage.js?v=20260801"></script>
    <script src="../js/login_admin.js?v=20260801"></script>
    <script>
      if (window.feather) window.feather.replace();
    </script>
  </body>
</html>
