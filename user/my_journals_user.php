<?php
$page_title = 'Jurnal Saya - KSM Education';
$base_css = '<link rel="stylesheet" href="../styles/user/dashboard_user.css?v=202501111545" />
  <link rel="stylesheet" href="../styles/user/token_wallet.css?v=20260715" />
  <link rel="stylesheet" href="../styles/user/upload_journal_modal.css?v=20260715" />
  <link rel="stylesheet" href="../styles/user/my_journals_user.css?v=20260715" />';
include 'components/header.php';
include 'components/navbar.php';
?>

    <div class="container">
      <section class="ksm-page-heading">
        <div>
          <h2>Jurnal Saya</h2>
          <p class="ksm-page-subtitle">
            Daftar jurnal &amp; opini yang pernah Anda upload, beserta status
            reviewnya.
          </p>
        </div>
        <button type="button" class="ksm-btn-solid-dark" data-ksm-open-upload>
          <i data-feather="upload"></i>
          Upload Jurnal Baru
        </button>
      </section>

      <!-- ===== FILTER TABS ===== -->
      <div class="ksm-filter-tabs" id="ksmMyJournalsFilters">
        <button type="button" class="ksm-filter-tab active" data-filter="all">
          Semua
        </button>
        <button type="button" class="ksm-filter-tab" data-filter="pending">
          Pending Review
        </button>
        <button type="button" class="ksm-filter-tab" data-filter="published">
          Terbit
        </button>
        <button type="button" class="ksm-filter-tab" data-filter="rejected">
          Ditolak
        </button>
      </div>

      <!-- ===== LIST ===== -->
      <section class="ksm-my-journals-list" id="ksmMyJournalsList">
        <!-- Diisi oleh js/my_journals_user.js -->
      </section>
    </div>

    <?php
    include 'components/token_modal.php';
    include 'components/upload_journal_modal.php';
    ?>

<?php
$extra_scripts = <<<'EOT'
<script src="../js/script.js?v=2025112910"></script>
    <script src="../js/custom_alerts.js"></script>
    <script src="../js/api.js?v=20260729"></script>
    <script src="../js/token_wallet.js?v=20260729"></script>
    <script src="../js/upload_journal_modal.js?v=20260729"></script>
    <script src="../js/my_journals_user.js?v=20260729"></script>
    <script src="../js/mobile_menu.js?v=20251130"></script>
    <script>
      feather.replace();
    </script>
  
EOT;
include 'components/footer.php';
?>