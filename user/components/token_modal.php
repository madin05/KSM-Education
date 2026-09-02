<?php
/**
 * components/token_modal.php
 * Modal "Beli Token (Midtrans Payment Gateway)" + Modal "Token Tidak Cukup".
 * Include file ini di halaman manapun yang membutuhkan fitur beli token
 * (dashboard_user.php, my_journals_user.php, token_history_user.php, dst).
 *
 * Membutuhkan:
 *  - styles/user/token_wallet.css
 *  - js/token_wallet.js
 */
?>
<!-- ===== MODAL: BELI TOKEN (MIDTRANS GATEWAY) ===== -->
<div class="ksm-modal-overlay" id="ksmBuyTokenModal">
  <div class="ksm-modal-box ksm-modal-wide">
    <button type="button" class="ksm-modal-close" id="ksmBuyTokenClose" aria-label="Tutup">
      <i data-feather="x"></i>
    </button>

    <div class="ksm-modal-header-badge">
      <i data-feather="zap"></i> Top Up Saldo Instant
    </div>

    <h3 class="ksm-modal-title">Pilih Paket Token Upload</h3>
    <p class="ksm-modal-subtitle">
      Setiap 1 token digunakan untuk mengunggah 1 Jurnal atau Opini. Pembayaran dapat dilakukan secara otomatis via QRIS (GoPay, OVO, Dana, ShopeePay, Mobile Banking) & Transfer Bank.
    </p>

    <!-- Pricelist Package Cards -->
    <div class="ksm-token-packages-grid">
      <!-- 5 Token -->
      <div class="ksm-pkg-card" data-pkg-id="pkg_5" data-tokens="5" data-price="10000">
        <span class="ksm-pkg-tag">Hemat 20%</span>
        <div class="ksm-pkg-tokens">5 <span>Token</span></div>
        <div class="ksm-pkg-title">Paket Hemat</div>
        <div class="ksm-pkg-price">Rp10.000</div>
        <div class="ksm-pkg-subtext">Rp2.000 / token</div>
      </div>

      <!-- 10 Token (Popular Default) -->
      <div class="ksm-pkg-card active popular" data-pkg-id="pkg_10" data-tokens="10" data-price="18000">
        <span class="ksm-pkg-tag popular-badge">⭐ Paling Populer</span>
        <div class="ksm-pkg-tokens">10 <span>Token</span></div>
        <div class="ksm-pkg-title">Paket Standar</div>
        <div class="ksm-pkg-price">Rp18.000</div>
        <div class="ksm-pkg-subtext">Rp1.800 / token (Hemat 28%)</div>
      </div>

      <!-- 20 Token -->
      <div class="ksm-pkg-card" data-pkg-id="pkg_20" data-tokens="20" data-price="34000">
        <span class="ksm-pkg-tag">Best Value</span>
        <div class="ksm-pkg-tokens">20 <span>Token</span></div>
        <div class="ksm-pkg-title">Paket Super</div>
        <div class="ksm-pkg-price">Rp34.000</div>
        <div class="ksm-pkg-subtext">Rp1.700 / token (Hemat 32%)</div>
      </div>

      <!-- 50 Token -->
      <div class="ksm-pkg-card" data-pkg-id="pkg_50" data-tokens="50" data-price="80000">
        <span class="ksm-pkg-tag">Sultan</span>
        <div class="ksm-pkg-tokens">50 <span>Token</span></div>
        <div class="ksm-pkg-title">Paket Sultan</div>
        <div class="ksm-pkg-price">Rp80.000</div>
        <div class="ksm-pkg-subtext">Rp1.600 / token (Hemat 36%)</div>
      </div>

      <!-- 1 Token Eceran -->
      <div class="ksm-pkg-card" data-pkg-id="pkg_1" data-tokens="1" data-price="2500">
        <span class="ksm-pkg-tag">Eceran</span>
        <div class="ksm-pkg-tokens">1 <span>Token</span></div>
        <div class="ksm-pkg-title">Paket Trial</div>
        <div class="ksm-pkg-price">Rp2.500</div>
        <div class="ksm-pkg-subtext">Rp2.500 / token</div>
      </div>
    </div>

    <!-- Payment Summary Box -->
    <div class="ksm-token-summary-box">
      <div class="ksm-summary-row">
        <span>Paket Dipilih:</span>
        <strong id="ksmSelectedPkgName">Paket Standar (10 Token)</strong>
      </div>
      <div class="ksm-summary-row">
        <span>Total Pembayaran:</span>
        <strong id="ksmSelectedPkgPrice" class="price-highlight">Rp18.000</strong>
      </div>
      <div class="ksm-payment-methods-icons">
        <span class="method-badge">QRIS</span>
        <span class="method-badge">GoPay</span>
        <span class="method-badge">ShopeePay</span>
        <span class="method-badge">OVO / Dana</span>
        <span class="method-badge">BCA / Mandiri / BRI / BNI</span>
      </div>
    </div>

    <!-- Action Buttons -->
    <button type="button" class="ksm-btn-midtrans-pay" id="ksmMidtransPayBtn">
      <i data-feather="qr-code"></i>
      Bayar Sekarang (QRIS / Midtrans)
    </button>

    <p class="ksm-token-note">
      🔒 Pembayaran aman diawasi oleh <strong>Midtrans Payment Gateway</strong>. Saldo token otomatis bertambah seketika setelah pembayaran berhasil dikonfirmasi.
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
      Saldo token Anda saat ini adalah:
      <strong id="ksmInsufficientCurrentBalance">0</strong>.
    </p>

    <button type="button" class="ksm-btn-midtrans-pay" id="ksmGoBuyTokenBtn" data-ksm-open-buy-token>
      <i data-feather="shopping-cart"></i>
      Beli Token Sekarang
    </button>
  </div>
</div>