// =========================================================
// RIWAYAT TOKEN — render riwayat permintaan beli token
//
// Data berasal dari services/token_wallet.php untuk user terautentikasi.
// =========================================================

(function () {
  let currentFilter = "all";

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
    initFilters();
    render();
    window.addEventListener("ksm-token-wallet:updated", render);
  });
})();