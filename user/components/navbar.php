<?php
// user/components/navbar.php
// PHP_SELF still holds the REAL script name after the clean-URL rewrite
// (the rewrite is internal), so the active-state test below is unchanged.
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <header>
      <div class="header-container">
        <div class="logo">
          <a href="dashboard"><img src="../assets/main_logo.png" alt="Logo" /></a>
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
          <a href="dashboard" class="<?= $current_page === 'dashboard_user.php' ? 'active' : '' ?>">Beranda</a>
          <a href="journals" class="<?= $current_page === 'journals_user.php' ? 'active' : '' ?>">Jurnal</a>
          <a href="opinions" class="<?= $current_page === 'opinions_user.php' ? 'active' : '' ?>">Opini</a>
          <a href="tentang" class="<?= $current_page === 'tentang_user.php' ? 'active' : '' ?>">Tentang</a>
          <a href="kontak" class="<?= $current_page === 'kontak_user.php' ? 'active' : '' ?>">Kontak</a>

        </nav>

        <div class="auth-section" id="navbarAuth">
          <!-- Dynamically populated by script.js -->
        </div>
      </div>
    </header>