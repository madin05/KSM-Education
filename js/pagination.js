// ===== PAGINATION MANAGER - DATABASE VERSION =====
//  Fully integrated with MySQL database, all features preserved

class PaginationManager {
  constructor(options = {}) {
    this.containerSelector = options.containerSelector || "#journalContainer";
    this.paginationSelector = options.paginationSelector || "#pagination";
    this.searchInputSelector = options.searchInputSelector || "#searchInput";
    this.sortSelectSelector = options.sortSelectSelector || "#sortSelect";
    this.filterSelectSelector = options.filterSelectSelector || "#filterSelect";

    this.itemsPerPage = options.itemsPerPage || 9;
    this.currentPage = 1;
    this.dataType = options.dataType || "jurnal";

    this.allItems = [];
    this.filteredItems = [];
    this.currentSort = "newest";
    this.currentFilter = "all";

    console.log(`PaginationManager initializing for ${this.dataType} (Database Mode)...`);
    this.init();
  }

  async init() {
    await this.loadData();
    this.setupSearch();
    this.setupSort();
    this.setupFilter();
    this.setupIconSort();
    this.applyFiltersAndSort();

    window.addEventListener(`${this.dataType}s:changed`, async () => {
      console.log(`${this.dataType}s changed event received`);
      await this.loadData();
      this.applyFiltersAndSort();
    });

    console.log(` PaginationManager initialized with ${this.allItems.length} ${this.dataType}s`);
  }

  // ===== LOAD DATA FROM DATABASE =====
  async loadData() {
    try {
      console.log(`📥 Loading ${this.dataType}s from database...`);

      const timestamp = Date.now();

      const endpoint =
        this.dataType === "jurnal"
          ? `/ksmaja/api/list_journals.php?limit=100&offset=0&t=${timestamp}`
          : `/ksmaja/api/list_opinion.php?limit=100&offset=0&t=${timestamp}`;

      const response = await fetch(endpoint, {
        cache: "no-store",
        headers: {
          "Cache-Control": "no-cache, no-store, must-revalidate",
          Pragma: "no-cache",
        },
      });

      const data = await response.json();

      if (data.ok && data.results) {
        this.allItems = data.results.map((item) => this.transformItem(item));
        this.filteredItems = [...this.allItems];

        console.log(`✅ Loaded ${this.allItems.length} ${this.dataType}s from database`);
      } else {
        console.warn(`⚠️ No ${this.dataType}s found in database`);
        this.allItems = [];
        this.filteredItems = [];
      }
    } catch (error) {
      console.error(`❌ Error loading ${this.dataType}s:`, error);

      console.warn("⚠️ Falling back to localStorage...");
      const storageKey = this.dataType === "jurnal" ? "journals" : "opinions";
      const stored = localStorage.getItem(storageKey);

      if (stored) {
        try {
          this.allItems = JSON.parse(stored);
          this.filteredItems = [...this.allItems];
          console.log(`📦 Loaded ${this.allItems.length} ${this.dataType}s from localStorage`);
        } catch (e) {
          console.error("Failed to parse localStorage:", e);
          this.allItems = [];
          this.filteredItems = [];
        }
      } else {
        this.allItems = [];
        this.filteredItems = [];
      }
    }
  }

  // ===== TRANSFORM DATABASE ITEM TO APP FORMAT =====
  transformItem(item) {
    const parseJsonField = (field) => {
      if (!field) return [];
      if (Array.isArray(field)) return field;
      try {
        return JSON.parse(field);
      } catch (e) {
        return [];
      }
    };

    if (this.dataType === "jurnal") {
      return {
        id: String(item.id),
        title: item.title || "Untitled",
        abstract: item.abstract || "",
        authors: parseJsonField(item.authors),
        tags: parseJsonField(item.tags),
        pengurus: parseJsonField(item.pengurus),
        date: item.created_at,
        uploadDate: item.created_at,
        fileData: item.file_url,
        file: item.file_url,
        coverImage:
          item.cover_url ||
          "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
        email: item.email || "",
        contact: item.contact || "",
        views: parseInt(item.views) || 0,
      };
    } else {
      return {
        id: String(item.id),
        title: item.title || "Untitled",
        description: item.description || "",
        category: item.category || "opini",
        author_name: item.author_name || "Anonymous",
        date: item.created_at,
        uploadDate: item.created_at,
        coverImage:
          item.cover_url ||
          "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
        fileUrl: item.file_url,
        file: item.file_url,
        views: parseInt(item.views) || 0,
      };
    }
  }

  // ===== RENDER ITEMS =====
  render() {
    const container = document.querySelector(this.containerSelector);
    if (!container) {
      console.warn("Container not found:", this.containerSelector);
      return;
    }

    container.innerHTML = "";

    // ✅ UPDATE COUNTER
    this.updateTotalCount();

    if (this.filteredItems.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">${this.dataType === "jurnal" ? "📚" : "📝"}</div>
          <h3>Tidak Ada ${this.dataType === "jurnal" ? "Jurnal" : "Opini"}</h3>
          <p>Belum ada ${this.dataType} yang tersedia</p>
        </div>
      `;
      return;
    }

    const start = (this.currentPage - 1) * this.itemsPerPage;
    const end = start + this.itemsPerPage;
    const itemsToShow = this.filteredItems.slice(start, end);

    itemsToShow.forEach((item) => {
      const card = this.createCard(item);
      container.appendChild(card);
    });

    this.renderPagination();

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }


  
  // ===== CREATE CARD =====
  createCard(item) {
    const card = document.createElement("div");
    card.className = this.dataType === "jurnal" ? "journal-card" : "opinion-card";
    card.setAttribute(`data-${this.dataType}-id`, item.id);

    const truncate = (text, max) => {
      if (!text) return "";
      return text.length > max ? text.substring(0, max) + "..." : text;
    };

    const formatDate = (dateString) => {
      try {
        return new Date(dateString).toLocaleDateString("id-ID", {
          day: "numeric",
          month: "short",
          year: "numeric",
        });
      } catch (e) {
        return dateString;
      }
    };

    if (this.dataType === "jurnal") {
      const author =
        Array.isArray(item.authors) && item.authors.length > 0 ? item.authors[0] : "Unknown";

      card.innerHTML = `
        <div class="journal-cover">
          <img src="${item.coverImage}" alt="${item.title}" 
               onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
          <div class="journal-views">
            <i data-feather="eye"></i> ${item.views}
          </div>
        </div>
        <div class="journal-content">
          <h3 class="journal-title">${truncate(item.title, 60)}</h3>
          <p class="journal-abstract">${truncate(item.abstract, 150)}</p>
          <div class="journal-meta">
            <span class="journal-author"><i data-feather="user"></i> ${author}</span>
            <span class="journal-date"><i data-feather="calendar"></i> ${formatDate(
              item.uploadDate
            )}</span>
          </div>
          ${
            item.tags && item.tags.length > 0
              ? `
            <div class="journal-tags">
              ${item.tags
                .slice(0, 3)
                .map((tag) => `<span class="tag">${tag}</span>`)
                .join("")}
              ${
                item.tags.length > 3 ? `<span class="tag-more">+${item.tags.length - 3}</span>` : ""
              }
            </div>
          `
              : ""
          }
          <div class="journal-actions">
            <a href="explore_jurnal_user.html?id=${item.id}&type=jurnal" class="btn-view">
              <i data-feather="eye"></i> Lihat Detail
            </a>
          </div>
        </div>
      `;
    } else {
      card.innerHTML = `
        <div class="opinion-cover">
          <img src="${item.coverImage}" alt="${item.title}"
               onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
          <div class="opinion-views">
            <i data-feather="eye"></i> ${item.views}
          </div>
        </div>
        <div class="opinion-content">
          <span class="opinion-category">${item.category}</span>
          <h3 class="opinion-title">${truncate(item.title, 60)}</h3>
          <p class="opinion-description">${truncate(item.description, 150)}</p>
          <div class="opinion-meta">
            <span class="opinion-author"><i data-feather="user"></i> ${item.author_name}</span>
            <span class="opinion-date"><i data-feather="calendar"></i> ${formatDate(
              item.uploadDate
            )}</span>
          </div>
          <div class="opinion-actions">
            <a href="explore_jurnal_user.html?id=${item.id}&type=opini" class="btn-view">
              <i data-feather="eye"></i> Lihat Detail
            </a>
          </div>
        </div>
      `;
    }

    return card;
  }

  // ===== RENDER PAGINATION =====
  renderPagination() {
    const paginationContainer = document.querySelector(this.paginationSelector);
    if (!paginationContainer) return;

    const totalPages = Math.ceil(this.filteredItems.length / this.itemsPerPage);

    if (totalPages <= 1) {
      paginationContainer.innerHTML = "";
      return;
    }

    paginationContainer.innerHTML = "";

    const prevBtn = document.createElement("button");
    prevBtn.textContent = "Previous";
    prevBtn.className = "pagination-btn";
    prevBtn.disabled = this.currentPage === 1;
    prevBtn.onclick = () => this.goToPage(this.currentPage - 1);
    paginationContainer.appendChild(prevBtn);

    const maxVisible = 5;
    let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

    if (endPage - startPage < maxVisible - 1) {
      startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
      const firstBtn = this.createPageButton(1);
      paginationContainer.appendChild(firstBtn);

      if (startPage > 2) {
        const ellipsis = document.createElement("span");
        ellipsis.textContent = "...";
        ellipsis.className = "pagination-ellipsis";
        paginationContainer.appendChild(ellipsis);
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      const pageBtn = this.createPageButton(i);
      paginationContainer.appendChild(pageBtn);
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        const ellipsis = document.createElement("span");
        ellipsis.textContent = "...";
        ellipsis.className = "pagination-ellipsis";
        paginationContainer.appendChild(ellipsis);
      }

      const lastBtn = this.createPageButton(totalPages);
      paginationContainer.appendChild(lastBtn);
    }

    const nextBtn = document.createElement("button");
    nextBtn.textContent = "Next";
    nextBtn.className = "pagination-btn";
    nextBtn.disabled = this.currentPage === totalPages;
    nextBtn.onclick = () => this.goToPage(this.currentPage + 1);
    paginationContainer.appendChild(nextBtn);
  }

  createPageButton(pageNum) {
    const btn = document.createElement("button");
    btn.textContent = pageNum;
    btn.className = pageNum === this.currentPage ? "pagination-btn active" : "pagination-btn";
    btn.onclick = () => this.goToPage(pageNum);
    return btn;
  }

  goToPage(pageNum) {
    this.currentPage = pageNum;
    this.render();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // ===== UPDATE TOTAL COUNT (DYNAMIC) ===== ✅ CUMA 1 INI YANG DIPAKE
  updateTotalCount() {
    const possibleIds = [
      "totalJournals", // journals_user.html
      "totalOpinions", // opinions_user.html
      "totalCount", // generic fallback
    ];

    for (const id of possibleIds) {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = this.filteredItems.length;
        console.log(`✅ Total count updated (${id}): ${this.filteredItems.length}`);
        return;
      }
    }

    console.warn("⚠️ Total count element not found");
  }

  // ===== SETUP SEARCH =====
  setupSearch() {
    const searchInput = document.querySelector(this.searchInputSelector);
    if (!searchInput) return;

    searchInput.addEventListener("input", (e) => {
      const query = e.target.value.toLowerCase().trim();
      this.applyFiltersAndSort(query);
    });
  }

  // ===== SETUP SORT =====
  setupSort() {
    const sortSelect = document.querySelector(this.sortSelectSelector);
    if (sortSelect) {
      sortSelect.addEventListener("change", (e) => {
        this.currentSort = e.target.value;
        this.applyFiltersAndSort();
      });
    }

    window.addEventListener("sortChanged", (e) => {
      this.currentSort = e.detail.sortValue;
      this.applyFiltersAndSort();
    });
  }

  // ===== SETUP FILTER =====
  setupFilter() {
    const filterSelect = document.querySelector(this.filterSelectSelector);
    if (!filterSelect) return;

    filterSelect.addEventListener("change", (e) => {
      this.currentFilter = e.target.value;
      this.applyFiltersAndSort();
    });
  }

  // ===== SETUP ICON SORT DROPDOWN =====
  setupIconSort() {
    const btnSort = document.getElementById("btnSort");
    const sortMenu = document.getElementById("sortMenu");
    const sortItems = document.querySelectorAll(".sort-item");
    const dropdown = document.querySelector(".sort-dropdown");

    if (!btnSort || !sortMenu) {
      console.log("⚠️ Icon sort dropdown not found, skipping...");
      return;
    }

    btnSort.addEventListener("click", (e) => {
      e.stopPropagation();
      dropdown.classList.toggle("active");
    });

    sortItems.forEach((item) => {
      item.addEventListener("click", (e) => {
        const sortValue = e.currentTarget.dataset.sort;

        sortItems.forEach((i) => i.classList.remove("active"));
        e.currentTarget.classList.add("active");

        dropdown.classList.remove("active");

        this.currentSort = sortValue;
        this.applyFiltersAndSort();

        console.log(`✅ Sort changed to: ${sortValue}`);
      });
    });

    document.addEventListener("click", (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("active");
      }
    });

    console.log("✅ Icon sort dropdown initialized");
  }

  // ===== APPLY FILTERS AND SORT =====
  applyFiltersAndSort(searchQuery = null) {
    let items = [...this.allItems];

    const query =
      searchQuery !== null
        ? searchQuery
        : document.querySelector(this.searchInputSelector)?.value.toLowerCase().trim() || "";

    if (query) {
      items = items.filter((item) => {
        if (this.dataType === "jurnal") {
          return (
            item.title.toLowerCase().includes(query) ||
            item.abstract.toLowerCase().includes(query) ||
            (Array.isArray(item.authors) &&
              item.authors.some((a) => a.toLowerCase().includes(query))) ||
            (Array.isArray(item.tags) && item.tags.some((t) => t.toLowerCase().includes(query)))
          );
        } else {
          return (
            item.title.toLowerCase().includes(query) ||
            item.description.toLowerCase().includes(query) ||
            item.author_name.toLowerCase().includes(query) ||
            item.category.toLowerCase().includes(query)
          );
        }
      });
    }

    if (this.currentFilter !== "all") {
      items = items.filter((item) => {
        if (this.dataType === "jurnal") {
          return Array.isArray(item.tags) && item.tags.includes(this.currentFilter);
        } else {
          return item.category === this.currentFilter;
        }
      });
    }

    if (this.currentSort === "newest") {
      items.sort((a, b) => new Date(b.uploadDate) - new Date(a.uploadDate));
    } else if (this.currentSort === "oldest") {
      items.sort((a, b) => new Date(a.uploadDate) - new Date(b.uploadDate));
    } else if (this.currentSort === "title") {
      items.sort((a, b) => a.title.localeCompare(b.title));
    } else if (this.currentSort === "views") {
      items.sort((a, b) => (b.views || 0) - (a.views || 0));
    }

    this.filteredItems = items;
    this.currentPage = 1;
    this.render();
  }
}

// ===== AUTO-INITIALIZE =====
let paginationManager;

document.addEventListener("DOMContentLoaded", () => {
  console.log(" DOM ready, initializing...");

  let container = document.getElementById("journalContainer");

  if (!container) {
    console.warn("⚠️ Container not found, creating one...");

    const main =
      document.querySelector("main") || document.querySelector(".container") || document.body;

    container = document.createElement("div");
    container.id = "journalContainer";
    container.style.cssText = `
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 30px;
      padding: 40px 0;
      min-height: 300px;
    `;
    main.appendChild(container);

    let pagination = document.getElementById("pagination");
    if (!pagination) {
      pagination = document.createElement("div");
      pagination.id = "pagination";
      pagination.style.cssText = `
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: 40px 0;
        flex-wrap: wrap;
      `;
      main.appendChild(pagination);
    }

    console.log(" Containers created");
  }

  const isOpinionsPage = window.location.pathname.includes("opinions");

  console.log("📍 Page detected:", isOpinionsPage ? "Opinions" : "Journals");

  paginationManager = new PaginationManager({
    containerSelector: "#journalContainer",
    paginationSelector: "#pagination",
    searchInputSelector: "#searchInput",
    sortSelectSelector: "#sortSelect",
    filterSelectSelector: "#filterSelect",
    itemsPerPage: 9,
    dataType: isOpinionsPage ? "opini" : "jurnal",
  });

  console.log(" PaginationManager initialized (Database Mode)");
  window.paginationManager = paginationManager;
});

console.log("pagination.js loaded (Database Mode)");
