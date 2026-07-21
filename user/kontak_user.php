<?php
$page_title = 'Kontak - KSM Education';
$base_css = '<link rel="stylesheet" href="../styles/static_pages.css?v=20260719" />
  <link rel="stylesheet" href="../styles/profile_pages.css?v=20260719" />';
include 'components/header.php';
include 'components/navbar.php';
?>

    <!-- Main Content -->
    <div class="container">

      <!-- Hero -->
      <section class="static-hero">
        <span class="static-hero-tag">Kontak</span>
        <h1>Ada Pertanyaan? Hubungi Kami</h1>
        <p>
          Tim KSM Education siap membantu seputar upload jurnal, opini,
          token, atau kerja sama lainnya.
        </p>
      </section>

      <div class="contact-layout">
        <!-- Info kontak -->
        <div class="contact-info-col">
          <div class="contact-info-card">
            <div class="contact-info-icon"><i data-feather="mail"></i></div>
            <div>
              <h4>Email</h4>
              <p>admin@ksmeducation.id</p>
            </div>
          </div>
          <div class="contact-info-card">
            <div class="contact-info-icon"><i data-feather="phone"></i></div>
            <div>
              <h4>WhatsApp</h4>
              <p>+62 812-3456-7890</p>
            </div>
          </div>
          <div class="contact-info-card">
            <div class="contact-info-icon"><i data-feather="map-pin"></i></div>
            <div>
              <h4>Alamat</h4>
              <p>Jl. Pendidikan No. 1, Jakarta, Indonesia</p>
            </div>
          </div>
          <div class="contact-info-card">
            <div class="contact-info-icon"><i data-feather="clock"></i></div>
            <div>
              <h4>Jam Operasional</h4>
              <p>Senin – Jumat, 09.00 – 17.00 WIB</p>
            </div>
          </div>

          <div class="contact-social">
            <a href="#" aria-label="Instagram"><i data-feather="instagram"></i></a>
            <a href="#" aria-label="Facebook"><i data-feather="facebook"></i></a>
            <a href="#" aria-label="Twitter"><i data-feather="twitter"></i></a>
            <a href="#" aria-label="YouTube"><i data-feather="youtube"></i></a>
          </div>
        </div>

        <!-- Form kontak -->
        <div class="contact-form-col">
          <div class="profile-card">
            <h3 class="profile-form-title">Kirim Pesan</h3>
            <form id="contactForm" class="profile-form">
              <div class="form-group">
                <label for="contactNama">Nama</label>
                <input type="text" id="contactNama" name="name" placeholder="Nama lengkap" required />
              </div>

              <div class="form-group">
                <label for="contactEmail">Email</label>
                <input type="email" id="contactEmail" name="email" placeholder="email@contoh.com" required />
              </div>

              <div class="form-group">
                <label for="contactSubjek">Subjek</label>
                <input type="text" id="contactSubjek" name="subject" placeholder="Topik pesan Anda" required />
              </div>

              <div class="form-group">
                <label for="contactPesan">Pesan</label>
                <textarea id="contactPesan" name="message" rows="5" placeholder="Tulis pesan Anda di sini..." required></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-save-profile">
                  <i data-feather="send"></i>
                  Kirim Pesan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </div>

    <!-- Scripts -->

<?php
$extra_scripts = <<<'EOT'
<script src="../js/script.js?v=2025112910"></script>
    <script src="../js/custom_alerts.js"></script>
    <script src="../js/kontak_user.js?v=20260719"></script>
    <script src="../js/mobile_menu.js?v=20251130"></script>
    <script>
      feather.replace();
    </script>
  
EOT;
include 'components/footer.php';
?>