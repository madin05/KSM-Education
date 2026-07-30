// ===== REVIEW JURNAL MASUK (ADMIN) =====
// Halaman: admin/review_journals.php
// Endpoint: services/admin_review_queue.php  (GET  — daftar per status)
//           services/admin_review_journal.php (POST — approve / reject)
//
// Jurnal yang dikirim user lewat submit_journal.php masuk dengan status
// 'pending' dan TIDAK tampil di publik sampai admin approve. Sebelumnya
// backend review sudah ada tapi tidak punya UI, jadi tidak pernah bisa
// dipakai. File ini yang menyediakan UI-nya.

(() => {
  const PER_PAGE = 10;

  const state = {
    status: "pending",
    sort: "oldest",
    search: "",
    page: 1,
    items: [], // semua item pada status aktif (hasil fetch)
    filtered: [],
    loading: false,
  };

  // ===== HELPERS =====
  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const formatDate = (value) => {
    if (!value) return "-";
    const d = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return escapeHtml(value);
    return d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const asList = (field) => {
    if (!field) return [];
    if (Array.isArray(field)) return field;
    try {
      const parsed = JSON.parse(field);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  };

  const notify = (msg, type = "success", title = "") => {
    if (typeof showToast === "function") showToast(msg, type, title);
    else console.log(`[${type}] ${msg}`);
  };

  const apiFetch = (url, options) =>
    (window.authFetch || fetch)(url, { cache: "no-store", ...options });

  const $ = (id) => document.getElementById(id);

  // ===== LOAD DATA =====
  async function loadQueue() {
    const tbody = $("reviewTableBody");
    if (!tbody) return;

    state.loading = true;
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="review-empty-cell">
          <div class="loader" style="margin:0 auto 12px;"></div>
          Memuat data review...
        </td>
      </tr>`;

    try {
      const url = `${window.APP_CONFIG.apiBase}/admin_review_queue.php?status=${encodeURIComponent(
        state.status,
      )}&limit=100&offset=0&t=${Date.now()}`;
      const res = await apiFetch(url);
      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Gagal memuat data review.");
      }

      state.items = Array.isArray(data.results) ? data.results : [];
      applyFilters();

      // Badge jumlah pending selalu di-refresh saat tab pending dibuka
      if (state.status === "pending") setPendingCount(data.total ?? state.items.length);
      else refreshPendingCount();
    } catch (err) {
      console.error("Review queue error:", err);
      state.items = [];
      state.filtered = [];
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="review-empty-cell review-error-cell">
            <i data-feather="alert-circle"></i>
            ${escapeHtml(err.message)}
          </td>
        </tr>`;
      updateTotal(0);
      renderPagination(0);
      if (typeof feather !== "undefined") feather.replace();
    } finally {
      state.loading = false;
    }
  }

  // Ambil jumlah pending tanpa mengganggu tabel (dipakai saat tab lain aktif)
  async function refreshPendingCount() {
    try {
      const url = `${window.APP_CONFIG.apiBase}/admin_review_queue.php?status=pending&limit=1&offset=0&t=${Date.now()}`;
      const res = await apiFetch(url);
      const data = await res.json();
      if (data.ok) setPendingCount(data.total ?? 0);
    } catch (e) {
      /* badge bukan fitur kritis — abaikan kegagalan */
    }
  }

  function setPendingCount(total) {
    const badge = $("countPending");
    if (!badge) return;
    badge.textContent = total;
    badge.classList.toggle("has-items", Number(total) > 0);
  }

  // ===== FILTER + SORT =====
  function applyFilters() {
    const query = state.search.toLowerCase().trim();
    let items = [...state.items];

    if (query) {
      items = items.filter((item) => {
        const haystack = [
          item.title,
          item.abstract,
          item.owner_name,
          item.owner_email,
          item.email,
          ...asList(item.authors),
          ...asList(item.tags),
        ]
          .filter(Boolean)
          .join(" ")
          .toLowerCase();
        return haystack.includes(query);
      });
    }

    switch (state.sort) {
      case "newest":
        items.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        break;
      case "title":
        items.sort((a, b) => String(a.title).localeCompare(String(b.title)));
        break;
      case "oldest":
      default:
        items.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        break;
    }

    state.filtered = items;
    state.page = 1;
    render();
  }

  // ===== RENDER =====
  function render() {
    const tbody = $("reviewTableBody");
    if (!tbody) return;

    updateTotal(state.filtered.length);

    if (state.filtered.length === 0) {
      const emptyMsg = state.search
        ? "Tidak ada jurnal yang cocok dengan pencarian."
        : state.status === "pending"
          ? "Tidak ada jurnal yang menunggu review."
          : "Belum ada data pada status ini.";
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="review-empty-cell">
            <i data-feather="inbox"></i>
            ${escapeHtml(emptyMsg)}
          </td>
        </tr>`;
      renderPagination(0);
      if (typeof feather !== "undefined") feather.replace();
      return;
    }

    const start = (state.page - 1) * PER_PAGE;
    const pageItems = state.filtered.slice(start, start + PER_PAGE);

    tbody.innerHTML = pageItems.map(rowHtml).join("");
    renderPagination(Math.ceil(state.filtered.length / PER_PAGE));
    if (typeof feather !== "undefined") feather.replace();
  }

  function statusBadge(status) {
    const map = {
      pending: ["badge-pending", "Menunggu"],
      published: ["badge-published", "Disetujui"],
      rejected: ["badge-rejected", "Ditolak"],
      draft: ["badge-draft", "Draft"],
    };
    const [cls, label] = map[status] || ["badge-draft", status || "-"];
    return `<span class="review-badge ${cls}">${escapeHtml(label)}</span>`;
  }

  function rowHtml(item) {
    const authors = asList(item.authors);
    const authorLine = authors.length ? authors.join(", ") : "Tanpa penulis";
    const owner = item.owner_name || "Pengguna dihapus";

    const actions =
      item.status === "pending"
        ? `
        <button type="button" class="btn-review btn-review-approve" data-action="approve" data-id="${item.id}">
          <i data-feather="check"></i> Setujui
        </button>
        <button type="button" class="btn-review btn-review-reject" data-action="reject" data-id="${item.id}">
          <i data-feather="x"></i> Tolak
        </button>`
        : "";

    return `
      <tr data-row-id="${item.id}">
        <td>
          <button type="button" class="review-title-btn" data-action="detail" data-id="${item.id}">
            ${escapeHtml(item.title || "Untitled")}
          </button>
          <div class="review-subtext">${escapeHtml(authorLine)}</div>
          ${
            item.status === "rejected" && item.rejection_reason
              ? `<div class="review-reason"><i data-feather="alert-triangle"></i> ${escapeHtml(item.rejection_reason)}</div>`
              : ""
          }
        </td>
        <td>
          <div class="review-owner">${escapeHtml(owner)}</div>
          <div class="review-subtext">${escapeHtml(item.owner_email || item.email || "-")}</div>
        </td>
        <td class="review-date">${formatDate(item.created_at)}</td>
        <td>${statusBadge(item.status)}</td>
        <td class="col-action">
          <div class="review-actions">
            <button type="button" class="btn-review btn-review-detail" data-action="detail" data-id="${item.id}">
              <i data-feather="eye"></i> Detail
            </button>
            ${actions}
          </div>
        </td>
      </tr>`;
  }

  function updateTotal(count) {
    const el = $("totalCount");
    if (el) el.textContent = `${count} jurnal`;
  }

  function renderPagination(totalPages) {
    const container = $("reviewPagination");
    if (!container) return;

    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    container.innerHTML = `
      <div class="pill-pagination">
        <button type="button" class="prev-page" id="reviewPrev" ${state.page === 1 ? "disabled" : ""} aria-label="Halaman sebelumnya">
          <i data-feather="chevron-left"></i>
        </button>
        <div class="page-info">${state.page} of ${totalPages}</div>
        <button type="button" class="next-page" id="reviewNext" ${state.page === totalPages ? "disabled" : ""} aria-label="Halaman berikutnya">
          <i data-feather="chevron-right"></i>
        </button>
      </div>`;

    $("reviewPrev")?.addEventListener("click", () => {
      if (state.page > 1) {
        state.page -= 1;
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
    $("reviewNext")?.addEventListener("click", () => {
      if (state.page < totalPages) {
        state.page += 1;
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
  }

  // ===== DETAIL MODAL =====
  function openDetail(id) {
    const item = state.items.find((x) => String(x.id) === String(id));
    if (!item) return;

    const body = $("reviewDetailBody");
    const modal = $("reviewDetailModal");
    if (!body || !modal) return;

    const authors = asList(item.authors);
    const tags = asList(item.tags);
    const pengurus = asList(item.pengurus);

    body.innerHTML = `
      <h3 class="review-detail-heading">${escapeHtml(item.title || "Untitled")}</h3>
      <div class="review-detail-meta">
        ${statusBadge(item.status)}
        <span><i data-feather="user"></i> ${escapeHtml(item.owner_name || "-")}</span>
        <span><i data-feather="calendar"></i> ${formatDate(item.created_at)}</span>
      </div>
      <dl class="review-detail-grid">
        <dt>Abstrak</dt><dd>${escapeHtml(item.abstract || "-")}</dd>
        <dt>Penulis</dt><dd>${authors.length ? escapeHtml(authors.join(", ")) : "-"}</dd>
        <dt>Pengurus</dt><dd>${pengurus.length ? escapeHtml(pengurus.join(", ")) : "-"}</dd>
        <dt>Tags</dt><dd>${tags.length ? tags.map((t) => `<span class="tag">${escapeHtml(t)}</span>`).join(" ") : "-"}</dd>
        <dt>Volume</dt><dd>${escapeHtml(item.volume || "-")}</dd>
        <dt>Email</dt><dd>${escapeHtml(item.email || item.owner_email || "-")}</dd>
        <dt>Kontak</dt><dd>${escapeHtml(item.contact || "-")}</dd>
        <dt>File</dt><dd>${
          item.file_url
            ? `<a href="${escapeHtml(item.file_url)}" target="_blank" rel="noopener noreferrer">Buka file jurnal</a>`
            : "Tidak ada file"
        }</dd>
        ${
          item.rejection_reason
            ? `<dt>Alasan ditolak</dt><dd>${escapeHtml(item.rejection_reason)}</dd>`
            : ""
        }
      </dl>
      ${
        item.status === "pending"
          ? `<div class="review-detail-actions">
               <button type="button" class="btn-review btn-review-approve" data-action="approve" data-id="${item.id}">
                 <i data-feather="check"></i> Setujui
               </button>
               <button type="button" class="btn-review btn-review-reject" data-action="reject" data-id="${item.id}">
                 <i data-feather="x"></i> Tolak
               </button>
             </div>`
          : ""
      }`;

    modal.classList.add("active");
    document.body.style.overflow = "hidden";
    if (typeof feather !== "undefined") feather.replace();
  }

  function closeDetail() {
    $("reviewDetailModal")?.classList.remove("active");
    document.body.style.overflow = "auto";
  }

  // ===== REJECT MODAL =====
  let rejectTargetId = null;

  function openRejectModal(id) {
    const item = state.items.find((x) => String(x.id) === String(id));
    rejectTargetId = id;
    const hint = $("rejectTargetTitle");
    if (hint) hint.textContent = item ? item.title : "";
    const input = $("rejectReasonInput");
    if (input) input.value = "";
    $("rejectReasonModal")?.classList.add("active");
    document.body.style.overflow = "hidden";
    setTimeout(() => input?.focus(), 80);
  }

  function closeRejectModal() {
    rejectTargetId = null;
    $("rejectReasonModal")?.classList.remove("active");
    document.body.style.overflow = "auto";
  }

  // ===== REVIEW ACTION =====
  async function submitReview(id, action, reason = null) {
    const buttons = document.querySelectorAll(
      `[data-id="${id}"][data-action="approve"], [data-id="${id}"][data-action="reject"]`,
    );
    buttons.forEach((b) => (b.disabled = true));

    try {
      const res = await apiFetch(`${window.APP_CONFIG.apiBase}/admin_review_journal.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(reason ? { id, action, reason } : { id, action }),
      });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Gagal menyimpan hasil review.");
      }

      notify(
        action === "approve"
          ? "Jurnal disetujui dan sekarang tampil di publik."
          : "Jurnal ditolak. Alasan sudah tercatat untuk pengirim.",
        "success",
        action === "approve" ? "APPROVE BERHASIL" : "REJECT BERHASIL",
      );

      closeDetail();
      closeRejectModal();

      // Refresh daftar review + daftar publik (journals.php ikut ter-update)
      await loadQueue();
      window.dispatchEvent(
        new CustomEvent("journals:changed", { detail: { id, action } }),
      );
    } catch (err) {
      console.error("Review action error:", err);
      notify(err.message, "error", "REVIEW GAGAL");
      buttons.forEach((b) => (b.disabled = false));
    }
  }

  // ===== EVENT WIRING =====
  function setupTabs() {
    document.querySelectorAll(".review-tab").forEach((tab) => {
      tab.addEventListener("click", () => {
        if (state.loading) return;
        document.querySelectorAll(".review-tab").forEach((t) => {
          t.classList.remove("active");
          t.setAttribute("aria-selected", "false");
        });
        tab.classList.add("active");
        tab.setAttribute("aria-selected", "true");
        state.status = tab.dataset.status;
        state.page = 1;
        loadQueue();
      });
    });
  }

  function setupSearch() {
    const input = $("reviewSearchInput");
    const btn = $("btnReviewSearch");
    if (!input) return;

    // Live search (debounce) — tombol & Enter hanya memaksa filter ulang,
    // tidak lagi wajib diklik supaya pencarian jalan.
    let timer = null;
    input.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        state.search = input.value;
        applyFilters();
      }, 200);
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        clearTimeout(timer);
        state.search = input.value;
        applyFilters();
      }
      if (e.key === "Escape") {
        input.value = "";
        state.search = "";
        applyFilters();
      }
    });

    btn?.addEventListener("click", () => {
      clearTimeout(timer);
      state.search = input.value;
      applyFilters();
    });
  }

  function setupSortDropdown() {
    const btn = $("btnReviewSort");
    const menu = $("reviewSortMenu");
    const dropdown = btn?.closest(".sort-dropdown");
    if (!btn || !menu || !dropdown) return;

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      dropdown.classList.toggle("active");
      menu.classList.toggle("active");
    });

    menu.querySelectorAll(".review-sort-item").forEach((item) => {
      item.addEventListener("click", () => {
        menu
          .querySelectorAll(".review-sort-item")
          .forEach((i) => i.classList.remove("active"));
        item.classList.add("active");
        state.sort = item.dataset.sort;
        dropdown.classList.remove("active");
        menu.classList.remove("active");
        applyFilters();
      });
    });

    document.addEventListener("click", (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("active");
        menu.classList.remove("active");
      }
    });
  }

  function setupTableActions() {
    // Event delegation: baris tabel & modal detail di-render ulang terus,
    // jadi listener dipasang sekali di document.
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action][data-id]");
      if (!btn) return;

      const { action, id } = btn.dataset;
      if (action === "detail") {
        openDetail(id);
      } else if (action === "approve") {
        const item = state.items.find((x) => String(x.id) === String(id));
        const title = item ? item.title : "jurnal ini";
        if (typeof showConfirm === "function") {
          showConfirm(
            `Setujui "${title}"? Jurnal akan langsung tampil di halaman publik.`,
            () => submitReview(id, "approve"),
            "Konfirmasi Approve",
          );
        } else {
          submitReview(id, "approve");
        }
      } else if (action === "reject") {
        openRejectModal(id);
      }
    });

    $("rejectForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const reason = $("rejectReasonInput")?.value.trim();
      if (!reason) {
        notify("Alasan penolakan wajib diisi.", "warning", "VALIDASI GAGAL");
        return;
      }
      if (rejectTargetId) submitReview(rejectTargetId, "reject", reason);
    });
  }

  function setupModals() {
    $("closeReviewDetail")?.addEventListener("click", closeDetail);
    $("reviewDetailModal")
      ?.querySelector(".modal-overlay")
      ?.addEventListener("click", closeDetail);

    $("closeRejectReason")?.addEventListener("click", closeRejectModal);
    $("cancelReject")?.addEventListener("click", closeRejectModal);
    $("rejectReasonModal")
      ?.querySelector(".modal-overlay")
      ?.addEventListener("click", closeRejectModal);

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeRejectModal();
        closeDetail();
      }
    });
  }

  function init() {
    if (!$("reviewTableBody")) return;
    setupTabs();
    setupSearch();
    setupSortDropdown();
    setupTableActions();
    setupModals();
    loadQueue();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  // Re-load kalau identitas admin berubah (login/logout di tab yang sama)
  window.addEventListener("userIdentityChanged", () => {
    if ($("reviewTableBody")) loadQueue();
  });
})();
