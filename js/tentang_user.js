// ===== TENTANG KAMI — statistik ringkas =====
// Menghitung total artikel dari list_journals.php + list_opinions.php
// (sama seperti dashboard). Untuk total pengunjung, halaman ini
// mencoba beberapa kemungkinan endpoint statistik yang umum dipakai
// di project ini (get_stats.php / statistics.php) — kalau semuanya
// gagal, angka pengunjung disembunyikan dengan rapi (bukan error).

async function loadAboutStats() {
  const articleEl = document.getElementById("aboutArticleCount");
  const visitorEl = document.getElementById("aboutVisitorCount");

  try {
    const timestamp = Date.now();
    const [journalsRes, opinionsRes] = await Promise.all([
      fetch(`${window.APP_CONFIG.apiBase}/list_journals.php?limit=1&offset=0&t=${timestamp}`, {
        cache: "no-store",
      }),
      fetch(`${window.APP_CONFIG.apiBase}/list_opinions.php?limit=1&offset=0&t=${timestamp}`, {
        cache: "no-store",
      }).catch(() => ({ json: async () => ({ ok: false, total: 0 }) })),
    ]);

    const journalsData = await journalsRes.json();
    const opinionsData = await opinionsRes.json();

    const journalTotal = journalsData.total ?? (journalsData.results ? journalsData.results.length : 0);
    const opinionTotal = opinionsData.total ?? (opinionsData.results ? opinionsData.results.length : 0);

    if (articleEl) articleEl.textContent = journalTotal + opinionTotal;
  } catch (error) {
    console.warn("Gagal memuat jumlah artikel:", error);
    if (articleEl) articleEl.textContent = "-";
  }

  // TODO backend: sesuaikan endpoint ini kalau nama file statistik
  // pengunjung Anda berbeda (mis. get_stats.php, visitor_count.php, dst).
  try {
    const res = await fetch(`${window.APP_CONFIG.apiBase}/get_stats.php?t=${Date.now()}`, {
      cache: "no-store",
    });
    const data = await res.json();
    if (data.ok && visitorEl) {
      visitorEl.textContent = data.visitors ?? data.total_visitors ?? "-";
    } else if (visitorEl) {
      visitorEl.closest(".stat-highlight-card").style.display = "none";
    }
  } catch (error) {
    if (visitorEl) visitorEl.closest(".stat-highlight-card").style.display = "none";
  }
}

document.addEventListener("DOMContentLoaded", loadAboutStats);