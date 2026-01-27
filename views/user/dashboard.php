<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>KSM Education</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Favicon -->
  <link rel="shortcut icon" type="image/x-icon" href="../../assets/images/icons/favicon.ico" />

  <!-- CSS Files -->
  <link rel="stylesheet" href="../../assets/css/user-main.css" />


  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>
</head>

<body>

  <?php
  require_once '../../config/config.php';
  ?>

  <?php include 'components/header.html'; ?>

  <div class="container">
    <?php include 'components/statistics.html'; ?>
    <?php include 'components/articles-section.html'; ?>
    <?php include 'components/categories.html'; ?>
    <?php include 'components/newsletter.html'; ?>
  </div>

  <?php include 'components/footer-scripts-dashboard.html'; ?>

</body>

</html>