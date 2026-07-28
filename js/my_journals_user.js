// JURNAL SAYA — data server, filter status, dan aksi berbasis ownership.
(function () {
  "use strict";

  let currentFilter = "all";
  let journals = [];
  let loading = false;

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function statusBadge(status) {
    const map = {
      draft: { label: "Draft", icon: "file", cls: "ksm-status-pending" },
      pending: { label: "Pending Review", icon: "clock", cls: "ksm-status-pending" },
      published: { label: "Terbit", icon: "check-circle", cls: "ksm-status-published" },
      rejected: { label: "Ditolak", icon: "x-circle", cls: "ksm-status-rejected" },
    };
    const info = map[status] || map.pending;
    return `<span class="ksm-status-badge ${info.cls}"><i data-feather="${info.icon}"></i>${info.label}</span>`;
  }

  function formatDate(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "-";
    return date.toLocaleDateString("id-ID", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function render() {
    const container = document.getElementById("ksmMyJournalsList");
    if (!container) return;

    if (loading) {
      container.innerHTML = `<div class="ksm-my-journals-empty"><p>Memuat jurnal Anda...</p></div>`;
      return;
    }

    const filtered =
      currentFilter === "all"
        ? journals
        : journals.filter((item) => item.status === currentFilter);

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="ksm-my-journals-empty">
          <i data-feather="inbox"></i>
          <h3>Belum ada jurnal di kategori ini</h3>
          <p>Upload jurnal baru untuk mulai membangun portofolio Anda.</p>
        </div>
      `;
      if (typeof feather !== "undefined") feather.replace();
      return;
    }

    container.innerHTML = filtered
      .map((item) => {
        const canChange = ["draft", "pending", "rejected"].includes(item.status);
        const rejectionReason = item.status === "rejected" && item.rejection_reason
          ? `<div class="ksm-my-journal-meta"><span>Alasan: ${escapeHtml(item.rejection_reason)}</span></div>`
          : "";

        return `
          <div class="ksm-my-journal-card" data-id="${item.id}">
            <div class="ksm-my-journal-info">
              <div class="ksm-my-journal-type-icon type-jurnal">
                <i data-feather="book-open"></i>
              </div>
              <div class="ksm-my-journal-text">
                <div class="ksm-my-journal-title">${escapeHtml(item.title)}</div>
                <div class="ksm-my-journal-meta">
                  <span>Jurnal</span>
                  <span>&bull;</span>
                  <span>${formatDate(item.created_at)}</span>
                </div>
                ${rejectionReason}
              </div>
            </div>

            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
              ${statusBadge(item.status)}
              <div class="ksm-my-journal-actions">
                <button type="button" class="ksm-icon-btn" data-action="edit" title="Edit" ${
                  canChange ? "" : "disabled"
                }>
                  <i data-feather="edit-2"></i>
                </button>
                <button type="button" class="ksm-icon-btn ksm-icon-btn-danger" data-action="delete" title="Hapus" ${
                  canChange ? "" : "disabled"
                }>
                  <i data-feather="trash-2"></i>
                </button>
              </div>
            </div>
          </div>
        `;
      })
      .join("");

    if (typeof feather !== "undefined") feather.replace();
  }

  async function apiJson(url, options = {}) {
    if (typeof authFetch !== "function") throw new Error("Layanan autentikasi belum siap.");
    const response = await authFetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.message || "Permintaan gagal.");
    return data;
  }

  async function loadJournals() {
    loading = true;
    render();
    try {
      const data = await apiJson(`${window.APP_CONFIG.apiBase}/my_journals.php?limit=100&offset=0`);
      journals = Array.isArray(data.results) ? data.results : [];
    } catch (error) {
      journals = [];
      if (typeof showToast === "function") showToast(error.message, "error");
    } finally {
      loading = false;
      render();
    }
  }

  async function handleListClick(e) {
    const btn = e.target.closest(".ksm-icon-btn");
    if (!btn || btn.disabled) return;

    const card = btn.closest(".ksm-my-journal-card");
    const id = card?.dataset.id;
    const action = btn.dataset.action;

    if (action === "delete") {
      (async () => {
        const confirmed =
          typeof showAlert !== "undefined"
            ? await showAlert.confirm(
                "Karya ini akan dihapus permanen dari daftar Anda.",
                "Hapus Karya?",
              )
            : confirm("Hapus karya ini?");

        if (!confirmed) return;

        try {
          await apiJson(`${window.APP_CONFIG.apiBase}/delete_my_journal.php?id=${encodeURIComponent(id)}`, { method: "DELETE" });
          journals = journals.filter((item) => String(item.id) !== String(id));
          render();
          if (typeof showToast === "function") showToast("Jurnal berhasil dihapus.", "success");
        } catch (error) {
          if (typeof showToast === "function") showToast(error.message, "error");
        }
      })();
    }

    if (action === "edit") {
      const journal = journals.find((item) => String(item.id) === String(id));
      if (!journal) return;
      const title = prompt("Judul jurnal:", journal.title || "");
      if (title === null) return;
      const abstract = prompt("Abstrak jurnal:", journal.abstract || "");
      if (abstract === null) return;
      const volume = prompt("Volume jurnal:", journal.volume || "");
      if (volume === null) return;
      try {
        const data = await apiJson(`${window.APP_CONFIG.apiBase}/update_my_journal.php`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: Number(id), title, abstract, volume }),
        });
        journals = journals.map((item) => String(item.id) === String(id)
          ? { ...item, ...(data.submission || {}), status: "pending", rejection_reason: null }
          : item);
        render();
        if (typeof showToast === "function") showToast(data.message || "Jurnal berhasil diperbarui.", "success");
      } catch (error) {
        if (typeof showToast === "function") showToast(error.message, "error");
      }
    }
  }

  function initFilters() {
    const tabs = document.querySelectorAll(
      "#ksmMyJournalsFilters .ksm-filter-tab",
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

    const list = document.getElementById("ksmMyJournalsList");
    if (list) list.addEventListener("click", handleListClick);
    window.addEventListener("ksm:journal-submitted", loadJournals);
    loadJournals();
  });
})();