<?php
$page_title = 'Profil Saya - KSM Education';
$base_css = '<link rel="stylesheet" href="../styles/profile_pages.css?v=20260719" />';
include 'components/header.php';
include 'components/navbar.php';
?>

    <!-- Main Content -->
    <div class="container">
      <section class="account-page">
        <h2>Profil Saya</h2>

        <div class="profile-card">
          <div class="profile-card-header">
            <div class="profile-avatar-lg" id="profileAvatar">-</div>
            <div class="profile-header-info">
              <h3 id="profileName">Memuat...</h3>
              <p id="profileEmail" class="profile-email">-</p>
              <span class="profile-join-badge" id="profileJoinDate">
                <i data-feather="calendar"></i>
                <span>Bergabung sejak -</span>
              </span>
            </div>
          </div>

          <div class="profile-stats-row">
            <div class="profile-stat">
              <div class="profile-stat-number" id="profileArticleCount">0</div>
              <div class="profile-stat-label">Artikel</div>
            </div>
            <div class="profile-stat">
              <div class="profile-stat-number" id="profileViewCount">0</div>
              <div class="profile-stat-label">Total Views</div>
            </div>
            <div class="profile-stat">
              <div class="profile-stat-number" id="profileTokenCount">0</div>
              <div class="profile-stat-label">Token Saya</div>
            </div>
          </div>
        </div>

        <div class="profile-card">
          <h3 class="profile-form-title">Edit Informasi</h3>
          <form id="profileForm" class="profile-form">
            <div class="form-group">
              <label for="inputNama">Nama Lengkap</label>
              <input type="text" id="inputNama" name="name" placeholder="Nama lengkap Anda" required />
            </div>

            <div class="form-group">
              <label for="inputEmail">Email</label>
              <input type="email" id="inputEmail" name="email" placeholder="email@contoh.com" disabled />
              <small class="form-hint">Email tidak dapat diubah. Hubungi admin jika perlu bantuan.</small>
            </div>

            <div class="form-group">
              <label for="inputBio">Bio Singkat</label>
              <textarea id="inputBio" name="bio" rows="4" placeholder="Ceritakan sedikit tentang diri Anda..."></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-save-profile">
                <i data-feather="save"></i>
                Simpan Perubahan
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>

    <!-- Scripts -->

<?php
$extra_scripts = <<<'EOT'
<script src="../js/script.js?v=2025112910"></script>
    <script src="../js/custom_alerts.js"></script>
    <script src="../js/profil_user.js?v=20260719"></script>
    <script src="../js/mobile_menu.js?v=20251130"></script>
    <script>
      feather.replace();
    </script>
  
EOT;
include 'components/footer.php';
?>