<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Detail Opini - KSM Education</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Favicon -->
  <link rel="shortcut icon" type="image/x-icon" href="../../assets/favicon.ico" />

  <!-- CSS Files (User Main - includes layout css) -->
  <link rel="stylesheet" href="../../assets/css/user/user-main.css" />

  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>

  <!-- PDF.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>

<body>
  <?php
  require_once '../../config/config.php';
  ?>

  <!-- Header -->
  <?php include 'components/header.html'; ?>

  <!-- Breadcrumb (Shared Component) -->
  <?php include '../shared/components/breadcrumb.html'; ?>

  <!-- Search Modal (Shared Component) -->
  <?php include '../shared/components/search-modal.html'; ?>

  <!-- Article Container (900px Width) -->
  <div class="article-container">
    <?php include '../shared/components/article-detail.html'; ?>
  </div>

  <!-- Scripts -->
  <script src="../../js/pdf_text_extractor.js"></script>
  <script src="../../js/explore_jurnal_user.js"></script>
  <script src="../../js/api.js"></script>
  <script src="../../js/storage.js"></script>

  <script>
    feather.replace();
  </script>

</body>

</html>