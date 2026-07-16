<?php
/**
 * components/token_modal.php
 * Modal "Beli Token" + Modal "Token Tidak Cukup".
 * Include file ini di halaman manapun yang butuh fitur beli token
 * (dashboard_user.php, my_journals_user.php, token_history_user.php, dst).
 *
 * Membutuhkan:
 *  - styles/token_wallet.css
 *  - js/token_wallet.js
 *
 * // TODO backend: nomor WA admin sebaiknya diambil dari 1 sumber
 * // konfigurasi (mis. config.php / API settings) bukan hardcode di
 * // beberapa file. Untuk sekarang disamakan dengan nomor di footer.
 */
$admin_whatsapp_number = $admin_whatsapp_number ?? '6285814991897';
?>
<!-- ===== MODAL: BELI TOKEN ===== -->
<div class="ksm-modal-overlay" id="ksmBuyTokenModal">
  <div class="ksm-modal-box">
    <button type="button" class="ksm-modal-close" id="ksmBuyTokenClose" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>

    <h3 class="ksm-modal-title">Beli Token</h3>
    <p class="ksm-modal-subtitle">
      Token digunakan untuk mengunggah 1 jurnal atau opini. Pilih paket di
      bawah, lalu hubungi admin via WhatsApp untuk validasi &amp; pembayaran.
    </p>

    <div class="ksm-token-packages" id="ksmTokenPackages">
      <div class="ksm-token-package" data-amount="5">
        <div class="qty">5</div>
        <div class="unit">Token</div>
      </div>
      <div class="ksm-token-package active" data-amount="10">
        <div class="qty">10</div>
        <div class="unit">Token</div>
      </div>
      <div class="ksm-token-package" data-amount="20">
        <div class="qty">20</div>
        <div class="unit">Token</div>
      </div>
    </div>

    <div class="ksm-token-custom">
      <label for="ksmTokenCustomAmount">Atau masukkan jumlah custom</label>
      <input
        type="number"
        id="ksmTokenCustomAmount"
        min="1"
        placeholder="Contoh: 15"
      />
    </div>

    <div class="ksm-token-summary">
      <span>Jumlah token dipesan</span>
      <strong id="ksmTokenSummaryAmount">10 Token</strong>
    </div>

    <button type="button" class="ksm-btn-whatsapp" id="ksmContactAdminBtn">
      <i data-feather="message-circle"></i>
      Hubungi Admin via WhatsApp
    </button>

    <p class="ksm-token-note">
      Setelah pesan terkirim, admin akan memverifikasi pembayaran dan
      menambahkan token ke akun Anda secara manual. Permintaan ini akan
      tercatat sebagai <strong>Pending</strong> di halaman Riwayat Token.
    </p>
  </div>
</div>

<!-- ===== MODAL: TOKEN TIDAK CUKUP ===== -->
<div class="ksm-modal-overlay" id="ksmInsufficientTokenModal">
  <div class="ksm-modal-box ksm-center-text">
    <button type="button" class="ksm-modal-close" id="ksmInsufficientClose" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>

    <div class="ksm-insufficient-icon">
      <i data-feather="alert-circle"></i>
    </div>

    <h3 class="ksm-modal-title" style="padding-right:0;">Token Tidak Cukup</h3>
    <p class="ksm-modal-subtitle">
      Anda membutuhkan minimal 1 token untuk mengunggah jurnal atau opini.
      Saldo token Anda saat ini
      <strong id="ksmInsufficientCurrentBalance">0</strong>.
    </p>

    <button type="button" class="ksm-btn-whatsapp" id="ksmGoBuyTokenBtn" style="background:#3b82f6;">
      <i data-feather="shopping-cart"></i>
      Beli Token Sekarang
    </button>
  </div>
</div>