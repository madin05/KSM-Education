// =========================================================
// JURNAL SAYA — render daftar upload user, filter status, hapus draft
//
// Data disimpan di localStorage key "ksm_my_journals" (lihat
// js/upload_journal_modal.js -> KsmMyJournals). Di-seed dengan data
// dummy kalau kosong supaya halaman ini enak dilihat saat demo.
//
// // TODO backend:
// // 1. Ganti seedDummyIfEmpty()/getMyJournals() dengan fetch API
// //    (mis. GET /api/my_journals.php) yang mengembalikan daftar
// //    upload milik user yang sedang login beserta status approve.
// // 2. Hapus (DELETE) & Edit harus memanggil endpoint sungguhan,
// //    dan idealnya hanya diizinkan untuk status "pending".
// =========================================================

(function () {
  let currentFilter = "all";

  function seedDummyIfEmpty() {
    const existing =
      typeof KsmMyJournals !== "undefined" ? KsmMyJournals.getMyJournals() : [];
    if (existing.length > 0) return;

    const dummy = [
      {
        id: "DUMMY1",
        title: "Dampak Digitalisasi terhadap Pendidikan Karakter",
        type: "jurnal",
        status: "published",
        createdAt: new Date(Date.now() - 86400000 * 12).toISOString(),
      },
      {
        id: "DUMMY2",
        title: "Refleksi Kebijakan Pendidikan Inklusif di Indonesia",
        type: "opini",
        status: "pending",
        createdAt: new Date(Date.now() - 86400000 * 2).toISOString(),
      },
      {
        id: "DUMMY3",
        title: "Analisis Kesenjangan Akses Internet di Daerah 3T",
        type: "jurnal",
        status: "rejected",
        createdAt: new Date(Date.now() - 86400000 * 20).toISOString(),
      },
    ];

    KsmMyJournals.saveMyJournals(dummy);
  }

  function statusBadge(status) {
    const map = {
      pending: { label: "Pending Review", icon: "clock", cls: "ksm-status-pending" },
      published: { label: "Terbit", icon: "check-circle", cls: "ksm-status-published" },
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

  function render() {
    const container = document.getElementById("ksmMyJournalsList");
    if (!container) return;

    const all =
      typeof KsmMyJournals !== "undefined" ? KsmMyJournals.getMyJournals() : [];

    const filtered =
      currentFilter === "all"
        ? all
        : all.filter((item) => item.status === currentFilter);

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="ksm-my-journals-empty">
          <i data-feather="inbox"></i>
          <h3>Belum ada karya di kategori ini</h3>
          <p>Upload jurnal atau opini baru untuk mulai membangun portofolio Anda.</p>
        </div>
      `;
      if (typeof feather !== "undefined") feather.replace();
      return;
    }

    container.innerHTML = filtered
      .map((item) => {
        const isPending = item.status === "pending";
        const typeIconClass =
          item.type === "opini" ? "type-opini" : "type-jurnal";
        const typeIcon = item.type === "opini" ? "message-square" : "book-open";

        return `
          <div class="ksm-my-journal-card" data-id="${item.id}">
            <div class="ksm-my-journal-info">
              <div class="ksm-my-journal-type-icon ${typeIconClass}">
                <i data-feather="${typeIcon}"></i>
              </div>
              <div class="ksm-my-journal-text">
                <div class="ksm-my-journal-title">${item.title}</div>
                <div class="ksm-my-journal-meta">
                  <span>${item.type === "opini" ? "Opini" : "Jurnal"}</span>
                  <span>&bull;</span>
                  <span>${formatDate(item.createdAt)}</span>
                </div>
              </div>
            </div>

            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
              ${statusBadge(item.status)}
              <div class="ksm-my-journal-actions">
                <button type="button" class="ksm-icon-btn" data-action="edit" title="Edit" ${
                  isPending ? "" : "disabled"
                }>
                  <i data-feather="edit-2"></i>
                </button>
                <button type="button" class="ksm-icon-btn ksm-icon-btn-danger" data-action="delete" title="Hapus" ${
                  isPending ? "" : "disabled"
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

  function handleListClick(e) {
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

        const all = KsmMyJournals.getMyJournals();
        const updated = all.filter((item) => item.id !== id);
        KsmMyJournals.saveMyJournals(updated);
        render();

        if (typeof showToast === "function") {
          showToast("Karya berhasil dihapus.", "success");
        }
      })();
    }

    if (action === "edit") {
      // TODO backend: idealnya membuka ksmUploadModal dalam mode edit
      // dengan field terisi data lama, lalu PUT/PATCH ke endpoint update.
      if (typeof showToast === "function") {
        showToast(
          "Fitur edit akan tersedia setelah endpoint update siap dari backend.",
          "info",
        );
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
    seedDummyIfEmpty();
    initFilters();
    render();

    const list = document.getElementById("ksmMyJournalsList");
    if (list) list.addEventListener("click", handleListClick);
  });
})();