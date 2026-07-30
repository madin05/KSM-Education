// ===== KOTAK MASUK KONTAK (ADMIN) =====
// Halaman : admin/contact_messages.php
// Endpoint: services/admin/contact_messages.php (GET  — daftar + filter status)
//           services/admin/contact_message.php  (PATCH — read / reply / close)
//
// Backend kedua endpoint ini sudah lengkap (termasuk require_admin()) tetapi
// sebelumnya tidak punya UI sama sekali, sehingga pesan dari user/kontak_user.php
// masuk ke database tanpa pernah bisa dibaca atau dibalas admin.

(() => {
  const PER_PAGE = 15;

  const state = {
    status: "new",
    page: 1,
    items: [],
    filtered: [],
    total: 0,
    active: null, // pesan yang sedang dibuka di modal
    loading: false,
  };

  // ===== HELPERS =====
  const $ = (id) => document.getElementById(id);

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

  const notify = (msg, type = "success", title = "") => {
    if (typeof showToast === "function") showToast(msg, type, title);
    else console.log(`[${type}] ${msg}`);
  };

  const apiFetch = (url, options) =>
    (window.authFetch || fetch)(url, { cache: "no-store", ...options });

  const STATUS_LABEL = {
    new: "Baru",
    read: "Dibaca",
    replied: "Dibalas",
    closed: "Ditutup",
  };

  const refreshIcons = () => {
    if (typeof feather !== "undefined") feather.replace();
  };

  // ===== LOAD =====
  async function loadMessages() {
    const tbody = $("contactTableBody");
    if (!tbody) return;

    state.loading = true;
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="review-empty-cell">
          <div class="loader" style="margin:0 auto 12px;"></div>
          Memuat pesan...
        </td>
      </tr>`;

    try {
      let url = `${window.APP_CONFIG.apiBase}/admin/contact_messages.php?limit=100&offset=0&t=${Date.now()}`;
      if (state.status) url += `&status=${encodeURIComponent(state.status)}`;

      const res = await apiFetch(url);
      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Gagal memuat kotak masuk.");
      }

      state.items = Array.isArray(data.results) ? data.results : [];
      state.total = Number(data.total || state.items.length);
      state.page = 1;
      applyFilter();
      updateTabCount();
    } catch (err) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="review-empty-cell review-error">
            ${escapeHtml(err.message || "Gagal memuat kotak masuk.")}
          </td>
        </tr>`;
      $("contactPagination").innerHTML = "";
      $("contactTotal").textContent = "";
    } finally {
      state.loading = false;
    }
  }

  // Hitung jumlah pesan berstatus "new" untuk badge tab & navbar.
  async function updateTabCount() {
    try {
      const url = `${window.APP_CONFIG.apiBase}/admin/contact_messages.php?status=new&limit=1&offset=0&t=${Date.now()}`;
      const res = await apiFetch(url);
      const data = await res.json();
      if (!res.ok || !data.ok) return;

      const count = Number(data.total || 0);
      const badge = $("countNew");
      if (badge) badge.textContent = count > 0 ? count : "";
    } catch (e) {
      /* badge bersifat opsional, abaikan error */
    }
  }

  // ===== FILTER + RENDER =====
  function applyFilter() {
    state.filtered = [...state.items];
    render();
  }

  function render() {
    const tbody = $("contactTableBody");
    if (!tbody) return;

    const totalPages = Math.max(1, Math.ceil(state.filtered.length / PER_PAGE));
    if (state.page > totalPages) state.page = totalPages;

    const start = (state.page - 1) * PER_PAGE;
    const rows = state.filtered.slice(start, start + PER_PAGE);

    $("contactTotal").textContent = `${state.filtered.length} pesan`;

    if (rows.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="review-empty-cell">
            Tidak ada pesan pada filter ini.
          </td>
        </tr>`;
      $("contactPagination").innerHTML = "";
      return;
    }

    tbody.innerHTML = rows
      .map((m) => {
        const status = String(m.status || "new").toLowerCase();
        const preview =
          String(m.message ?? "").length > 140
            ? String(m.message).slice(0, 140) + "..."
            : String(m.message ?? "");

        return `
          <tr data-id="${Number(m.id)}">
            <td>
              <div class="review-author">
                <strong>${escapeHtml(m.name)}</strong>
                <span>${escapeHtml(m.email)}</span>
              </div>
            </td>
            <td>
              <div class="review-title">${escapeHtml(m.subject || "(tanpa subjek)")}</div>
              <div class="review-abstract">${escapeHtml(preview)}</div>
            </td>
            <td>
              <span class="review-badge status-${escapeHtml(status)}">
                ${escapeHtml(STATUS_LABEL[status] || status)}
              </span>
            </td>
            <td class="review-date">${formatDate(m.created_at)}</td>
            <td class="col-actions">
              <button type="button" class="btn-detail" data-action="open" data-id="${Number(m.id)}">
                <i data-feather="mail"></i> Buka
              </button>
            </td>
          </tr>`;
      })
      .join("");

    renderPagination(totalPages);
    refreshIcons();
  }

  function renderPagination(totalPages) {
    const wrap = $("contactPagination");
    if (!wrap) return;

    if (totalPages <= 1) {
      wrap.innerHTML = "";
      return;
    }

    wrap.innerHTML = `
      <div class="pill-pagination">
        <button class="prev-page" type="button" ${state.page === 1 ? "disabled" : ""}>
          <i data-feather="chevron-left"></i>
        </button>
        <div class="page-info">${state.page} of ${totalPages}</div>
        <button class="next-page" type="button" ${state.page === totalPages ? "disabled" : ""}>
          <i data-feather="chevron-right"></i>
        </button>
      </div>`;

    wrap.querySelector(".prev-page")?.addEventListener("click", () => {
      if (state.page > 1) {
        state.page -= 1;
        render();
      }
    });
    wrap.querySelector(".next-page")?.addEventListener("click", () => {
      if (state.page < totalPages) {
        state.page += 1;
        render();
      }
    });

    refreshIcons();
  }

  // ===== MODAL =====
  function openModal(id) {
    const msg = state.items.find((m) => Number(m.id) === Number(id));
    if (!msg) return;

    state.active = msg;

    $("contactDetail").innerHTML = `
      <div><dt>Nama</dt><dd>${escapeHtml(msg.name)}</dd></div>
      <div><dt>Email</dt><dd>${escapeHtml(msg.email)}</dd></div>
      <div><dt>Subjek</dt><dd>${escapeHtml(msg.subject || "(tanpa subjek)")}</dd></div>
      <div><dt>Dikirim</dt><dd>${formatDate(msg.created_at)}</dd></div>
      <div class="full"><dt>Pesan</dt><dd class="review-detail-text">${escapeHtml(msg.message)}</dd></div>
      ${
        msg.admin_reply
          ? `<div class="full"><dt>Balasan sebelumnya</dt>
               <dd class="review-detail-text">${escapeHtml(msg.admin_reply)}
               <small class="field-hint">Dibalas ${formatDate(msg.replied_at)}</small></dd></div>`
          : ""
      }`;

    const textarea = $("contactReplyText");
    textarea.value = msg.admin_reply ? String(msg.admin_reply) : "";
    $("contactReplyCount").textContent = String(textarea.value.length);

    $("contactModal").hidden = false;
    document.body.style.overflow = "hidden";
    refreshIcons();
    textarea.focus();

    // Tandai sudah dibaca hanya bila masih berstatus "new".
    if (String(msg.status).toLowerCase() === "new") {
      sendAction("read", { silent: true });
    }
  }

  function closeModal() {
    $("contactModal").hidden = true;
    document.body.style.overflow = "";
    state.active = null;
  }

  // ===== AKSI (read / reply / close) =====
  async function sendAction(action, { silent = false } = {}) {
    if (!state.active) return;

    const payload = { id: Number(state.active.id), action };

    if (action === "reply") {
      const reply = $("contactReplyText").value.trim();
      if (reply === "") {
        notify("Balasan tidak boleh kosong.", "warning", "Validasi");
        return;
      }
      if (reply.length > 5000) {
        notify("Balasan maksimal 5000 karakter.", "warning", "Validasi");
        return;
      }
      payload.reply = reply;
    }

    const buttons = [$("contactReplyBtn"), $("contactCloseBtn")].filter(Boolean);
    if (!silent) buttons.forEach((b) => (b.disabled = true));

    try {
      const res = await apiFetch(`${window.APP_CONFIG.apiBase}/admin/contact_message.php`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Aksi gagal diproses.");
      }

      if (!silent) {
        notify(
          action === "reply" ? "Balasan terkirim." : "Pesan ditandai selesai.",
          "success",
          "Berhasil",
        );
        closeModal();
      }

      await loadMessages();
    } catch (err) {
      notify(err.message || "Aksi gagal diproses.", "error", "Gagal");
    } finally {
      buttons.forEach((b) => (b.disabled = false));
    }
  }

  // ===== EVENT BINDING =====
  function bind() {
    // Tab status
    document.querySelectorAll(".review-tab").forEach((tab) => {
      tab.addEventListener("click", () => {
        document.querySelectorAll(".review-tab").forEach((t) => {
          t.classList.remove("active");
          t.setAttribute("aria-selected", "false");
        });
        tab.classList.add("active");
        tab.setAttribute("aria-selected", "true");
        state.status = tab.dataset.status || "";
        loadMessages();
      });
    });

    $("contactRefresh")?.addEventListener("click", () => loadMessages());

    // Buka detail
    $("contactTableBody")?.addEventListener("click", (e) => {
      const btn = e.target.closest('[data-action="open"]');
      if (btn) openModal(btn.dataset.id);
    });

    // Modal
    document.querySelectorAll("[data-close-contact-modal]").forEach((el) => {
      el.addEventListener("click", closeModal);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !$("contactModal").hidden) closeModal();
    });

    $("contactReplyBtn")?.addEventListener("click", () => sendAction("reply"));
    $("contactCloseBtn")?.addEventListener("click", () => sendAction("close"));

    $("contactReplyText")?.addEventListener("input", (e) => {
      $("contactReplyCount").textContent = String(e.target.value.length);
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    bind();
    loadMessages();
  });
})();
