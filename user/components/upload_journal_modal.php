<?php
/**
 * components/upload_journal_modal.php
 * Modal form upload Jurnal/Opini oleh USER (digembok oleh saldo token).
 * Include di halaman yang butuh tombol "Upload Jurnal Baru"
 * (dashboard_user.php, my_journals_user.php).
 *
 * Membutuhkan:
 *  - journal.css (sudah ter-load via header.php, dipakai untuk
 *    .pengurus-input-group / .pengurus-input / .cover-preview)
 *  - styles/upload_journal_modal.css
 *  - js/upload_journal_modal.js
 *  - components/token_modal.php (untuk modal "token tidak cukup")
 *
 * // TODO backend: endpoint upload_journal.php / upload_opinion.php
 * // belum ada. Validasi & pemotongan token WAJIB diulang di server.
 */
?>
<!-- ===== MODAL: UPLOAD JURNAL / OPINI ===== -->
<div class="ksm-modal-overlay" id="ksmUploadModal">
  <div class="ksm-modal-box ksm-modal-wide">
    <button type="button" class="ksm-modal-close" id="ksmUploadClose" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>

    <h3 class="ksm-modal-title">Upload Karya Baru</h3>
    <p class="ksm-modal-subtitle">
      Lengkapi form di bawah ini. Setiap upload akan menggunakan 1 token dari saldo Anda.
    </p>

    <div class="ksm-upload-type-toggle">
      <button type="button" class="ksm-upload-type-btn active" data-type="jurnal" id="ksmTypeJurnalBtn">
        Jurnal
      </button>
      <button type="button" class="ksm-upload-type-btn" data-type="opini" id="ksmTypeOpiniBtn">
        Opini
      </button>
    </div>

    <form id="ksmUploadForm" novalidate>
      <div class="ksm-upload-form-group">
        <label for="ksmFieldTitle">Judul <span class="req">*</span></label>
        <input type="text" id="ksmFieldTitle" name="title" required />
        <div class="ksm-upload-field-error">Judul wajib diisi.</div>
      </div>

      <div class="ksm-upload-row">
        <div class="ksm-upload-form-group" id="ksmVolumeGroup">
          <label>Volume <span class="req">*</span></label>
          <div class="ksm-volume-inputs">
            <span class="ksm-volume-prefix">Vol.</span>
            <input type="number" id="ksmVolNum" placeholder="12" min="1" />
            <span class="ksm-volume-prefix">No.</span>
            <input type="number" id="ksmNoNum" placeholder="1" min="1" />
            <span class="ksm-volume-prefix">(Thn)</span>
            <input type="number" id="ksmYearNum" placeholder="2026" min="2000" max="2099" style="width: 75px;" />
          </div>
          <input type="hidden" id="ksmFieldVolume" name="volume" />
          <div class="ksm-upload-field-error">Volume dan Nomor wajib diisi.</div>
        </div>
        <div class="ksm-upload-form-group" id="ksmCategoryGroup" style="display:none;">
          <label for="ksmFieldCategory">Kategori Opini <span class="req">*</span></label>
          <input type="text" id="ksmFieldCategory" name="category" placeholder="Contoh: Pendidikan" />
          <div class="ksm-upload-field-error">Kategori wajib diisi untuk opini.</div>
        </div>

        <div class="ksm-upload-form-group">
          <label for="ksmFieldTagsInput">Tags (Ketik &amp; Tekan Koma)</label>
          <div class="ksm-tags-wrap" id="ksmTagsWrap">
            <div class="ksm-tags-list" id="ksmTagsList"></div>
            <input type="text" id="ksmFieldTagsInput" placeholder="Ketik tag lalu tekan koma (,)" />
          </div>
          <input type="hidden" id="ksmFieldTags" name="tags" />
        </div>
      </div>

      <div class="ksm-upload-form-group">
        <label for="ksmFieldAbstract">Abstrak / Deskripsi <span class="req">*</span></label>
        <textarea id="ksmFieldAbstract" name="abstract" required></textarea>
        <div class="ksm-upload-field-error">Abstrak wajib diisi.</div>
      </div>

      <!-- ===== PENULIS (dinamis) ===== -->
      <div class="ksm-upload-form-group">
        <label>Penulis <span class="req">*</span></label>
        <div id="ksmAuthorRows"></div>
        <button type="button" class="ksm-upload-row-add" id="ksmAddAuthorBtn">
          <i data-feather="plus"></i> Tambah Penulis
        </button>
      </div>

      <!-- ===== PENGURUS (opsional, khusus jurnal) — reuse class journal.css ===== -->
      <div class="ksm-upload-form-group" id="ksmPengurusGroup">
        <label>Pengurus (opsional)</label>
        <div id="ksmPengurusRows"></div>
        <button type="button" class="ksm-upload-row-add" id="ksmAddPengurusBtn">
          <i data-feather="plus"></i> Tambah Pengurus
        </button>
      </div>

      <div class="ksm-upload-row">
        <div class="ksm-upload-form-group">
          <label for="ksmFieldEmail">Email <span class="req">*</span></label>
          <input type="email" id="ksmFieldEmail" name="email" required />
          <div class="ksm-upload-field-error">Email wajib diisi dan valid.</div>
        </div>
        <div class="ksm-upload-form-group">
          <label for="ksmFieldPhone">No. Telepon <span class="req">*</span></label>
          <input type="tel" id="ksmFieldPhone" name="phone" required />
          <div class="ksm-upload-field-error">No. telepon wajib diisi.</div>
        </div>
      </div>

      <div class="ksm-upload-row">
        <div class="ksm-upload-form-group">
          <label>Cover Image (Max 5 MB)</label>
          <div class="ksm-upload-file-drop" id="ksmCoverDrop">
            <i data-feather="image"></i>
            <div class="drop-label">Klik untuk pilih gambar cover (&lt; 5 MB)</div>
            <div class="file-chosen" id="ksmCoverFileName"></div>
          </div>
          <input type="file" accept="image/*" class="ksm-upload-file-input" id="ksmCoverInput" />
        </div>
        <div class="ksm-upload-form-group">
          <label>File PDF <span class="req">*</span> (Max 5 MB)</label>
          <div class="ksm-upload-file-drop" id="ksmPdfDrop">
            <i data-feather="file-text"></i>
            <div class="drop-label">Klik untuk pilih file PDF (&lt; 5 MB)</div>
            <div class="file-chosen" id="ksmPdfFileName"></div>
          </div>
          <input type="file" accept="application/pdf" class="ksm-upload-file-input" id="ksmPdfInput" />
          <div class="ksm-upload-field-error" id="ksmPdfError">File PDF wajib diunggah.</div>
        </div>
      </div>

      <div class="ksm-upload-footer">
        <div class="ksm-upload-token-note">
          <i data-feather="zap"></i>
          Upload ini akan menggunakan <strong>&nbsp;1 token&nbsp;</strong>
        </div>
        <button type="submit" class="ksm-upload-submit-btn" id="ksmUploadSubmitBtn">
          <span class="ksm-spinner"></span>
          <span class="ksm-submit-label">Gunakan 1 Token &amp; Upload</span>
        </button>
      </div>
    </form>
  </div>
</div>