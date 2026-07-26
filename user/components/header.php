<?php
// user/components/header.php
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($page_title) ? $page_title : 'KSM Education'; ?></title>

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
    <?php if (isset($base_css)) echo $base_css; ?>
    <link rel="stylesheet" href="../styles/header.css?v=20260323" />
    <link rel="stylesheet" href="../styles/toast.css" />
    <link rel="stylesheet" href="../styles/custom_alerts.css" />
    <link rel="stylesheet" href="../styles/journal.css?v=20260321" />
    <link rel="stylesheet" href="../styles/footer.css" />
    <link rel="stylesheet" href="../styles/skeleton.css" />
    <link rel="stylesheet" href="../styles/dark_mode_p2.css?v=20260722" />
    <link rel="stylesheet" href="../styles/dark_mode_p3.css?v=20260722" />
    <link rel="stylesheet" href="../styles/dark_mode.css?v=20260722" />
    <!--
      dark_mode.css dimuat PALING TERAKHIR supaya override-nya menang
      di atas semua CSS lain (dashboard_user.css, header.css, dst),
      tanpa perlu !important di mana-mana.
    -->
    <script src="../js/config.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <?php if (isset($extra_head)) echo $extra_head; ?>
  </head>
  <body>