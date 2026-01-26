<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>KSM Education - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/admin-main.css" />
  <link rel="shortcut icon" type="image/x-icon" href="../../assets/images/icons/favicon.ico" />
  <script src="https://unpkg.com/feather-icons"></script>
</head>

<body>

  <?php include 'components/header.html'; ?>

  <div class="container">
    <?php include 'components/statistics.html'; ?>
    <?php include 'components/journal-list.html'; ?>
    <?php include 'components/upload-section.html'; ?>
  </div>

  <?php include 'components/modals.html'; ?>
  <?php include 'components/footer-scripts-dashboard.html'; ?>

</body>

</html>