<?php
// admin/visitor_analytics.php
// Halaman detail analitik pengunjung.
// Endpoint: services/admin/visitor_analytics.php?days=7|30|90
// Sebelumnya data visitors hanya tampil sebagai satu angka (#visitorCount) di
// dashboard; halaman ini membedah tren harian, halaman populer, jam sibuk,
// perangkat, dan kunjungan terakhir.
$page_title = 'Analitik Pengunjung - KSM Admin';
$extra_head = '
  <link rel="stylesheet" href="../styles/admin/explore_admin.css" />
  <link rel="stylesheet" href="../styles/admin/review_admin.css?v=' . time() . '" />
  <link rel="stylesheet" href="../styles/admin/token_admin.css?v=' . time() . '" />
  <link rel="stylesheet" href="../styles/admin/visitor_admin.css?v=' . time() . '" />';
include 'components/header.php';
include 'components/sidebar.php';
?>

<div class="admin-review-page">

  <div class="page-header">
    <h1>
      <i data-feather="bar-chart-2"></i>
      Analitik Pengunjung
    </h1>
    <p>Rekap kunjungan situs berdasarkan data pelacakan pengunjung.</p>
  </div>

  <!-- Rentang waktu -->
  <div class="token-view-switch" role="tablist" aria-label="Rentang waktu">
    <button type="button" class="token-view-btn" data-days="7" role="tab" aria-selected="false">7 Hari</button>
    <button type="button" class="token-view-btn active" data-days="30" role="tab" aria-selected="true">30 Hari</button>
    <button type="button" class="token-view-btn" data-days="90" role="tab" aria-selected="false">90 Hari</button>
    <button type="button" class="btn-refresh" id="visitorRefresh" title="Muat ulang">
      <i data-feather="refresh-cw"></i>
    </button>
  </div>

  <!-- Ringkasan -->
  <div class="token-stat-grid">
    <div class="token-stat-card">
      <span class="token-stat-label">Kunjungan Hari Ini</span>
      <strong class="token-stat-value" id="statToday">-</strong>
      <small class="token-stat-note" id="statTodayUnique"></small>
    </div>
    <div class="token-stat-card">
      <span class="token-stat-label">Kunjungan Periode</span>
      <strong class="token-stat-value" id="statRange">-</strong>
      <small class="token-stat-note" id="statRangeUnique"></small>
    </div>
    <div class="token-stat-card">
      <span class="token-stat-label">Total Kunjungan</span>
      <strong class="token-stat-value" id="statTotal">-</strong>
      <small class="token-stat-note" id="statFirstVisit"></small>
    </div>
    <div class="token-stat-card">
      <span class="token-stat-label">Pengunjung Unik</span>
      <strong class="token-stat-value" id="statUnique">-</strong>
      <small class="token-stat-note" id="statLastVisit"></small>
    </div>
  </div>

  <div id="visitorError" class="review-error visitor-error" hidden></div>

  <!-- Tren harian -->
  <section class="visitor-panel">
    <div class="visitor-panel-head">
      <h2><i data-feather="trending-up"></i> Tren Harian</h2>
      <span class="visitor-panel-note" id="dailyPeak"></span>
    </div>
    <div class="visitor-chart" id="dailyChart" role="img" aria-label="Grafik kunjungan harian">
      <div class="visitor-chart-loading">Memuat data...</div>
    </div>
  </section>

  <div class="visitor-grid">
    <!-- Halaman terpopuler -->
    <section class="visitor-panel">
      <div class="visitor-panel-head">
        <h2><i data-feather="file-text"></i> Halaman Terpopuler</h2>
      </div>
      <div class="review-table-wrapper">
        <table class="review-table">
          <thead>
            <tr>
              <th scope="col">Halaman</th>
              <th scope="col">Kunjungan</th>
              <th scope="col">Unik</th>
            </tr>
          </thead>
          <tbody id="pagesBody">
            <tr><td colspan="3" class="review-empty-cell">Memuat...</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Perangkat -->
    <section class="visitor-panel">
      <div class="visitor-panel-head">
        <h2><i data-feather="smartphone"></i> Perangkat</h2>
      </div>
      <ul class="visitor-bar-list" id="deviceList">
        <li class="visitor-empty">Memuat...</li>
      </ul>
    </section>
  </div>

  <!-- Jam sibuk -->
  <section class="visitor-panel">
    <div class="visitor-panel-head">
      <h2><i data-feather="clock"></i> Distribusi Jam</h2>
      <span class="visitor-panel-note" id="hourPeak"></span>
    </div>
    <div class="visitor-chart visitor-chart-hours" id="hourlyChart" role="img" aria-label="Grafik kunjungan per jam">
      <div class="visitor-chart-loading">Memuat data...</div>
    </div>
  </section>

  <!-- Kunjungan terakhir -->
  <section class="visitor-panel">
    <div class="visitor-panel-head">
      <h2><i data-feather="activity"></i> Kunjungan Terakhir</h2>
    </div>
    <div class="review-table-wrapper">
      <table class="review-table">
        <thead>
          <tr>
            <th scope="col">Waktu</th>
            <th scope="col">Halaman</th>
            <th scope="col">Perangkat</th>
            <th scope="col">IP</th>
          </tr>
        </thead>
        <tbody id="recentBody">
          <tr><td colspan="4" class="review-empty-cell">Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php
$extra_scripts = '
<script src="../js/visitor_analytics_admin.js?v=' . time() . '"></script>
<script>
  if (typeof feather !== "undefined") feather.replace();
</script>';
include 'components/footer.php';
?>
