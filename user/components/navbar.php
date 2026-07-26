<?php
// user/components/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <header>
      <div class="header-container">
        <div class="logo">
          <a href="dashboard_user.php"><img src="../assets/main_logo.png" alt="Logo" /></a>
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
          <a href="dashboard_user.php" class="<?= $current_page === 'dashboard_user.php' ? 'active' : '' ?>">Beranda</a>
          <a href="journals_user.php" class="<?= $current_page === 'journals_user.php' ? 'active' : '' ?>">Jurnal</a>
          <a href="opinions_user.php" class="<?= $current_page === 'opinions_user.php' ? 'active' : '' ?>">Opini</a>
          <a href="tentang_user.php" class="<?= $current_page === 'tentang_user.php' ? 'active' : '' ?>">Tentang</a>
          <a href="kontak_user.php" class="<?= $current_page === 'kontak_user.php' ? 'active' : '' ?>">Kontak</a>
        </nav>

        <div class="auth-section" id="navbarAuth">
          <!-- Dynamically populated by script.js -->
        </div>
      </div>
    </header>