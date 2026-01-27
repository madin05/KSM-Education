import { listJournals, listOpinions, updateViews } from "../api.js";
import { CONFIG } from "../config.js";
import {
  escapeHtml,
  formatDate,
  truncateText,
  parseJsonSafe,
} from "../utils.js";
import { showToast } from "../modules/toast.js";
import { setupShareHandler } from "../modules/share.js";

// Global State
let globalArticles = [];
const viewedArticles = new Set(
  JSON.parse(localStorage.getItem(CONFIG.STORAGE_KEYS.VIEWED_ARTICLES) || "[]"),
);

//  DATA LOADING
async function loadAllData() {
  console.log("Loading articles from database...");

  try {
    const [journalsRes, opinionsRes] = await Promise.all([
      listJournals(CONFIG.ITEMS_PER_PAGE, 0),
      listOpinions(CONFIG.ITEMS_PER_PAGE, 0).catch(() => ({
        ok: false,
        results: [],
      })),
    ]);

    let articles = [];

    if (journalsRes.ok && journalsRes.results) {
      articles.push(
        ...journalsRes.results.map((j) => normalizeArticle(j, "jurnal")),
      );
    }

    if (opinionsRes.ok && opinionsRes.results) {
      articles.push(
        ...opinionsRes.results.map((o) => normalizeArticle(o, "opini")),
      );
    }

    // Sort by date descending
    articles.sort((a, b) => new Date(b.date) - new Date(a.date));

    console.log(`✓ Loaded ${articles.length} articles`);
    return articles;
  } catch (err) {
    console.error("Failed to load data:", err);
    showToast("Gagal memuat artikel. Silakan refresh halaman.", "error");
    return [];
  }
}

function normalizeArticle(item, type) {
  let authors = parseJsonSafe(item.authors, [item.author_name || "Admin"]);
  let tags = parseJsonSafe(item.tags, []);

  if (!Array.isArray(authors)) authors = [authors];
  if (!Array.isArray(tags)) tags = [];

  return {
    id: item.id,
    type: type,
    title: item.title || "Untitled",
    abstract: item.abstract || item.description || "",
    authors: authors,
    authorName: authors[0] || "Admin",
    tags: tags,
    date: item.created_at || new Date().toISOString(),
    cover:
      item.cover_url ||
      "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
    views: parseInt(item.views || 0),
  };
}

//  RENDER UI
async function renderArticles() {
  const grid = document.getElementById("articlesGrid");
  const navBtn = document.getElementById("latestArticlesNavUser");

  if (!grid) return;

  // Loading state
  grid.innerHTML = `
        <div class="loading-state" style="text-align: center; padding: 60px 20px; color: #666;">
            <div style="width: 50px; height: 50px; border: 4px solid rgba(0,0,0,0.1); 
                border-top: 4px solid #3498db; border-radius: 50%; 
                animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>MEMUAT ARTIKEL...</p>
        </div>
    `;

  globalArticles = await loadAllData();

  if (globalArticles.length === 0) {
    grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <h3>BELUM ADA ARTIKEL</h3>
                <p>Artikel akan muncul di sini setelah admin mengupload jurnal</p>
            </div>
        `;
    return;
  }

  const displayArticles = globalArticles.slice(
    0,
    CONFIG.MAX_DASHBOARD_ARTICLES,
  );

  grid.innerHTML = displayArticles
    .map((article) => {
      const typeClass =
        article.type === "opini" ? "badge-opini" : "badge-jurnal";
      const typeLabel = article.type.toUpperCase();
      const shortAbstract = truncateText(article.abstract, 100);

      return `
        <div class="article-card">
            <div class="article-image-container" onclick="navigateToArticle('${article.id}', '${article.type}')">
                <img src="${article.cover}" alt="${escapeHtml(article.title)}" 
                     class="article-image" onerror="this.src='https://placehold.co/600x400?text=No+Image'">
                <span class="article-type-badge ${typeClass}">${typeLabel}</span>
            </div>
            <div class="article-content">
                <div class="article-meta">
                    <span><i data-feather="user"></i> ${escapeHtml(article.authorName)}</span>
                    <span><i data-feather="calendar"></i> ${formatDate(article.date)}</span>
                    <span><i data-feather="eye"></i> ${article.views}</span>
                </div>
                <div class="article-title" onclick="navigateToArticle('${article.id}', '${article.type}')">
                    ${escapeHtml(article.title)}
                </div>
                ${shortAbstract ? `<div class="article-excerpt">${escapeHtml(shortAbstract)}</div>` : ""}
                
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
                    <button class="btn-share-article" 
                        data-article-id="${article.id}" 
                        data-article-type="${article.type}" 
                        data-article-title="${escapeHtml(article.title)}">
                        <i data-feather="share-2"></i> SHARE
                    </button>
                </div>
            </div>
        </div>`;
    })
    .join("");

  // Show "Lihat Semua" button if more than 6 articles
  if (navBtn) {
    navBtn.innerHTML =
      globalArticles.length > CONFIG.MAX_DASHBOARD_ARTICLES
        ? `<button class="btn-see-all" onclick="window.location.href='journals_user.html'">LIHAT SEMUA ARTIKEL</button>`
        : "";
  }

  // Re-render feather icons
  if (window.feather) feather.replace();

  // Render categories from loaded data
  renderCategories(globalArticles);
}

//  CATEGORIES (REUSE DATA, NO DUPLICATE FETCH)
function renderCategories(articles) {
  const grid = document.querySelector(".categories-grid");
  if (!grid) return;

  const categoryMap = new Map();

  articles.forEach((article) => {
    article.tags.forEach((tag) => {
      if (!tag) return;
      const normalized = tag
        .trim()
        .toLowerCase()
        .replace(/\b\w/g, (l) => l.toUpperCase());
      categoryMap.set(normalized, (categoryMap.get(normalized) || 0) + 1);
    });
  });

  const topCategories = [...categoryMap.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 12);

  if (topCategories.length === 0) {
    grid.innerHTML =
      '<div style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px;">Belum ada kategori</div>';
    return;
  }

  grid.innerHTML = topCategories
    .map(
      ([name, count]) => `
        <div class="category-card" onclick="window.location.href='journals_user.html?category=${encodeURIComponent(name)}'">
            <span class="category-name">${escapeHtml(name)}</span>
            <span class="category-count">(${count})</span>
        </div>
    `,
    )
    .join("");
}

//  NAVIGATION & VIEW TRACKING
window.navigateToArticle = (id, type) => {
  const key = String(id);

  if (!viewedArticles.has(key)) {
    viewedArticles.add(key);
    localStorage.setItem(
      CONFIG.STORAGE_KEYS.VIEWED_ARTICLES,
      JSON.stringify([...viewedArticles]),
    );
    updateViews(id, type); // Fire and forget
  }

  const page =
    type === "opini" ? "explore_opini_user.html" : "explore_jurnal_user.html";
  window.location.href = `${page}?id=${id}&type=${type}`;
};

//  AUTH & UI SETUP
function checkLoginStatus() {
  return sessionStorage.getItem(CONFIG.STORAGE_KEYS.USER_LOGGED_IN) === "true";
}

function setupGuestMode() {
  const isLoggedIn = checkLoginStatus();
  const loggedInElements = [
    document.getElementById("userProfile"),
    document.getElementById("logoutBtn"),
    document.querySelector(".user-info-section"),
  ];

  if (!isLoggedIn) {
    loggedInElements.forEach((el) => el && (el.style.display = "none"));

    const navbar = document.querySelector(".navbar");
    if (navbar && !document.getElementById("guestLoginBtn")) {
      const loginBtn = document.createElement("a");
      loginBtn.id = "guestLoginBtn";
      loginBtn.href = "./login_user.html";
      loginBtn.className = "btn-guest-login";
      loginBtn.innerHTML = `<i data-feather="log-in"></i> LOGIN`;
      navbar.appendChild(loginBtn);
    }

    const userNameEl = document.querySelector(".user-name");
    const userAvatarEl = document.querySelector(".user-avatar");
    if (userNameEl) userNameEl.textContent = "GUEST";
    if (userAvatarEl) userAvatarEl.textContent = "G";
  } else {
    loggedInElements.forEach((el) => el && (el.style.display = "block"));
    document.getElementById("guestLoginBtn")?.remove();

    const userEmail = sessionStorage.getItem(CONFIG.STORAGE_KEYS.USER_EMAIL);
    if (userEmail) {
      const userName = userEmail.split("@")[0].toUpperCase();
      const userNameEl = document.querySelector(".user-name");
      const userAvatarEl = document.querySelector(".user-avatar");
      if (userNameEl) userNameEl.textContent = userName;
      if (userAvatarEl) userAvatarEl.textContent = userName.charAt(0);
    }
  }
}

function setupLogout() {
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      if (confirm("YAKIN INGIN LOGOUT?")) {
        sessionStorage.clear();
        localStorage.removeItem(CONFIG.STORAGE_KEYS.USER_EMAIL);
        window.location.href = "./login_user.html";
      }
    });
  }
}

function setupNewsletter() {
  const subscribeBtn = document.getElementById("subscribeBtn");
  const newsletterEmail = document.getElementById("newsletterEmail");

  if (subscribeBtn && newsletterEmail) {
    subscribeBtn.addEventListener("click", () => {
      const email = newsletterEmail.value.trim();
      if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast(
          "Terima kasih! Anda telah berhasil subscribe newsletter.",
          "success",
        );
        newsletterEmail.value = "";
      } else {
        showToast("Mohon masukkan email yang valid.", "error");
      }
    });

    newsletterEmail.addEventListener("keypress", (e) => {
      if (e.key === "Enter") subscribeBtn.click();
    });
  }
}

function setupSearch() {
  const searchInput = document.querySelector(".search-box input");
  if (searchInput) {
    searchInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        const query = searchInput.value.trim();
        if (query) {
          window.location.href = `journals_user.html?search=${encodeURIComponent(query)}`;
        }
      }
    });
  }
}

//  INIT
document.addEventListener("DOMContentLoaded", async () => {
  console.log("Initializing User Dashboard...");

  // Inject loading animation style
  const style = document.createElement("style");
  style.textContent = `@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }`;
  document.head.appendChild(style);

  setupGuestMode();
  setupLogout();
  setupNewsletter();
  setupSearch();
  setupShareHandler();

  await renderArticles();

  if (window.feather) feather.replace();
  console.log("✓ User Dashboard ready");
});
