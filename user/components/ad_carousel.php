<?php
/**
 * components/ad_carousel.php
 * Slider iklan/promosi di atas section Statistik.
 * Auto-geser tiap beberapa detik, ada panah + dots, berhenti saat di-hover.
 * Tiap slide sekarang punya panel gambar di sisi kanan.
 *
 * Include di halaman manapun (biasanya dashboard_user.php), sebelum
 * <section class="statistics">.
 *
 * Membutuhkan:
 *  - styles/ad_carousel.css
 *  - js/ad_carousel.js
 *
 * // TODO backend: slide di bawah ini masih hardcode (dummy).
 * // Idealnya nanti diambil dari tabel `ads` yang admin kelola sendiri
 * // (judul, deskripsi, warna/gambar, link CTA, urutan tampil, status aktif),
 * // lalu di-loop dengan foreach dari hasil query di sini.
 */
$ksm_ad_slides = [
  [
    'title'    => 'Beli Token, Upload Karyamu Sekarang!',
    'desc'     => 'Jurnal & opini kamu bisa tayang di KSM Education hari ini juga. Token bisa dibeli langsung via WhatsApp admin.',
    'cta_text' => 'Beli Token',
    'cta_attr' => 'data-ksm-open-buy-token',
    'gradient' => 'linear-gradient(135deg, #2c3e50 0%, #34495e 100%)',
    'color'    => '#2c3e50',
    'icon'     => 'zap',
    'image' => '../assets/image.png',
  ],
  [
    'title'    => 'Gabung Jadi Kontributor Tetap',
    'desc'     => 'Konsisten menulis jurnal & opini berkualitas membuka kesempatan menjadi kontributor tetap KSM Education.',
    'cta_text' => 'Upload Sekarang',
    'cta_attr' => 'data-ksm-open-upload',
    'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%)',
    'color'    => '#2563eb',
    'icon'     => 'edit-3',
    'image' => '../assets/image.png',
  ],
  [
    'title'    => 'Jangkau Lebih Banyak Pembaca',
    'desc'     => 'Bagikan tulisanmu ke media sosial langsung dari halaman artikel — makin banyak dibaca, makin banyak dampaknya.',
    'cta_text' => 'Lihat Artikel Terbaru',
    'cta_attr' => 'onclick="document.querySelector(\'.articles-section\').scrollIntoView({behavior:\'smooth\'})"',
    'gradient' => 'linear-gradient(135deg, #9b59b6 0%, #b57edc 100%)',
    'color'    => '#8e44ad',
    'icon'     => 'share-2',
    'image'    => 'https://images.unsplash.com/photo-1611926653458-09294b3142bf?w=700&h=500&fit=crop',
  ],
];
?>
<div class="ksm-ad-carousel" id="ksmAdCarousel" aria-roledescription="carousel" aria-label="Iklan dan Promosi">
  <div class="ksm-ad-track" id="ksmAdTrack">
    <?php foreach ($ksm_ad_slides as $slide): ?>
      <div class="ksm-ad-slide" style="background:<?= htmlspecialchars($slide['gradient']) ?>; --slide-color: <?= htmlspecialchars($slide['color']) ?>;">
        <div class="ksm-ad-slide-icon">
          <i data-feather="<?= htmlspecialchars($slide['icon']) ?>"></i>
        </div>
        <div class="ksm-ad-slide-text">
          <h3><?= htmlspecialchars($slide['title']) ?></h3>
          <p><?= htmlspecialchars($slide['desc']) ?></p>
        </div>
        <button type="button" class="ksm-ad-cta" <?= $slide['cta_attr'] ?>>
          <?= htmlspecialchars($slide['cta_text']) ?>
          <i data-feather="arrow-right"></i>
        </button>
        <div class="ksm-ad-slide-image" style="background-image: url('<?= htmlspecialchars($slide['image']) ?>');" role="img" aria-label="<?= htmlspecialchars($slide['title']) ?>"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <button type="button" class="ksm-ad-arrow ksm-ad-arrow-prev" id="ksmAdPrev" aria-label="Slide sebelumnya">
    <i data-feather="chevron-left"></i>
  </button>
  <button type="button" class="ksm-ad-arrow ksm-ad-arrow-next" id="ksmAdNext" aria-label="Slide berikutnya">
    <i data-feather="chevron-right"></i>
  </button>

  <div class="ksm-ad-dots" id="ksmAdDots">
    <?php foreach ($ksm_ad_slides as $i => $slide): ?>
      <button
        type="button"
        class="ksm-ad-dot<?= $i === 0 ? ' active' : '' ?>"
        data-index="<?= $i ?>"
        aria-label="Ke slide <?= $i + 1 ?>"
      ></button>
    <?php endforeach; ?>
  </div>
</div>