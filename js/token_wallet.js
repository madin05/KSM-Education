(function (global) {
  const KsmTokenWallet = {
    balance: 0,
    history: [],

    getBalance() {
      return this.balance;
    },

    getHistory() {
      return [...this.history];
    },

    renderBalance() {
      document.querySelectorAll("[data-ksm-token-balance]").forEach((el) => {
        el.textContent = this.balance;
      });
    },

    async refresh() {
      if (typeof authFetch !== "function") return;
      const response = await authFetch(`${window.APP_CONFIG.apiBase}/token_wallet.php`);
      if (!response.ok) return;
      const data = await response.json();
      if (!data.ok) return;
      this.balance = Number(data.wallet?.balance || 0);
      this.history = Array.isArray(data.history) ? data.history : [];
      this.renderBalance();
      global.dispatchEvent(new CustomEvent("ksm-token-wallet:updated"));
    },
  };

  global.KsmTokenWallet = KsmTokenWallet;

  function initInsufficientModal() {
    const overlay = document.getElementById("ksmInsufficientTokenModal");
    if (!overlay) return;
    const closeBtn = document.getElementById("ksmInsufficientClose");
    const goBuyBtn = document.getElementById("ksmGoBuyTokenBtn");
    const balanceEl = document.getElementById("ksmInsufficientCurrentBalance");
    const close = () => overlay.classList.remove("active");
    closeBtn?.addEventListener("click", close);
    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) close();
    });
    goBuyBtn?.addEventListener("click", close);
    global.ksmOpenInsufficientModal = () => {
      if (balanceEl) balanceEl.textContent = KsmTokenWallet.getBalance();
      overlay.classList.add("active");
    };
  }

  document.addEventListener("DOMContentLoaded", () => {
    KsmTokenWallet.renderBalance();
    initInsufficientModal();
    KsmTokenWallet.refresh().catch((error) => console.error("Token wallet error:", error));
  });
})(window);