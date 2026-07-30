// ===== RINGKASAN OPERASIONAL DASHBOARD ADMIN =====
// Halaman : admin/dashboard_admin.php
// Endpoint: services/admin/dashboard_stats.php
// Menghubungkan dashboard ke data DB nyata: antrean review, kotak masuk kontak,
// top-up token, pengguna, dan pengunjung. Tiap kartu menautkan ke halaman detail.

(() => {
  const nf = (value) => Number(value || 0).toLocaleString("id-ID");

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };

  const setBadge = (id, count) => {
    const el = document.getElementById(id);
    if (!el) return;
    if (count > 0) {
      el.textContent = nf(count);
      el.hidden = false;
    } else {
      el.hidden = true;
    }
  };

  async function loadOverview() {
    const grid = document.getElementById("adminOverviewGrid");
    if (!grid) return;

    const errorBox = document.getElementById("adminOverviewError");
    if (errorBox) errorBox.hidden = true;

    try {
      const fetcher = window.authFetch || fetch;
      const res = await fetcher(
        `${window.APP_CONFIG.apiBase}/admin/dashboard_stats.php?t=${Date.now()}`,
        { cache: "no-store" }
      );
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Gagal memuat ringkasan dashboard.");
      }

      const s = data.stats;

      // Antrean review (jurnal + opini)
      setText("ovReviewPending", nf(s.content.reviewPending));
      setText(
        "ovReviewDetail",
        `${nf(s.content.journalsPending)} jurnal · ${nf(
          s.content.opinionsPending
        )} opini`
      );
      setBadge("badgeReview", s.content.reviewPending);

      // Kotak masuk kontak
      setText("ovContactNew", nf(s.contact.new));
      setText(
        "ovContactDetail",
        `${nf(s.contact.open)} belum selesai · ${nf(s.contact.total)} total`
      );
      setBadge("badgeContact", s.contact.new);

      // Top-up token
      setText("ovTokenPending", nf(s.token.pendingRequests));
      setText(
        "ovTokenDetail",
        `${nf(s.token.pendingAmount)} token diminta · ${nf(
          s.token.circulatingBalance
        )} beredar`
      );
      setBadge("badgeToken", s.token.pendingRequests);

      // Pengunjung
      setText("ovVisitorToday", nf(s.visitors.today));
      setText(
        "ovVisitorDetail",
        `${nf(s.visitors.last7d)} kunjungan 7 hari · ${nf(
          s.visitors.unique
        )} unik`
      );

      // Konten terbit
      setText("ovPublished", nf(s.content.articlesPublished));
      setText(
        "ovPublishedDetail",
        `${nf(s.content.journalsPublished)} jurnal · ${nf(
          s.content.opinionsPublished
        )} opini · ${nf(s.content.totalViews)} views`
      );

      // Pengguna
      setText("ovUsers", nf(s.users.total));
      setText("ovUsersDetail", `${nf(s.users.new7d)} pendaftar baru (7 hari)`);

      grid.classList.remove("is-loading");
      if (typeof feather !== "undefined") feather.replace();
    } catch (err) {
      if (errorBox) {
        errorBox.textContent = err.message || "Gagal memuat ringkasan dashboard.";
        errorBox.hidden = false;
      }
    }
  }

  function init() {
    loadOverview();
    document
      .getElementById("adminOverviewRefresh")
      ?.addEventListener("click", loadOverview);

    // Ikut menyegarkan saat konten berubah dari halaman lain.
    window.addEventListener("journals:changed", loadOverview);
    window.addEventListener("opinions:changed", loadOverview);

    // Auto refresh tiap 60 detik agar antrean tetap aktual.
    setInterval(loadOverview, 60000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
