// ===== ADMIN NAV & TOMBOL MUAT ULANG =====
// Dipakai di semua halaman admin (dimuat dari admin/components/scripts.php).
//
// Dua tugas:
//   1. Mengisi badge jumlah antrean pada dropdown navbar (review, pesan kontak,
//      token) dari services/admin/dashboard_stats.php, plus titik penanda pada
//      trigger grup agar terlihat walau dropdown tertutup.
//   2. Memberi umpan balik visual pada semua tombol .btn-refresh: ikon berputar
//      + tombol dinonaktifkan sementara, sehingga klik ganda tidak menumpuk
//      request. Sebelumnya tombol tidak memberi tanda apa pun saat diklik.

(() => {
  "use strict";

  /* ------------------------------------------------------------------ *
   * 1. TOMBOL MUAT ULANG
   * ------------------------------------------------------------------ */

  const MIN_SPIN_MS = 650; // durasi minimum agar animasi terlihat

  /**
   * Set status memuat sebuah tombol refresh.
   * @param {HTMLElement} btn
   * @param {boolean} loading
   */
  function setRefreshLoading(btn, loading) {
    if (!btn) return;
    btn.classList.toggle("is-loading", loading);
    btn.setAttribute("aria-busy", loading ? "true" : "false");
    btn.disabled = loading;
  }

  // Diekspos agar script halaman bisa mengontrol manual bila perlu.
  window.setRefreshLoading = setRefreshLoading;

  // Delegasi pada fase capture: berjalan sebelum handler halaman, dan tetap
  // bekerja untuk tombol yang dirender belakangan.
  document.addEventListener(
    "click",
    (e) => {
      const btn = e.target.closest(".btn-refresh");
      if (!btn || btn.disabled) return;

      setRefreshLoading(btn, true);

      // Handler halaman umumnya async tanpa callback selesai, jadi status
      // dilepas setelah durasi minimum (atau lebih cepat jika halaman
      // memanggil setRefreshLoading(btn, false) sendiri).
      window.setTimeout(() => setRefreshLoading(btn, false), MIN_SPIN_MS);

      // Perbarui badge navbar setelah data halaman dimuat ulang.
      window.setTimeout(loadNavBadges, MIN_SPIN_MS + 150);
    },
    true
  );

  // Cegah tombol refresh dalam <form> ikut mengirim form (semua sudah
  // type="button", ini pengaman tambahan bila ada yang terlewat).
  document.querySelectorAll("form .btn-refresh:not([type])").forEach((btn) => {
    btn.setAttribute("type", "button");
  });

  /* ------------------------------------------------------------------ *
   * 2. BADGE ANTREAN DI NAVBAR
   * ------------------------------------------------------------------ */

  const nf = (value) => Number(value || 0).toLocaleString("id-ID");

  const BADGES = [
    { id: "navBadgeReview", pick: (s) => s.content && s.content.reviewPending },
    { id: "navBadgeContact", pick: (s) => s.contact && s.contact.new },
    { id: "navBadgeToken", pick: (s) => s.token && s.token.pendingRequests },
  ];

  function applyBadge(id, count) {
    const el = document.getElementById(id);
    if (!el) return;
    const n = Number(count || 0);
    el.textContent = n > 0 ? nf(n) : "";
    el.title = n > 0 ? `${nf(n)} item menunggu` : "";
  }

  // Titik merah pada trigger grup jika ada badge aktif di dalamnya.
  function syncGroupDots() {
    document.querySelectorAll(".admin-nav .nav-dropdown").forEach((group) => {
      const dot = group.querySelector(".nav-dot");
      if (!dot) return;
      const hasPending = Array.from(group.querySelectorAll(".nav-badge")).some(
        (badge) => badge.textContent.trim() !== ""
      );
      dot.hidden = !hasPending;
    });
  }

  async function loadNavBadges() {
    if (!document.querySelector(".admin-nav .nav-badge")) return;
    if (!window.APP_CONFIG || !window.APP_CONFIG.apiBase) return;

    try {
      const fetcher = window.authFetch || fetch;
      const res = await fetcher(
        `${window.APP_CONFIG.apiBase}/admin/dashboard_stats.php?t=${Date.now()}`,
        { cache: "no-store" }
      );
      const data = await res.json();
      if (!res.ok || !data.ok || !data.stats) return;

      BADGES.forEach(({ id, pick }) => applyBadge(id, pick(data.stats)));
      syncGroupDots();
    } catch (err) {
      // Navbar tidak boleh mengganggu halaman jika endpoint gagal/belum login.
      console.debug("Nav badge tidak dimuat:", err);
    }
  }

  window.refreshAdminNavBadges = loadNavBadges;

  /* ------------------------------------------------------------------ *
   * INISIALISASI
   * ------------------------------------------------------------------ */

  document.addEventListener("DOMContentLoaded", () => {
    loadNavBadges();

    // Segarkan berkala, hanya saat tab terlihat.
    window.setInterval(() => {
      if (!document.hidden) loadNavBadges();
    }, 60000);

    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) loadNavBadges();
    });
  });
})();
