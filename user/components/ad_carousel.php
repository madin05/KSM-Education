<?php
/**
 * components/ad_carousel.php
 * Slider iklan/promosi di atas section Statistik.
 * Auto-geser tiap beberapa detik, ada panah + dots, berhenti saat di-hover.
 * Setiap slide sekarang full-bleed foto dengan gradient fade di sisi kiri
 * supaya teks tetap kebaca jelas.
 *
 * Include di halaman manapun (biasanya dashboard_user.php), sebelum
 * <section class="statistics">.
 *
 * Membutuhkan:
 *  - styles/ad_carousel.css
 *  - js/ad_carousel.js (TIDAK PERLU DIUBAH — id/class track & slide tetap sama)
 *
 * // TODO backend: slide di bawah ini masih hardcode (dummy).
 * // Idealnya nanti diambil dari tabel `ads` yang admin kelola sendiri
 * // (judul, deskripsi, gambar, link CTA, urutan tampil, status aktif),
 * // lalu di-loop dengan foreach dari hasil query di sini.
 */
$ksm_ad_slides = [
  [
    'title'    => 'Webinar Nasional: Menulis Jurnal Berkualitas',
    'desc'     => 'Gratis untuk seluruh anggota KSM Education, kuota terbatas.',
    'cta_text' => 'Ikuti Webinar',
    'cta_attr' => 'onclick="window.location.href=\'#\'"',
    'image'    => '../assets/image.png',
  ],
  [
    'title'    => 'Beli Token, Upload Karyamu Sekarang!',
    'desc'     => 'Jurnal & opini kamu bisa tayang di KSM Education hari ini juga. Token bisa dibeli lewat bot Telegram KSMedu.',
    'cta_text' => 'Beli Token',
    'cta_attr' => 'data-ksm-open-buy-token',
    'image'    => '../assets/image.png',
  ],
  [
    'title'    => 'Gabung Jadi Kontributor Tetap',
    'desc'     => 'Konsisten menulis jurnal & opini berkualitas membuka kesempatan menjadi kontributor tetap KSM Education.',
    'cta_text' => 'Upload Sekarang',
    'cta_attr' => 'data-ksm-open-upload',
    'image'    => '../assets/image.png',
  ],
];
?>
<div class="ksm-ad-carousel" id="ksmAdCarousel" aria-roledescription="carousel" aria-label="Iklan dan Promosi">
  <div class="ksm-ad-track" id="ksmAdTrack">
    <?php foreach ($ksm_ad_slides as $slide): ?>
      <div class="ksm-ad-slide" style="background-image: linear-gradient(90deg, rgba(20,26,34,0.88) 15%, rgba(20,26,34,0.5) 48%, rgba(20,26,34,0.05) 78%), url('<?= htmlspecialchars($slide['image']) ?>');">
        <div class="ksm-ad-slide-text">
          <h3><?= htmlspecialchars($slide['title']) ?></h3>
          <p><?= htmlspecialchars($slide['desc']) ?></p>
          <button type="button" class="ksm-ad-cta" <?= $slide['cta_attr'] ?>>
            <?= htmlspecialchars($slide['cta_text']) ?>
          </button>
        </div>
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