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
 */
?>
<!-- ===== MODAL: BELI TOKEN ===== -->
<div class="ksm-modal-overlay" id="ksmBuyTokenModal">
  <div class="ksm-modal-box">
    <button type="button" class="ksm-modal-close" id="ksmBuyTokenClose" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>

    <h3 class="ksm-modal-title">Beli Token</h3>
    <p class="ksm-modal-subtitle">
      Token digunakan untuk mengunggah jurnal atau opini. Lanjutkan ke WhatsApp
      KSM Education untuk memilih paket dan mengirim bukti transfer.
    </p>

    <div class="ksm-token-summary">
      <span>Proses pembayaran</span>
      <strong>Pilih paket → Transfer → Upload bukti → Verifikasi admin</strong>
    </div>

    <button type="button" class="ksm-btn-telegram" id="ksmContactAdminBtn">
      <i data-feather="message-square"></i>
      Lanjutkan di WhatsApp
    </button>

    <p class="ksm-token-note">
      Kirimkan foto bukti transfer Anda di WhatsApp. Setelah admin menyetujuinya,
      token otomatis masuk ke akun Anda dan tercatat di Riwayat Token.
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

    <button type="button" class="ksm-btn-telegram" id="ksmGoBuyTokenBtn" data-ksm-open-buy-token>
      <i data-feather="shopping-cart"></i>
      Beli Token Sekarang
    </button>
  </div>
</div>