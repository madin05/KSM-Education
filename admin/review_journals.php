<?php
$page_title = 'Review Jurnal Masuk - KSM Admin';
$extra_head = '
  <link rel="stylesheet" href="../styles/explore_admin.css" />
  <link rel="stylesheet" href="../styles/comments_admin.css?v=' . time() . '" />
  <link rel="stylesheet" href="../styles/review_admin.css?v=' . time() . '" />';
include 'components/header.php';
include 'components/sidebar.php';
?>

<div class="admin-review-page">

  <!-- Page Header -->
  <div class="page-header">
    <h1>
      <i data-feather="inbox"></i>
      Review Jurnal Masuk
    </h1>
    <p>Setujui atau tolak jurnal yang dikirim oleh pengguna sebelum tampil di publik.</p>
  </div>

  <!-- Status Tabs -->
  <div class="review-tabs" role="tablist">
    <button class="review-tab active" data-status="pending" role="tab" aria-selected="true">
      <i data-feather="clock"></i> Menunggu Review
      <span class="tab-count" id="countPending">0</span>
    </button>
    <button class="review-tab" data-status="published" role="tab" aria-selected="false">
      <i data-feather="check-circle"></i> Disetujui
    </button>
    <button class="review-tab" data-status="rejected" role="tab" aria-selected="false">
      <i data-feather="x-circle"></i> Ditolak
    </button>
  </div>

  <!-- Filters -->
  <div class="comments-filters">
    <div class="filter-row-left">
      <div class="sort-dropdown">
        <button class="btn-icon-sort" type="button" id="btnReviewSort" aria-label="Urutkan">
          <i data-feather="filter"></i>
        </button>
        <div class="sort-menu" id="reviewSortMenu">
          <div class="menu-section-label">URUTKAN</div>
          <button data-sort="oldest" class="review-sort-item active">
            <i data-feather="clock"></i> Terlama (prioritas)
          </button>
          <button data-sort="newest" class="review-sort-item">
            <i data-feather="calendar"></i> Terbaru
          </button>
          <button data-sort="title" class="review-sort-item">
            <i data-feather="type"></i> Judul A-Z
          </button>
        </div>
      </div>
    </div>

    <div class="filter-row-right">
      <div class="filter-group">
        <div class="search-box-wrapper">
          <i data-feather="search"></i>
          <label for="reviewSearchInput" class="sr-only">Cari jurnal</label>
          <input
            id="reviewSearchInput"
            class="search-input"
            type="search"
            placeholder="Cari judul, penulis, atau pengirim..." />
        </div>
        <button type="button" id="btnReviewSearch" class="btn-search" aria-label="Cari">
          <i data-feather="search"></i>
        </button>
      </div>
      <span id="totalCount"></span>
    </div>
  </div>

  <!-- Review Table -->
  <div class="review-table-card">
    <div class="review-table-scroll">
      <table class="review-table" id="reviewTable">
        <thead>
          <tr>
            <th>Judul</th>
            <th>Pengirim</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th class="col-action">Aksi</th>
          </tr>
        </thead>
        <tbody id="reviewTableBody">
          <tr>
            <td colspan="5" class="review-empty-cell">
              <div class="loader" style="margin:0 auto 12px;"></div>
              Memuat data review...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div id="reviewPagination" class="review-pagination"></div>

</div>

<!-- Detail Modal -->
<div id="reviewDetailModal" class="modal">
  <div class="modal-overlay"></div>
  <div class="modal-content review-modal-content">
    <button type="button" class="close-modal" id="closeReviewDetail" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>
    <h2 id="reviewDetailTitle">Detail Jurnal</h2>
    <div id="reviewDetailBody" class="review-detail-body"></div>
  </div>
</div>

<!-- Reject Reason Modal -->
<div id="rejectReasonModal" class="modal">
  <div class="modal-overlay"></div>
  <div class="modal-content review-modal-content" style="max-width:480px">
    <button type="button" class="close-modal" id="closeRejectReason" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>
    <h2>Tolak Jurnal</h2>
    <p class="reject-hint" id="rejectTargetTitle"></p>
    <form id="rejectForm">
      <label for="rejectReasonInput" class="reject-label">Alasan penolakan <span aria-hidden="true">*</span></label>
      <textarea
        id="rejectReasonInput"
        rows="4"
        maxlength="500"
        required
        placeholder="Contoh: Abstrak belum sesuai format, file PDF tidak terbaca..."></textarea>
      <div class="reject-actions">
        <button type="button" class="btn-reject-cancel" id="cancelReject">Batal</button>
        <button type="submit" class="btn-reject-submit">Tolak Jurnal</button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_scripts = '
<script src="../js/custom_alerts.js"></script>
<script src="../js/review_journals_admin.js?v=' . time() . '"></script>
<script>
  if (typeof feather !== "undefined") feather.replace();
</script>';
include 'components/footer.php';
?>
