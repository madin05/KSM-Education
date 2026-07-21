<?php
$page_title = 'Tentang Kami - KSM Education';
$base_css = '<link rel="stylesheet" href="../styles/static_pages.css?v=20260719" />';
include 'components/header.php';
include 'components/navbar.php';
?>

    <!-- Main Content -->
    <div class="container">

      <!-- Hero -->
      <section class="static-hero">
        <span class="static-hero-tag">Tentang Kami</span>
        <h1>Mendorong Budaya Menulis &amp; Berbagi Ilmu</h1>
        <p>
          KSM Education adalah wadah bagi pelajar, mahasiswa, dan pendidik
          untuk mempublikasikan jurnal ilmiah dan opini secara mudah,
          cepat, dan terbuka untuk siapa saja.
        </p>
      </section>

      <!-- Visi & Misi -->
      <section class="static-section">
        <div class="vision-mission-grid">
          <div class="vm-card">
            <div class="vm-icon"><i data-feather="target"></i></div>
            <h3>Visi</h3>
            <p>
              Menjadi platform publikasi jurnal &amp; opini terdepan yang
              menumbuhkan budaya literasi ilmiah di kalangan pelajar dan
              masyarakat umum Indonesia.
            </p>
          </div>
          <div class="vm-card">
            <div class="vm-icon"><i data-feather="compass"></i></div>
            <h3>Misi</h3>
            <ul class="vm-list">
              <li>Memudahkan proses upload &amp; publikasi karya tulis ilmiah.</li>
              <li>Membangun komunitas kontributor yang aktif dan saling mendukung.</li>
              <li>Menjaga kualitas &amp; orisinalitas setiap karya yang tayang.</li>
              <li>Menyediakan akses gratis untuk membaca seluruh artikel.</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Angka -->
      <section class="static-section">
        <h2 class="section-heading">KSM Education dalam Angka</h2>
        <div class="stat-highlight-grid">
          <div class="stat-highlight-card">
            <div class="stat-highlight-number" id="aboutArticleCount">-</div>
            <div class="stat-highlight-label">Artikel Terpublikasi</div>
          </div>
          <div class="stat-highlight-card">
            <div class="stat-highlight-number" id="aboutVisitorCount">-</div>
            <div class="stat-highlight-label">Total Pengunjung</div>
          </div>
          <div class="stat-highlight-card">
            <div class="stat-highlight-number">100%</div>
            <div class="stat-highlight-label">Gratis untuk Pembaca</div>
          </div>
        </div>
      </section>

      <!-- Kenapa KSM -->
      <section class="static-section">
        <h2 class="section-heading">Kenapa Memilih KSM Education?</h2>
        <div class="feature-grid">
          <div class="feature-card">
            <div class="feature-icon"><i data-feather="zap"></i></div>
            <h4>Upload Cepat</h4>
            <p>Proses publikasi jurnal &amp; opini yang ringkas, cukup dengan sistem token.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon"><i data-feather="users"></i></div>
            <h4>Komunitas Aktif</h4>
            <p>Terhubung dengan penulis lain dan dapatkan lebih banyak pembaca untuk karyamu.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon"><i data-feather="shield"></i></div>
            <h4>Kredibel &amp; Terkurasi</h4>
            <p>Setiap karya melalui proses tinjauan admin sebelum tayang ke publik.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon"><i data-feather="share-2"></i></div>
            <h4>Mudah Dibagikan</h4>
            <p>Bagikan tulisanmu ke media sosial langsung dari halaman artikel.</p>
          </div>
        </div>
      </section>

      <!-- CTA -->
      <section class="static-cta">
        <h3>Siap membagikan karyamu?</h3>
        <p>Bergabunglah dengan ribuan kontributor lain di KSM Education.</p>
        <div class="static-cta-actions">
          <button type="button" class="static-btn-primary" data-ksm-open-upload>
            <i data-feather="upload"></i>
            Upload Jurnal Sekarang
          </button>
          <a href="kontak_user.php" class="static-btn-outline">
            <i data-feather="mail"></i>
            Hubungi Kami
          </a>
        </div>
      </section>

    </div>

    <!-- Scripts -->

<?php
$extra_scripts = <<<'EOT'
<script src="../js/script.js?v=20260721"></script>
    <script src="../js/custom_alerts.js"></script>
    <script src="../js/tentang_user.js?v=20260719"></script>
    <script src="../js/mobile_menu.js?v=20251130"></script>
    <script>
      feather.replace();
    </script>
  
EOT;
include 'components/footer.php';
?>