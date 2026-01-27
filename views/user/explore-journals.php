<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Detail Artikel - KSM Education</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Favicon -->
  <link rel="shortcut icon" type="image/x-icon" href="../../assets/images/icons/favicon.ico" />


  <!-- CSS Files -->
  <link rel="stylesheet" href="../../assets/css/admin-main.css" />


  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>

  <!-- PDF.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>

<body>
  <?php
  require_once '../../config/config.php';
  ?>

  <?php include 'components/header.html'; ?>

  <?php include '../shared/components/breadcrumb.html'; ?>

  <?php include '../shared/components/search-modal.html'; ?>

  <div class="container container--article">
    <?php include '../shared/components/article-detail.html'; ?>
  </div>

  <!-- Scripts -->
  <script src="../../js/pdf_text_extractor.js"></script>
  <script src="../../js/explore_jurnal_user.js"></script>
  <script type="module" src="../../assets/js/api.js"></script>
  <script src="../../js/storage.js"></script>
  <script>
    feather.replace();
  </script>

</body>

</html>