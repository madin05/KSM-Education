<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($page_title ?? 'KSM Education', ENT_QUOTES, 'UTF-8') ?></title>

    <!--
      ===== DARK MODE — INIT AWAL (anti-flash) =====
      Script ini SENGAJA ditaruh paling atas <head>, sebelum semua CSS,
      supaya atribut data-theme="dark" sudah terpasang di <html>
      sebelum browser sempat render apapun. Kalau ditaruh di bawah
      (misalnya di script.js yang dimuat belakangan), akan ada kedipan
      putih sekilas sebelum berubah ke gelap.
    -->
    <script>
      (function () {
        try {
          var theme = localStorage.getItem('ksm_theme');
          if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
          }
        } catch (e) {
          /* localStorage tidak tersedia (mode privat dsb) — abaikan, default light */
        }
      })();
    </script>

    <!-- Preconnect for Google Fonts CDN fallback -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <!-- Montserrat: offline-first via fonts.css, CDN sebagai fallback -->
    <link rel="stylesheet" href="../styles/fonts.css" />
    <link rel="shortcut icon" type="image/x-icon" href="../assets/favicon.ico" />
    <?= $base_css ?? '' ?>
    <link rel="stylesheet" href="../styles/header.css?v=20260323" />
    <link rel="stylesheet" href="../styles/toast.css" />
    <link rel="stylesheet" href="../styles/custom_alerts.css" />
    <link rel="stylesheet" href="../styles/journal.css?v=20260321" />
    <link rel="stylesheet" href="../styles/footer.css" />
    <link rel="stylesheet" href="../styles/skeleton.css" />
    <script src="../js/config.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <?= $extra_head ?? '' ?>
    <!--
      ===== DARK MODE — WAJIB PALING TERAKHIR =====
      Sebelumnya blok dark_mode berada di ATAS $extra_head, sehingga CSS
      halaman yang diinject via $extra_head (mis. my_journals_user.css,
      token_wallet.css, upload_journal_modal.css) memenangkan cascade dan
      sebagian komponen tetap tampil putih walau dark mode aktif.
      Dipindah ke bawah $extra_head supaya override dark selalu menang.
    -->
    <link rel="stylesheet" href="../styles/dark_mode_p2.css?v=20260801" />
    <link rel="stylesheet" href="../styles/dark_mode_p3.css?v=20260801" />
    <link rel="stylesheet" href="../styles/dark_mode.css?v=20260801" />
    <link rel="stylesheet" href="../styles/dark_mode_p4.css?v=20260801" />
    <!--
      PART 5: halaman DETAIL jurnal/opini (explore_*_user.php) + section
      komentar. explore_jurnal_user.css penuh warna putih hardcoded dan
      belum pernah tertutup p2/p3/p4, jadi ditambal di file terpisah ini.
    -->
    <link rel="stylesheet" href="../styles/dark_mode_p5.css?v=20260802" />
  </head>

  <body>