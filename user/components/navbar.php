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

        <?php
        if ($current_page === 'dashboard_user.php'):
        ?>
        <div class="navbar-search-container">
          <input type="checkbox" class="navbar-search-checkbox" id="navbarSearchToggle" checked>
          <div class="navbar-search-mainbox">
              <div class="navbar-search-icon-container">
                  <svg viewBox="0 0 512 512" height="1em" xmlns="http://www.w3.org/2000/svg" class="navbar-search-icon"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
              </div>
              <input type="text" class="navbar-search-input" id="navbarSearchInput" placeholder="Cari artikel..." autocomplete="off">
          </div>
        </div>
        <?php endif; ?>

        <div class="auth-section" id="navbarAuth">
          <!-- Dynamically populated by script.js -->
        </div>
      </div>
    </header>