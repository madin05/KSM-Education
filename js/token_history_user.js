// =========================================================
// RIWAYAT TOKEN — render riwayat permintaan beli token
//
// Data disimpan di localStorage key "ksm_token_history" (lihat
// js/token_wallet.js -> KsmTokenWallet.addRequest). Di-seed dengan
// data dummy kalau kosong supaya halaman ini enak dilihat saat demo.
//
// // TODO backend:
// // 1. Ganti dengan fetch API (mis. GET /api/token_requests.php)
// //    yang mengembalikan riwayat permintaan milik user yang login.
// // 2. Status "approved"/"rejected" diisi otomatis begitu admin
// //    memproses permintaan dari sisi admin panel.
// =========================================================

(function () {
  let currentFilter = "all";

  function seedDummyIfEmpty() {
    const existing =
      typeof KsmTokenWallet !== "undefined" ? KsmTokenWallet.getHistory() : [];
    if (existing.length > 0) return;

    const dummy = [
      {
        id: "TRXDUMMY1",
        amount: 10,
        status: "approved",
        createdAt: new Date(Date.now() - 86400000 * 15).toISOString(),
      },
      {
        id: "TRXDUMMY2",
        amount: 5,
        status: "pending",
        createdAt: new Date(Date.now() - 86400000 * 1).toISOString(),
      },
      {
        id: "TRXDUMMY3",
        amount: 20,
        status: "rejected",
        createdAt: new Date(Date.now() - 86400000 * 25).toISOString(),
      },
    ];

    KsmTokenWallet.saveHistory(dummy);
  }

  function statusBadge(status) {
    const map = {
      pending: { label: "Pending", icon: "clock", cls: "ksm-status-pending" },
      approved: {
        label: "Disetujui",
        icon: "check-circle",
        cls: "ksm-status-published",
      },
      rejected: { label: "Ditolak", icon: "x-circle", cls: "ksm-status-rejected" },
    };
    const info = map[status] || map.pending;
    return `<span class="ksm-status-badge ${info.cls}"><i data-feather="${info.icon}"></i>${info.label}</span>`;
  }

  function formatDate(iso) {
    return new Date(iso).toLocaleDateString("id-ID", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function renderSummary(all) {
    const totalEl = document.getElementById("ksmTotalRequests");
    const pendingEl = document.getElementById("ksmPendingRequests");
    if (totalEl) totalEl.textContent = all.length;
    if (pendingEl) {
      pendingEl.textContent = all.filter((i) => i.status === "pending").length;
    }
  }

  function render() {
    const container = document.getElementById("ksmTokenHistoryList");
    if (!container) return;

    const all =
      typeof KsmTokenWallet !== "undefined" ? KsmTokenWallet.getHistory() : [];

    renderSummary(all);

    const filtered =
      currentFilter === "all"
        ? all
        : all.filter((item) => item.status === currentFilter);

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="ksm-my-journals-empty">
          <i data-feather="inbox"></i>
          <h3>Belum ada riwayat di kategori ini</h3>
          <p>Klik "Beli Token" untuk mengajukan permintaan token baru.</p>
        </div>
      `;
      if (typeof feather !== "undefined") feather.replace();
      return;
    }

    container.innerHTML = filtered
      .map(
        (item) => `
          <div class="ksm-token-history-card">
            <div class="ksm-token-history-left">
              <div class="ksm-token-history-icon">
                <i data-feather="zap"></i>
              </div>
              <div>
                <div class="ksm-token-history-amount">${item.amount} Token</div>
                <div class="ksm-token-history-date">${formatDate(item.createdAt)}</div>
              </div>
            </div>
            ${statusBadge(item.status)}
          </div>
        `,
      )
      .join("");

    if (typeof feather !== "undefined") feather.replace();
  }

  function initFilters() {
    const tabs = document.querySelectorAll(
      "#ksmTokenHistoryFilters .ksm-filter-tab",
    );
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        currentFilter = tab.dataset.filter;
        render();
      });
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    seedDummyIfEmpty();
    initFilters();
    render();
  });
})();