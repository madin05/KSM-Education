<?php
$page_title = 'Pengaturan - KSM Education';
$base_css = '<link rel="stylesheet" href="../styles/profile_pages.css?v=20260719" />';
include 'components/header.php';
include 'components/navbar.php';
?>

    <!-- Main Content -->
    <div class="container">
      <section class="account-page">
        <h2>Pengaturan</h2>

        <!-- Tampilan -->
        <div class="profile-card">
          <h3 class="profile-form-title">Tampilan</h3>
          <div class="settings-toggle-list">
            <div class="settings-toggle-row">
              <div class="settings-toggle-text">
                <strong>Mode Gelap</strong>
                <p>Ubah tampilan seluruh halaman jadi warna gelap. Pilihan tersimpan di akun Anda; perangkat ini hanya menyimpan cache tampilan.</p>
              </div>
              <label class="switch">
                <input type="checkbox" id="toggleDarkMode" />
                <span class="switch-slider"></span>
              </label>
            </div>
          </div>
        </div>

        <!-- Ganti Password -->
        <div class="profile-card">
          <h3 class="profile-form-title">Ganti Password</h3>
          <form id="passwordForm" class="profile-form">
            <div class="form-group">
              <label for="inputPasswordLama">Password Lama</label>
              <input type="password" id="inputPasswordLama" name="old_password" placeholder="Masukkan password lama" required />
            </div>

            <div class="form-group">
              <label for="inputPasswordBaru">Password Baru</label>
              <input type="password" id="inputPasswordBaru" name="new_password" placeholder="Minimal 8 karakter" required minlength="8" />
            </div>

            <div class="form-group">
              <label for="inputPasswordKonfirmasi">Konfirmasi Password Baru</label>
              <input type="password" id="inputPasswordKonfirmasi" name="confirm_password" placeholder="Ulangi password baru" required minlength="8" />
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-save-profile">
                <i data-feather="lock"></i>
                Ubah Password
              </button>
            </div>
          </form>
        </div>

        <!-- Preferensi Notifikasi -->
        <div class="profile-card">
          <h3 class="profile-form-title">Preferensi Notifikasi</h3>
          <div class="settings-toggle-list">
            <div class="settings-toggle-row">
              <div class="settings-toggle-text">
                <strong>Email Artikel Baru</strong>
                <p>Dapatkan email saat ada jurnal/opini baru dari kontributor lain.</p>
              </div>
              <label class="switch">
                <input type="checkbox" id="toggleNewArticle" checked />
                <span class="switch-slider"></span>
              </label>
            </div>

            <div class="settings-toggle-row">
              <div class="settings-toggle-text">
                <strong>Notifikasi Status Upload</strong>
                <p>Diberitahu saat jurnal/opini Anda disetujui atau ditolak admin.</p>
              </div>
              <label class="switch">
                <input type="checkbox" id="toggleUploadStatus" checked />
                <span class="switch-slider"></span>
              </label>
            </div>

            <div class="settings-toggle-row">
              <div class="settings-toggle-text">
                <strong>Promo &amp; Info Token</strong>
                <p>Info promo pembelian token dan penawaran khusus lainnya.</p>
              </div>
              <label class="switch">
                <input type="checkbox" id="togglePromo" />
                <span class="switch-slider"></span>
              </label>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" id="btnSaveNotif" class="btn-save-profile">
              <i data-feather="save"></i>
              Simpan Preferensi
            </button>
          </div>
        </div>

        <!-- Danger Zone -->
        <div class="profile-card profile-card--danger">
          <h3 class="profile-form-title profile-form-title--danger">Zona Berbahaya</h3>
          <p class="danger-zone-desc">
            Menghapus akun akan menonaktifkan akses Anda secara permanen.
            Jurnal &amp; opini yang sudah dipublikasikan akan tetap tayang dan
            riwayat kepemilikannya tetap dipertahankan.
          </p>
          <button type="button" id="btnDeleteAccount" class="btn-delete-account">
            <i data-feather="trash-2"></i>
            Hapus Akun Saya
          </button>
        </div>
      </section>
    </div>

    <!-- Scripts -->

<?php
$extra_scripts = <<<'EOT'
<script src="../js/script.js?v=2025112910"></script>
    <script src="../js/custom_alerts.js"></script>
    <script src="../js/pengaturan_user.js?v=20260719"></script>
    <script src="../js/mobile_menu.js?v=20251130"></script>
    <script>
      feather.replace();
    </script>
  
EOT;
include 'components/footer.php';
?>