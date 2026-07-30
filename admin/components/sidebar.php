<?php
// admin/components/sidebar.php
?>
    <header>
      <div class="header-container">
        <div class="logo">
          <a href="dashboard_admin.php"><img src="../assets/main_logo.png" alt="Logo" /></a>
        </div>

        <div class="header-right">
          <div class="mobile-auth-header" id="mobileAuthHeader"></div>
          <button
            class="hamburger-menu"
            aria-label="Toggle menu"
            aria-expanded="false"
            type="button"
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>

        <nav>
          <a href="dashboard_admin.php">HOME</a>
          <div class="nav-dropdown">
            <button class="nav-link has-caret" type="button">
              ARTIKEL
              <svg
                class="caret"
                viewBox="0 0 24 24"
                width="16"
                height="16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <div class="dropdown-menu">
              <a href="journals.php">Artikel Jurnal</a>
              <a href="opinions.php">Artikel Opini</a>
              <a href="review_journals.php">Review Kiriman</a>
            </div>
          </div>
          <a href="dashboard_admin.php#upload">UPLOAD</a>
          <a href="review_journals.php">REVIEW</a>
          <a href="comments.php">KOMENTAR</a>
          <a href="contact_messages.php">KONTAK</a>
          <a href="token_requests.php">TOKEN</a>
          <a href="visitor_analytics.php">ANALITIK</a>
        </nav>

        <div class="auth-section">
          <button class="btn-register">LOGIN</button>
        </div>
      </div>
    </header>
<?php include 'login_modal.php'; ?>
