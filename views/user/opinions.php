<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Opini - KSM Education</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- CSS Utama User -->
  <link rel="stylesheet" href="../../assets/css/user-main.css" />
  <link rel="shortcut icon" type="image/x-icon" href="../../assets/favicon.ico" />

  <script src="https://unpkg.com/feather-icons"></script>
</head>

<body>
  <?php
  require_once '../../config/config.php';
  ?>

  <!-- Header User -->
  <?php include 'components/header.html'; ?>

  <!-- Main Content -->
  <div class="container">

    <!-- Filter & Search Section -->
    <section class="filter-section">
      <div class="filter-row-top">
        <!-- Sort Dropdown -->
        <div class="sort-dropdown">
          <button class="btn-icon-sort" type="button" id="btnSort">
            <i data-feather="filter"></i>
          </button>
          <div class="sort-menu" id="sortMenu">
            <button data-sort="newest" class="sort-item active">
              <i data-feather="clock"></i> Terbaru
            </button>
            <button data-sort="oldest" class="sort-item">
              <i data-feather="calendar"></i> Terlama
            </button>
            <button data-sort="title" class="sort-item">
              <i data-feather="type"></i> Judul A-Z
            </button>
            <button data-sort="views" class="sort-item">
              <i data-feather="eye"></i> Paling Populer
            </button>
          </div>
        </div>

        <!-- Search Box -->
        <div class="search-box">
          <i data-feather="search"></i>
          <input type="text" placeholder="Cari opini..." id="searchInput" />
        </div>
      </div>

      <!-- Counter -->
      <div class="filter-row-bottom">
        <div class="filter-stats">
          <span id="totalOpinions">0</span> artikel
        </div>
      </div>
    </section>

    <!-- Back Button -->
    <section class="back-section">
      <a href="dashboard.php" class="btn-back">
        <i data-feather="arrow-left"></i> Back
      </a>
    </section>

    <!-- Opinion List Section -->
    <section id="opinion" class="journal-list-full">
      <div id="journalContainer">
      </div>
    </section>

    <!-- Pagination -->
    <section class="pagination-section">
      <div id="pagination" class="pagination"></div>
    </section>
  </div>

  <!-- Scripts -->
  <script src="../../js/pagination.js"></script>
  <script src="../../js/opinions_user.js"></script>
  <script src="../../js/mobile_menu.js"></script>

  <script>
    feather.replace();
  </script>
</body>

</html>