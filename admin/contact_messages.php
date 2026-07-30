<?php
// admin/contact_messages.php
// Kotak masuk pesan kontak dari user (user/kontak_user.php -> services/send_contact.php).
// Endpoint yang dipakai:
//   GET   services/admin/contact_messages.php  -> daftar pesan (+filter status)
//   PATCH services/admin/contact_message.php   -> aksi read / reply / close
$page_title = 'Kotak Masuk Kontak - KSM Admin';
$extra_head = '
  <link rel="stylesheet" href="../styles/explore_admin.css" />
  <link rel="stylesheet" href="../styles/comments_admin.css?v=' . time() . '" />
  <link rel="stylesheet" href="../styles/review_admin.css?v=' . time() . '" />
  <link rel="stylesheet" href="../styles/contact_admin.css?v=' . time() . '" />';
include 'components/header.php';
include 'components/sidebar.php';
?>

<div class="admin-review-page">

  <div class="page-header">
    <h1>
      <i data-feather="inbox"></i>
      Kotak Masuk Kontak
    </h1>
    <p>Baca, balas, dan tutup pesan yang dikirim pengguna melalui halaman Kontak.</p>
  </div>

  <!-- Tab status -->
  <div class="review-tabs" role="tablist" aria-label="Filter status pesan">
    <button type="button" class="review-tab active" data-status="new" role="tab" aria-selected="true">
      Baru <span class="review-tab-count" id="countNew"></span>
    </button>
    <button type="button" class="review-tab" data-status="read" role="tab" aria-selected="false">
      Dibaca <span class="review-tab-count" id="countRead"></span>
    </button>
    <button type="button" class="review-tab" data-status="replied" role="tab" aria-selected="false">
      Dibalas <span class="review-tab-count" id="countReplied"></span>
    </button>
    <button type="button" class="review-tab" data-status="closed" role="tab" aria-selected="false">
      Ditutup <span class="review-tab-count" id="countClosed"></span>
    </button>
    <button type="button" class="review-tab" data-status="" role="tab" aria-selected="false">
      Semua
    </button>
  </div>

  <!-- Toolbar -->
  <div class="review-toolbar">
    <span class="review-total" id="contactTotal"></span>
    <button type="button" class="btn-refresh" id="contactRefresh" title="Muat ulang">
      <i data-feather="refresh-cw"></i>
    </button>
  </div>

  <!-- Daftar pesan -->
  <div class="review-table-wrapper">
    <table class="review-table" id="contactTable">
      <thead>
        <tr>
          <th scope="col">Pengirim</th>
          <th scope="col">Subjek &amp; Pesan</th>
          <th scope="col">Status</th>
          <th scope="col">Tanggal</th>
          <th scope="col" class="col-actions">Aksi</th>
        </tr>
      </thead>
      <tbody id="contactTableBody">
        <tr>
          <td colspan="5" class="review-empty-cell">
            <div class="loader" style="margin:0 auto 12px;"></div>
            Memuat pesan...
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div id="contactPagination" class="review-pagination"></div>
</div>

<!-- Modal balas / detail -->
<div class="review-modal" id="contactModal" hidden>
  <div class="review-modal-backdrop" data-close-contact-modal></div>
  <div class="review-modal-box" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">
    <div class="review-modal-head">
      <h2 id="contactModalTitle">Detail Pesan</h2>
      <button type="button" class="review-modal-close" data-close-contact-modal aria-label="Tutup">
        <i data-feather="x"></i>
      </button>
    </div>

    <div class="review-modal-body">
      <dl class="review-detail-list" id="contactDetail"></dl>

      <div class="form-group">
        <label for="contactReplyText">Balasan admin</label>
        <textarea
          id="contactReplyText"
          rows="6"
          maxlength="5000"
          placeholder="Tulis balasan untuk pengirim (maks. 5000 karakter)..."></textarea>
        <small class="field-hint"><span id="contactReplyCount">0</span>/5000 karakter</small>
      </div>
    </div>

    <div class="review-modal-foot">
      <button type="button" class="btn-secondary" data-close-contact-modal>Tutup</button>
      <button type="button" class="btn-danger-soft" id="contactCloseBtn">
        <i data-feather="archive"></i> Tandai Selesai
      </button>
      <button type="button" class="btn-primary" id="contactReplyBtn">
        <i data-feather="send"></i> Kirim Balasan
      </button>
    </div>
  </div>
</div>

<?php
$extra_scripts = '
<script src="../js/contact_messages_admin.js?v=' . time() . '"></script>
<script>
  if (typeof feather !== "undefined") feather.replace();
</script>';
include 'components/footer.php';
?>
