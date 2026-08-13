// =========================================================
// RIWAYAT TOKEN — render, filter, dan hapus riwayat milik user.
// =========================================================

(function () {
  let currentFilter = "all";
  const selectedIds = new Set();

  function getHistory() {
    return typeof KsmTokenWallet !== "undefined" ? KsmTokenWallet.getHistory() : [];
  }

  function statusBadge(status) {
    const map = {
      pending: { label: "Pending", icon: "clock", cls: "ksm-status-pending" },
      approved: { label: "Disetujui", icon: "check-circle", cls: "ksm-status-published" },
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

  function visibleHistory() {
    return getHistory().filter((item) => currentFilter === "all" || item.status === currentFilter);
  }

  function renderSummary(all) {
    const totalEl = document.getElementById("ksmTotalRequests");
    const pendingEl = document.getElementById("ksmPendingRequests");
    if (totalEl) totalEl.textContent = all.length;
    if (pendingEl) pendingEl.textContent = all.filter((i) => i.status === "pending").length;
  }

  function updateSelectionControls(items = visibleHistory()) {
    const selectAll = document.getElementById("ksmSelectAllHistory");
    const deleteButton = document.getElementById("ksmDeleteSelectedHistory");
    const count = document.getElementById("ksmHistorySelectedCount");
    const selectedVisible = items.filter((item) => selectedIds.has(item.id)).length;

    if (selectAll) {
      selectAll.checked = items.length > 0 && selectedVisible === items.length;
      selectAll.indeterminate = selectedVisible > 0 && selectedVisible < items.length;
      selectAll.disabled = items.length === 0;
    }
    if (deleteButton) deleteButton.disabled = selectedIds.size === 0;
    if (count) count.textContent = selectedIds.size ? `${selectedIds.size} riwayat dipilih` : "";
  }

  function render() {
    const container = document.getElementById("ksmTokenHistoryList");
    if (!container) return;

    const all = getHistory();
    const validIds = new Set(all.map((item) => item.id));
    [...selectedIds].forEach((id) => {
      if (!validIds.has(id)) selectedIds.delete(id);
    });
    renderSummary(all);

    const filtered = visibleHistory();
    if (!filtered.length) {
      container.innerHTML = `
        <div class="ksm-my-journals-empty">
          <i data-feather="inbox"></i>
          <h3>Belum ada riwayat di kategori ini</h3>
          <p>Klik "Beli Token" untuk mengajukan permintaan token baru.</p>
        </div>`;
      if (typeof feather !== "undefined") feather.replace();
      updateSelectionControls(filtered);
      return;
    }

    container.innerHTML = filtered.map((item) => `
      <div class="ksm-token-history-card">
        <div class="ksm-token-history-main">
          <label class="ksm-history-checkbox" aria-label="Pilih riwayat ${item.amount} Token">
            <input type="checkbox" class="ksm-history-item-checkbox" value="${item.id}" ${selectedIds.has(item.id) ? "checked" : ""} />
          </label>
          <div class="ksm-token-history-left">
            <div class="ksm-token-history-icon"><i data-feather="zap"></i></div>
            <div>
              <div class="ksm-token-history-amount">${item.amount} Token</div>
              <div class="ksm-token-history-date">${formatDate(item.createdAt)}</div>
            </div>
          </div>
        </div>
        ${statusBadge(item.status)}
      </div>`).join("");

    if (typeof feather !== "undefined") feather.replace();
    container.querySelectorAll(".ksm-history-item-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selectedIds.add(checkbox.value);
        else selectedIds.delete(checkbox.value);
        updateSelectionControls(filtered);
      });
    });
    updateSelectionControls(filtered);
  }

  function initFilters() {
    document.querySelectorAll("#ksmTokenHistoryFilters .ksm-filter-tab").forEach((tab) => {
      tab.addEventListener("click", () => {
        document.querySelectorAll("#ksmTokenHistoryFilters .ksm-filter-tab").forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        currentFilter = tab.dataset.filter;
        render();
      });
    });
  }

  function initSelection() {
    const selectAll = document.getElementById("ksmSelectAllHistory");
    const deleteButton = document.getElementById("ksmDeleteSelectedHistory");

    selectAll?.addEventListener("change", () => {
      visibleHistory().forEach((item) => {
        if (selectAll.checked) selectedIds.add(item.id);
        else selectedIds.delete(item.id);
      });
      render();
    });

    deleteButton?.addEventListener("click", async () => {
      if (!selectedIds.size) return;
      const count = selectedIds.size;
      if (!window.confirm(`Hapus ${count} riwayat token yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) return;

      deleteButton.disabled = true;
      try {
        const response = await authFetch(`${window.APP_CONFIG.apiBase}/token_history_delete.php`, {
          method: "POST",
          body: JSON.stringify({ ids: [...selectedIds] }),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || "Riwayat gagal dihapus.");

        selectedIds.clear();
        await KsmTokenWallet.refresh();
        if (typeof window.showToast === "function") window.showToast(`${data.deleted} riwayat berhasil dihapus.`, "success");
      } catch (error) {
        alert(error.message || "Riwayat gagal dihapus.");
        updateSelectionControls();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initSelection();
    render();
    window.addEventListener("ksm-token-wallet:updated", render);
  });
})();
