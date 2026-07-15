// =========================================================
// TOKEN WALLET MODULE
// - Simulasi saldo token user (localStorage) — FRONTEND ONLY
// - Modal Beli Token + generate link wa.me ke admin
// - Menyimpan riwayat permintaan token (Pending/Disetujui/Ditolak)
//
// // TODO backend:
// // 1. Ganti KsmTokenWallet.getBalance() agar fetch saldo asli
// //    dari API (mis. GET /api/token_balance.php).
// // 2. Endpoint utk menyimpan permintaan beli token
// //    (mis. POST /api/token_requests.php) begitu user klik
// //    "Hubungi Admin via WhatsApp".
// // 3. Admin men-approve/reject dari sisi admin panel, lalu saldo
// //    user di-update di server (bukan localStorage).
// =========================================================

(function (global) {
  const BALANCE_KEY = "ksm_token_balance";
  const HISTORY_KEY = "ksm_token_history";
  const ADMIN_WA_NUMBER = "6281234567890"; // TODO backend: samakan dgn footer/config

  const KsmTokenWallet = {
    // ---------- Saldo ----------
    getBalance() {
      // TODO backend: fetch(`${window.APP_CONFIG.apiBase}/token_balance.php`)
      const raw = localStorage.getItem(BALANCE_KEY);
      return raw === null ? 0 : parseInt(raw, 10) || 0;
    },

    setBalance(value) {
      localStorage.setItem(BALANCE_KEY, String(Math.max(0, value)));
      this.renderBalance();
    },

    deduct(amount = 1) {
      const current = this.getBalance();
      this.setBalance(current - amount);
    },

    add(amount) {
      const current = this.getBalance();
      this.setBalance(current + amount);
    },

    renderBalance() {
      const balance = this.getBalance();
      document.querySelectorAll("[data-ksm-token-balance]").forEach((el) => {
        el.textContent = balance;
      });
    },

    // ---------- Riwayat permintaan token ----------
    getHistory() {
      try {
        return JSON.parse(localStorage.getItem(HISTORY_KEY) || "[]");
      } catch (e) {
        return [];
      }
    },

    saveHistory(list) {
      localStorage.setItem(HISTORY_KEY, JSON.stringify(list));
    },

    addRequest(amount) {
      // TODO backend: POST ke /api/token_requests.php { amount }
      const history = this.getHistory();
      history.unshift({
        id: "TRX" + Date.now(),
        amount,
        status: "pending", // pending | approved | rejected
        createdAt: new Date().toISOString(),
      });
      this.saveHistory(history);
      return history[0];
    },
  };

  global.KsmTokenWallet = KsmTokenWallet;

  // ---------- UI: Modal Beli Token ----------
  function initBuyTokenModal() {
    const overlay = document.getElementById("ksmBuyTokenModal");
    if (!overlay) return; // halaman ini tidak include modal token

    const closeBtn = document.getElementById("ksmBuyTokenClose");
    const packages = overlay.querySelectorAll(".ksm-token-package");
    const customInput = document.getElementById("ksmTokenCustomAmount");
    const summaryAmount = document.getElementById("ksmTokenSummaryAmount");
    const contactBtn = document.getElementById("ksmContactAdminBtn");

    let selectedAmount = 10;

    function updateSummary() {
      summaryAmount.textContent = `${selectedAmount} Token`;
    }

    packages.forEach((pkg) => {
      pkg.addEventListener("click", () => {
        packages.forEach((p) => p.classList.remove("active"));
        pkg.classList.add("active");
        selectedAmount = parseInt(pkg.dataset.amount, 10);
        customInput.value = "";
        updateSummary();
      });
    });

    customInput.addEventListener("input", () => {
      const val = parseInt(customInput.value, 10);
      if (val > 0) {
        packages.forEach((p) => p.classList.remove("active"));
        selectedAmount = val;
        updateSummary();
      }
    });

    function openModal() {
      overlay.classList.add("active");
      if (typeof feather !== "undefined") feather.replace();
    }

    function closeModal() {
      overlay.classList.remove("active");
    }

    closeBtn.addEventListener("click", closeModal);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeModal();
    });

    contactBtn.addEventListener("click", () => {
      if (!selectedAmount || selectedAmount < 1) {
        if (typeof showToast === "function") {
          showToast("Masukkan jumlah token yang valid.", "error");
        }
        return;
      }

      // Catat permintaan sebagai pending (simulasi, sebelum backend ada)
      KsmTokenWallet.addRequest(selectedAmount);

      const userName =
        sessionStorage.getItem("userEmail")?.split("@")[0] || "User";

      const message =
        `Halo Admin, saya ${userName} ingin membeli ${selectedAmount} token ` +
        `untuk upload jurnal/opini di KSM Education.`;

      const waUrl = `https://wa.me/${ADMIN_WA_NUMBER}?text=${encodeURIComponent(
        message,
      )}`;

      if (typeof showToast === "function") {
        showToast(
          `Permintaan ${selectedAmount} token tercatat sebagai Pending.`,
          "success",
        );
      }

      closeModal();
      window.open(waUrl, "_blank");
    });

    global.ksmOpenBuyTokenModal = openModal;
    global.ksmCloseBuyTokenModal = closeModal;
  }

  // ---------- UI: Modal Token Tidak Cukup ----------
  function initInsufficientModal() {
    const overlay = document.getElementById("ksmInsufficientTokenModal");
    if (!overlay) return;

    const closeBtn = document.getElementById("ksmInsufficientClose");
    const goBuyBtn = document.getElementById("ksmGoBuyTokenBtn");
    const balanceEl = document.getElementById("ksmInsufficientCurrentBalance");

    function openModal() {
      if (balanceEl) balanceEl.textContent = KsmTokenWallet.getBalance();
      overlay.classList.add("active");
      if (typeof feather !== "undefined") feather.replace();
    }

    function closeModal() {
      overlay.classList.remove("active");
    }

    closeBtn.addEventListener("click", closeModal);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeModal();
    });

    goBuyBtn.addEventListener("click", () => {
      closeModal();
      if (typeof global.ksmOpenBuyTokenModal === "function") {
        global.ksmOpenBuyTokenModal();
      }
    });

    global.ksmOpenInsufficientModal = openModal;
    global.ksmCloseInsufficientModal = closeModal;
  }

  // Tombol-tombol pemicu "Beli Token" di halaman manapun
  function bindTriggers() {
    document.querySelectorAll("[data-ksm-open-buy-token]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (typeof global.ksmOpenBuyTokenModal === "function") {
          global.ksmOpenBuyTokenModal();
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    KsmTokenWallet.renderBalance();
    initBuyTokenModal();
    initInsufficientModal();
    bindTriggers();
  });
  // ---------- TESTING ONLY — hapus sebelum production ----------
  global.addTestToken = function (amount = 100) {
    KsmTokenWallet.add(amount);

    if (typeof showToast === "function") {
      showToast(`+${amount} token ditambahkan (mode testing)`, "success");
    }

    console.log(
      "[TEST] Token ditambahkan:",
      amount,
      "-> saldo sekarang:",
      KsmTokenWallet.getBalance(),
    );
  };

  document.addEventListener("DOMContentLoaded", () => {
    KsmTokenWallet.renderBalance();
    initBuyTokenModal();
    initInsufficientModal();
    bindTriggers();
  });



  
})(window);

