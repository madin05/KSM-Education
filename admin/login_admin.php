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
  </head>
  <body class="admin-login-body">
    <main class="admin-login-wrapper">
      <div class="admin-login-card">
        <div class="admin-badge-container">
          <span class="admin-badge"><i data-feather="shield"></i> Admin Portal</span>
        </div>
        <img src="../assets/main_logo.png" alt="KSM Education" class="login-logo" />
        <h1>Login Administrator</h1>
        <p class="subtitle">Masuk untuk mengelola panel KSM Education</p>

        <div class="login-alert" id="loginAlert" role="alert" aria-live="polite"></div>

        <form id="adminLoginForm" novalidate>
          <div class="form-group">
            <label for="adminEmail">Email</label>
            <div class="input-with-icon">
              <i data-feather="mail" class="field-icon"></i>
              <input
                type="email"
                id="adminEmail"
                name="email"
                autocomplete="username"
                placeholder="ksmedu2025@gmail.com"
                required
              />
            </div>
          </div>
          <div class="form-group password-group">
            <label for="adminPassword">Password</label>
            <div class="input-with-icon">
              <i data-feather="lock" class="field-icon"></i>
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
          </div>
          <button type="submit" class="btn-login" id="btnAdminLogin">
            <i data-feather="log-in"></i> MASUK
          </button>
        </form>

        <div class="back-link-container">
          <a class="back-link" href="../index.php">
            <i data-feather="arrow-left"></i> Kembali ke halaman utama
          </a>
        </div>
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
