// ===== ANALITIK PENGUNJUNG (ADMIN) =====
// Halaman : admin/visitor_analytics.php
// Endpoint: services/admin/visitor_analytics.php?days=7|30|90
// Grafik dibuat dengan div/CSS agar tidak perlu library chart tambahan.

(() => {
  const state = { days: 30, busy: false };

  const $ = (id) => document.getElementById(id);

  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const nf = (value) => Number(value || 0).toLocaleString("id-ID");

  const formatDay = (iso) => {
    const d = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString("id-ID", { day: "numeric", month: "short" });
  };

  const formatDateTime = (value) => {
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

  const apiFetch = (url) =>
    (window.authFetch || fetch)(url, { cache: "no-store" });

  async function load() {
    if (state.busy) return;
    state.busy = true;
    $("visitorError").hidden = true;

    try {
      const res = await apiFetch(
        `${window.APP_CONFIG.apiBase}/admin/visitor_analytics.php?days=${state.days}&t=${Date.now()}`
      );
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.message || "Gagal memuat analitik pengunjung.");
      }

      renderSummary(data.summary || {});
      renderDaily(data.daily || []);
      renderPages(data.topPages || []);
      renderDevices(data.devices || []);
      renderHourly(data.hourly || []);
      renderRecent(data.recent || []);

      if (typeof feather !== "undefined") feather.replace();
    } catch (err) {
      const box = $("visitorError");
      box.textContent = err.message || "Gagal memuat analitik pengunjung.";
      box.hidden = false;
    } finally {
      state.busy = false;
    }
  }

  function renderSummary(s) {
    const set = (id, value) => {
      const el = $(id);
      if (el) el.textContent = value;
    };

    set("statToday", nf(s.todayVisits));
    set("statTodayUnique", `${nf(s.todayUniques)} pengunjung unik`);
    set("statRange", nf(s.rangeVisits));
    set("statRangeUnique", `${nf(s.rangeUniques)} unik / ${state.days} hari`);
    set("statTotal", nf(s.totalVisits));
    set(
      "statFirstVisit",
      s.firstVisit ? `sejak ${formatDateTime(s.firstVisit)}` : "belum ada data"
    );
    set("statUnique", nf(s.uniqueVisitors));
    set(
      "statLastVisit",
      s.lastVisit ? `terakhir ${formatDateTime(s.lastVisit)}` : ""
    );
  }

  function renderDaily(daily) {
    const box = $("dailyChart");
    if (!daily.length) {
      box.innerHTML = '<div class="visitor-empty">Belum ada data kunjungan.</div>';
      $("dailyPeak").textContent = "";
      return;
    }

    const max = Math.max(...daily.map((d) => d.visits), 1);
    const peak = daily.reduce((a, b) => (b.visits > a.visits ? b : a), daily[0]);
    $("dailyPeak").textContent = peak.visits
      ? `Puncak: ${nf(peak.visits)} kunjungan (${formatDay(peak.date)})`
      : "";

    box.innerHTML = daily
      .map((d) => {
        const height = Math.round((d.visits / max) * 100);
        return `
        <div class="visitor-bar-col" title="${formatDay(d.date)}: ${nf(
          d.visits
        )} kunjungan, ${nf(d.uniques)} unik">
          <div class="visitor-bar" style="height:${Math.max(height, 2)}%">
            <span class="visitor-bar-value">${nf(d.visits)}</span>
          </div>
          <span class="visitor-bar-label">${formatDay(d.date)}</span>
        </div>`;
      })
      .join("");
  }

  function renderHourly(hourly) {
    const box = $("hourlyChart");
    const total = hourly.reduce((sum, h) => sum + h.visits, 0);
    if (!total) {
      box.innerHTML = '<div class="visitor-empty">Belum ada data kunjungan.</div>';
      $("hourPeak").textContent = "";
      return;
    }

    const max = Math.max(...hourly.map((h) => h.visits), 1);
    const peak = hourly.reduce((a, b) => (b.visits > a.visits ? b : a), hourly[0]);
    $("hourPeak").textContent = `Jam tersibuk: ${String(peak.hour).padStart(
      2,
      "0"
    )}:00 (${nf(peak.visits)} kunjungan)`;

    box.innerHTML = hourly
      .map((h) => {
        const height = Math.round((h.visits / max) * 100);
        return `
        <div class="visitor-bar-col" title="${String(h.hour).padStart(
          2,
          "0"
        )}:00 - ${nf(h.visits)} kunjungan">
          <div class="visitor-bar visitor-bar-alt" style="height:${Math.max(
            height,
            2
          )}%"></div>
          <span class="visitor-bar-label">${String(h.hour).padStart(2, "0")}</span>
        </div>`;
      })
      .join("");
  }

  function renderPages(pages) {
    const body = $("pagesBody");
    if (!pages.length) {
      body.innerHTML =
        '<tr><td colspan="3" class="review-empty-cell">Belum ada data halaman.</td></tr>';
      return;
    }

    body.innerHTML = pages
      .map(
        (p) => `
        <tr>
          <td class="visitor-page-cell">${escapeHtml(p.page)}</td>
          <td><strong>${nf(p.visits)}</strong></td>
          <td>${nf(p.uniques)}</td>
        </tr>`
      )
      .join("");
  }

  function renderDevices(devices) {
    const list = $("deviceList");
    if (!devices.length) {
      list.innerHTML = '<li class="visitor-empty">Belum ada data perangkat.</li>';
      return;
    }

    const total = devices.reduce((sum, d) => sum + d.visits, 0) || 1;
    list.innerHTML = devices
      .map((d) => {
        const pct = Math.round((d.visits / total) * 100);
        return `
        <li class="visitor-bar-row">
          <div class="visitor-bar-row-head">
            <span>${escapeHtml(d.label)}</span>
            <strong>${nf(d.visits)} <small>(${pct}%)</small></strong>
          </div>
          <div class="visitor-progress">
            <div class="visitor-progress-fill" style="width:${pct}%"></div>
          </div>
        </li>`;
      })
      .join("");
  }

  function renderRecent(recent) {
    const body = $("recentBody");
    if (!recent.length) {
      body.innerHTML =
        '<tr><td colspan="4" class="review-empty-cell">Belum ada kunjungan tercatat.</td></tr>';
      return;
    }

    body.innerHTML = recent
      .map(
        (r) => `
        <tr>
          <td>${formatDateTime(r.visitedAt)}</td>
          <td class="visitor-page-cell">${escapeHtml(r.page || "-")}</td>
          <td>${escapeHtml(r.device)}</td>
          <td><code>${escapeHtml(r.ip)}</code></td>
        </tr>`
      )
      .join("");
  }

  function init() {
    if (!$("dailyChart")) return;

    document.querySelectorAll("[data-days]").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("[data-days]").forEach((b) => {
          b.classList.remove("active");
          b.setAttribute("aria-selected", "false");
        });
        btn.classList.add("active");
        btn.setAttribute("aria-selected", "true");
        state.days = Number(btn.dataset.days);
        load();
      });
    });

    $("visitorRefresh")?.addEventListener("click", load);
    load();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
