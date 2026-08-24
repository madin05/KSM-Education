<?php
$current_page = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
$request_uri = strtolower($_SERVER['REQUEST_URI'] ?? '');

$is_dashboard = ($current_page === 'dashboard_user.php' || strpos($request_uri, '/dashboard') !== false);
$is_journals  = ($current_page === 'journals_user.php'  || strpos($request_uri, '/journals') !== false);
$is_opinions  = ($current_page === 'opinions_user.php'  || strpos($request_uri, '/opinions') !== false);
$is_kontak    = ($current_page === 'kontak_user.php'    || strpos($request_uri, '/kontak') !== false);
?>
<header>
  <div class="header-container">
    <div class="logo">
      <a href="dashboard"><img src="../assets/main_logo.png" alt="Logo" width="100" height="32" /></a>
    </div>


    <div class="header-right">
      <div class="mobile-auth-header" id="mobileAuthHeader"></div>
      <button class="hamburger-menu" aria-label="Toggle menu" aria-expanded="false" type="button">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>

    <nav>
      <a href="dashboard" class="<?= $is_dashboard ? 'active' : '' ?>">Beranda</a>
      <a href="journals" class="<?= $is_journals ? 'active' : '' ?>">Jurnal</a>
      <a href="opinions" class="<?= $is_opinions ? 'active' : '' ?>">Opini</a>
      <a href="kontak" class="<?= $is_kontak ? 'active' : '' ?>">Kontak</a>
    </nav>

    <div class="auth-section" id="navbarAuth">
      <!-- Dynamically populated by script.js -->
    </div>
  </div>
</header>