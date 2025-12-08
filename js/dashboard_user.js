// ===== DASHBOARD USER - DATABASE MIGRATION VERSION =====

feather.replace();

// ===== LOGIN STATUS CHECK =====
function checkLoginStatus() {
  return sessionStorage.getItem("userLoggedIn") === "true";
}

// ===== LOAD ARTICLES FROM DATABASE =====

async function loadArticles() {
  try {
    console.log("📥 Loading articles from database...");

    // Anti-cache timestamp
    const timestamp = Date.now();

    // Fetch journals with timestamp
    const journalsResponse = await fetch(
      `/ksmaja/api/list_journals.php?limit=50&offset=0&t=${timestamp}`,
      {
        cache: "no-store",
        headers: { "Cache-Control": "no-cache" },
      }
    );
    const journalsData = await journalsResponse.json();

    // Fetch opinions with timestamp
    let opinionsData = { ok: false, results: [] };
    try {
      const opinionsResponse = await fetch(
        `/ksmaja/api/list_opinion.php?limit=50&offset=0&t=${timestamp}`,
        {
          cache: "no-store",
          headers: { "Cache-Control": "no-cache" },
        }
      );
      opinionsData = await opinionsResponse.json();
    } catch (e) {
      console.warn("No opinions endpoint, skipping...");
    }

    let journals = [];
    let opinions = [];

    // ... rest of the processing logic remains exactly the same ...
    // (Copy the rest of your existing processing logic here)

    // Process journals from database
    if (journalsData.ok && journalsData.results) {
      journals = journalsData.results.map((j) => {
        const authors = j.authors
          ? typeof j.authors === "string"
            ? JSON.parse(j.authors)
            : j.authors
          : [];
        const tags = j.tags ? (typeof j.tags === "string" ? JSON.parse(j.tags) : j.tags) : [];
        return {
          id: j.id,
          title: j.title,
          judul: j.title,
          abstract: j.abstract,
          abstrak: j.abstract,
          authors: authors,
          author: authors,
          penulis: authors.length > 0 ? authors[0] : "Admin",
          tags: tags,
          date: j.created_at,
          uploadDate: j.created_at,
          fileData: j.file_url,
          coverImage:
            j.cover_url ||
            "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
          cover: j.cover_url,
          views: j.views || 0,
          type: "jurnal",
        };
      });
    }

    // Process opinions from database
    if (opinionsData.ok && opinionsData.results) {
      opinions = opinionsData.results.map((o) => {
        return {
          id: o.id,
          title: o.title,
          judul: o.title,
          description: o.description,
          abstract: o.description,
          abstrak: o.description,
          category: o.category || "opini",
          author: [o.author_name || "Anonymous"],
          authors: [o.author_name || "Anonymous"],
          penulis: o.author_name || "Anonymous",
          date: o.created_at,
          uploadDate: o.created_at,
          coverImage:
            o.cover_url ||
            "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
          cover: o.cover_url,
          fileUrl: o.file_url,
          fileData: o.file_url,
          views: o.views || 0,
          type: "opini",
        };
      });
    }

    const articles = [...journals, ...opinions].sort((a, b) => {
      const dateA = new Date(a.uploadDate || a.date || 0);
      const dateB = new Date(b.uploadDate || b.date || 0);
      return dateB - dateA;
    });

    console.log(`📊 Total articles from database: ${articles.length}`);
    return articles;
  } catch (error) {
    console.error("❌ Error loading articles from database:", error);
    return [];
  }
}

let articles = []; // Initialize empty, will be loaded async

// ===== NAVIGATE TO ARTICLE DETAIL =====
function openArticleDetail(articleId, articleType) {
  console.log("Opening article:", articleId, articleType);
  window.location.href = `explore_jurnal_user.html?id=${articleId}&type=${articleType}`;
}

async function renderArticles() {
  const grid = document.getElementById("articlesGrid");
  const navUser = document.getElementById("latestArticlesNavUser");

  grid.innerHTML = `
    <div class="loading-state" style="text-align: center; padding: 60px 20px; color: #666;">
      <div style="width: 50px; height: 50px; border: 4px solid rgba(0,0,0,0.1); border-top: 4px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
      <p>MEMUAT ARTIKEL...</p>
    </div>
  `;
  if (navUser) navUser.innerHTML = ""; // kosongin dulu

  // DATA DARI DATABASE
  articles = await loadArticles();

  if (articles.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">📄</div>
        <h3>BELUM ADA ARTIKEL</h3>
        <p>ARTIKEL AKAN MUNCUL DI SINI SETELAH ADMIN MENGUPLOAD JURNAL</p>
      </div>
    `;
    return;
  }

  grid.innerHTML = articles
    .slice(0, 6)
    .map((article) => {
      const title = article.title || article.judul || "UNTITLED";
      const author = Array.isArray(article.authors)
        ? article.authors[0]
        : Array.isArray(article.author)
        ? article.author[0]
        : article.author || article.penulis || "ADMIN";

      const date = article.date || article.uploadDate || new Date().toISOString();
      const formattedDate = new Date(date).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });

      const coverImage =
        article.coverImage ||
        article.cover ||
        "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop";

      const views = article.views || 0;
      const abstract = article.abstract || article.abstrak || "";
      const truncatedAbstract =
        abstract.length > 100 ? abstract.substring(0, 100) + "..." : abstract;

      const typeLabel = article.type === "opini" ? "OPINI" : "JURNAL";
      const typeClass = article.type === "opini" ? "badge-opini" : "badge-jurnal";

      return `
        <div class="article-card" onclick="openArticleDetail('${article.id}', '${
        article.type
      }')" style="cursor: pointer;">
          <div class="article-image-container">
            <img src="${coverImage}" alt="${title}" class="article-image"
                 onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
            <span class="article-type-badge ${typeClass}">${typeLabel}</span>
          </div>
          <div class="article-content">
            <div class="article-meta">
              <span><i data-feather="user" style="width: 14px; height: 14px;"></i> ${author}</span>
              <span><i data-feather="calendar" style="width: 14px; height: 14px;"></i> ${formattedDate}</span>
              <span><i data-feather="eye" style="width: 14px; height: 14px;"></i> ${views}</span>
            </div>
            <div class="article-title">${title}</div>
            ${truncatedAbstract ? `<div class="article-excerpt">${truncatedAbstract}</div>` : ""}
          </div>
        </div>
      `;
    })
    .join("");

  // ====== TOMBOL LIHAT SEMUA ======
  if (navUser) {
    if (articles.length > 6) {
      navUser.innerHTML = `
        <button class="btn-see-all" onclick="window.location.href='journals_user.html'">
          LIHAT SEMUA ARTIKEL
        </button>
      `;
    } else {
      navUser.innerHTML = "";
    }
  }

  feather.replace();
}

// // ===== SYNC VISITOR COUNT =====
// function syncVisitorCount() {
//   const oldVisitorKey = parseInt(localStorage.getItem("visitorCount") || "0");
//   if (oldVisitorKey > 0) {
//     const stats = JSON.parse(localStorage.getItem("siteStatistics") || "{}");
//     stats.visitors = Math.max(stats.visitors || 0, oldVisitorKey);
//     stats.lastVisit = new Date().toISOString();
//     stats.uniqueVisitorId =
//       stats.uniqueVisitorId ||
//       "visitor_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
//     localStorage.setItem("siteStatistics", JSON.stringify(stats));
//     localStorage.removeItem("visitorCount");
//   }
// }

// ===== LOGOUT HANDLER =====
function setupLogout() {
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      if (confirm("YAKIN INGIN LOGOUT?")) {
        sessionStorage.removeItem("userLoggedIn");
        sessionStorage.removeItem("userEmail");
        sessionStorage.removeItem("userType");
        sessionStorage.removeItem("visitorTracked");
        localStorage.removeItem("userEmail");
        window.location.href = "./login_user.html";
      }
    });
  }
}

// ===== NEWSLETTER SUBSCRIPTION =====
function setupNewsletter() {
  const subscribeBtn = document.getElementById("subscribeBtn");
  const newsletterEmail = document.getElementById("newsletterEmail");

  if (subscribeBtn && newsletterEmail) {
    subscribeBtn.addEventListener("click", () => {
      const email = newsletterEmail.value.trim();
      if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert("TERIMA KASIH! ANDA TELAH BERHASIL SUBSCRIBE NEWSLETTER");
        newsletterEmail.value = "";
      } else {
        alert("MOHON MASUKKAN EMAIL YANG VALID");
      }
    });
  }
}

// ===== SET USER NAME =====
function setUserName() {
  const userEmail = sessionStorage.getItem("userEmail");
  if (userEmail) {
    const userName = userEmail.split("@")[0].toUpperCase();
    const userNameEl = document.querySelector(".user-name");
    const userAvatarEl = document.querySelector(".user-avatar");
    if (userNameEl) userNameEl.textContent = userName;
    if (userAvatarEl) userAvatarEl.textContent = userName.charAt(0);
  }
}

// ===== GUEST MODE SETUP =====
function setupGuestMode() {
  const isLoggedIn = checkLoginStatus();

  const loggedInElements = [
    document.getElementById("userProfile"),
    document.getElementById("logoutBtn"),
    document.querySelector(".user-info-section"),
  ];

  if (!isLoggedIn) {
    // GUEST MODE
    loggedInElements.forEach((el) => {
      if (el) el.style.display = "none";
    });

    // Show login button in navbar
    const navbar = document.querySelector(".navbar");
    if (navbar && !document.getElementById("guestLoginBtn")) {
      const loginBtn = document.createElement("a");
      loginBtn.id = "guestLoginBtn";
      loginBtn.href = "./login_user.html";
      loginBtn.className = "btn-guest-login";
      loginBtn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
        LOGIN
      `;
      navbar.appendChild(loginBtn);
    }

    const userNameEl = document.querySelector(".user-name");
    const userAvatarEl = document.querySelector(".user-avatar");
    if (userNameEl) userNameEl.textContent = "GUEST";
    if (userAvatarEl) userAvatarEl.textContent = "G";
  } else {
    // LOGGED IN MODE
    loggedInElements.forEach((el) => {
      if (el) el.style.display = "block";
    });

    const guestBtn = document.getElementById("guestLoginBtn");
    if (guestBtn) guestBtn.remove();

    setUserName();
  }
}

// // ===== REAL-TIME SYNC =====
// function startRealTimeSync() {
//   setInterval(async () => {
//     const currentCount = articles.length;
//     const newArticles = await loadArticles();

//     if (newArticles.length !== currentCount) {
//       console.log(`📊 Article count changed: ${currentCount} → ${newArticles.length}`);
//       articles = newArticles;
//       await renderArticles();

//       if (window.statsManager) {
//         window.statsManager.updateArticleCount();
//       }
//     }
//   }, 10000); // Check every 10 seconds (reduced from 5s to avoid too many requests)
// }

// ===== SEARCH FUNCTIONALITY =====
function setupSearch() {
  const searchInput = document.querySelector(".search-box input");

  if (searchInput) {
    searchInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        const query = searchInput.value.trim();
        if (query) {
          performSearch(query);
        }
      }
    });
  }
}

async function performSearch(query) {
  const articles = await loadArticles();
  const results = articles.filter((article) => {
    const title = (article.title || article.judul || "").toLowerCase();
    const abstract = (article.abstract || article.abstrak || "").toLowerCase();
    const author = Array.isArray(article.authors)
      ? article.authors.join(" ").toLowerCase()
      : (article.author || article.penulis || "").toLowerCase();

    const searchQuery = query.toLowerCase();
    return (
      title.includes(searchQuery) || abstract.includes(searchQuery) || author.includes(searchQuery)
    );
  });

  // Redirect to journals page with search query
  window.location.href = `journals_user.html?search=${encodeURIComponent(query)}`;
}

document.addEventListener("DOMContentLoaded", async () => {
  console.log("🚀 Initializing User Dashboard (Database Mode)...");

  // Boleh matiin kalau bikin error
  // syncVisitorCount();

  if (typeof StatisticsManager !== "undefined" && !window.statsManager) {
    window.statsManager = new StatisticsManager();
  }

  setupGuestMode();
  setupLogout();
  setupNewsletter();
  setupSearch();

  // INI YANG PENTING UNTUK UI USER
  await renderArticles();

  feather.replace();
  console.log("✅ User Dashboard ready");
});

// Add CSS for loading animation
const style = document.createElement("style");
style.textContent = `
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
`;
document.head.appendChild(style);
