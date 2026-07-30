<?php
// admin/components/sidebar.php
// Navbar panel admin. Menu dikelompokkan menjadi beberapa dropdown (pola sama
// seperti dropdown "ARTIKEL" sebelumnya) agar tidak lagi berupa deretan tautan
// panjang. Halaman aktif ditandai otomatis dari nama file yang sedang dibuka.

$current_page = basename($_SERVER['PHP_SELF']);

/**
 * Definisi menu: setiap grup punya label, ikon, dan daftar item.
 * key 'badge' = id elemen badge yang diisi js/admin_nav.js dari
 * services/admin/dashboard_stats.php.
 */
$nav_groups = [
    [
        'label' => 'KONTEN',
        'icon'  => 'book-open',
        'items' => [
            ['file' => 'journals.php',         'label' => 'Artikel Jurnal', 'desc' => 'Kelola & terbitkan jurnal',   'icon' => 'book'],
            ['file' => 'opinions.php',         'label' => 'Artikel Opini',  'desc' => 'Kelola & terbitkan opini',    'icon' => 'edit-3'],
            ['file' => 'dashboard_admin.php',  'hash' => '#upload', 'label' => 'Upload Artikel', 'desc' => 'Unggah konten baru', 'icon' => 'upload-cloud'],
        ],
    ],
    [
        'label' => 'MODERASI',
        'icon'  => 'shield',
        'items' => [
            ['file' => 'review_journals.php',  'label' => 'Review Kiriman', 'desc' => 'Antrean jurnal & opini masuk', 'icon' => 'inbox',        'badge' => 'navBadgeReview'],
            ['file' => 'comments.php',         'label' => 'Komentar',       'desc' => 'Moderasi komentar pembaca',    'icon' => 'message-square'],
            ['file' => 'contact_messages.php', 'label' => 'Pesan Kontak',   'desc' => 'Kotak masuk & balasan',        'icon' => 'mail',         'badge' => 'navBadgeContact'],
        ],
    ],
    [
        'label' => 'OPERASIONAL',
        'icon'  => 'activity',
        'items' => [
            ['file' => 'token_requests.php',    'label' => 'Verifikasi Token', 'desc' => 'Top-up manual & ledger',   'icon' => 'credit-card', 'badge' => 'navBadgeToken'],
            ['file' => 'visitor_analytics.php', 'label' => 'Analitik',         'desc' => 'Statistik pengunjung',     'icon' => 'bar-chart-2'],
        ],
    ],
];

// Cek apakah salah satu item dalam grup adalah halaman aktif.
$group_is_active = static function (array $group) use ($current_page): bool {
    foreach ($group['items'] as $item) {
        // 'Upload' menunjuk ke dashboard; jangan ikut menandai grup saat di dashboard.
        if (isset($item['hash'])) {
            continue;
        }
        if ($item['file'] === $current_page) {
            return true;
        }
    }
    return false;
};
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

        <nav class="admin-nav" aria-label="Navigasi admin">
          <a
            href="dashboard_admin.php"
            class="<?php echo $current_page === 'dashboard_admin.php' ? 'active' : ''; ?>"
            <?php echo $current_page === 'dashboard_admin.php' ? 'aria-current="page"' : ''; ?>
          >
            <i data-feather="home"></i>
            HOME
          </a>

          <?php foreach ($nav_groups as $group): ?>
            <?php $active = $group_is_active($group); ?>
            <div class="nav-dropdown<?php echo $active ? ' has-active' : ''; ?>">
              <button
                class="nav-link has-caret"
                type="button"
                aria-expanded="false"
                aria-haspopup="true"
              >
                <i data-feather="<?php echo $group['icon']; ?>"></i>
                <?php echo $group['label']; ?>
                <span
                  class="nav-dot"
                  data-nav-dot="<?php echo strtolower($group['label']); ?>"
                  aria-hidden="true"
                  hidden
                ></span>
                <svg
                  class="caret"
                  viewBox="0 0 24 24"
                  width="16"
                  height="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>

              <div class="dropdown-menu" role="menu">
                <span class="nav-menu-label"><?php echo $group['label']; ?></span>
                <?php foreach ($group['items'] as $item): ?>
                  <?php
                  $href      = $item['file'] . ($item['hash'] ?? '');
                  $is_active = !isset($item['hash']) && $item['file'] === $current_page;
                  ?>
                  <a
                    href="<?php echo $href; ?>"
                    class="nav-menu-item<?php echo $is_active ? ' active' : ''; ?>"
                    role="menuitem"
                    <?php echo $is_active ? 'aria-current="page"' : ''; ?>
                  >
                    <span class="nav-menu-icon"><i data-feather="<?php echo $item['icon']; ?>"></i></span>
                    <span class="nav-menu-text">
                      <strong><?php echo $item['label']; ?></strong>
                      <small><?php echo $item['desc']; ?></small>
                    </span>
                    <?php if (isset($item['badge'])): ?>
                      <span
                        class="nav-badge"
                        id="<?php echo $item['badge']; ?>"
                        data-nav-group="<?php echo strtolower($group['label']); ?>"
                      ></span>
                    <?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </nav>

        <div class="auth-section">
          <button class="btn-register">LOGIN</button>
        </div>
      </div>
    </header>
<?php include 'login_modal.php'; ?>
