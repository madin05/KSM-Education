// Import API functions
import {
  listOpinions,
  deleteOpinion as deleteOpinionAPI,
  updateViews,
} from "../api.js";

// Import transformers
import { transformOpinion } from "../utils/data-transformer.js";

/**
 * Opinions Manager - Manage opinions display and CRUD operations
 */
export class OpinionManager {
  constructor() {
    this.container = document.getElementById("opinionsContainer");
    this.opinionsPerPage = 12;
    this.currentPage = 1;
    this.opinions = [];
    this.filteredOpinions = [];
    this.currentFilter = "all";
    this.currentSort = "newest";

    console.log("OpinionManager initializing...");
    this.init();
  }

  async init() {
    if (!this.container) {
      console.warn("Opinions container not found!");
      return;
    }

    await this.loadOpinions();
    console.log(
      "OpinionManager initialized with",
      this.opinions.length,
      "opinions",
    );

    this.render();
    this.setupSort();
    this.setupSearch();
    this.renderPagination();

    // Listen to custom events
    window.addEventListener("opinions:changed", async () => {
      console.log("Opinions changed event triggered");
      await this.loadOpinions();
      this.applyFiltersAndSort();
    });
  }

  /**
   * Load opinions from database via API
   */
  async loadOpinions() {
    try {
      console.log("Loading opinions from database...");

      // Use API function instead of manual fetch
      const data = await listOpinions(100, 0);

      if (data.ok && data.results) {
        // Transform data using data-transformer
        this.opinions = data.results.map((o) => transformOpinion(o));
        this.filteredOpinions = [...this.opinions];
        console.log(`Loaded ${this.opinions.length} opinions from database`);
      } else {
        console.warn("No opinions found in database");
        this.opinions = [];
        this.filteredOpinions = [];
      }
    } catch (error) {
      console.error("Error loading opinions from database:", error);
      this.opinions = [];
      this.filteredOpinions = [];
    }
  }

  /**
   * Render opinions to container
   */
  render() {
    if (!this.container) return;

    // Empty state
    if (this.filteredOpinions.length === 0) {
      this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>Belum Ada Opini</h3>
                    <p>Artikel opini akan muncul di sini setelah admin mengupload</p>
                </div>
            `;
      return;
    }

    // Pagination calculation
    const start = (this.currentPage - 1) * this.opinionsPerPage;
    const end = start + this.opinionsPerPage;
    const opinionsToShow = this.filteredOpinions.slice(start, end);

    this.container.innerHTML = "";

    // Render opinion cards
    opinionsToShow.forEach((opinion) => {
      const card = this.createOpinionCard(opinion);
      this.container.appendChild(card);
    });

    // Refresh feather icons
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  /**
   * Create opinion card
   */
  createOpinionCard(opinion) {
    const card = document.createElement("div");
    card.className = "opinion-card";
    card.setAttribute("data-opinion-id", opinion.id);

    // Helper functions
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

    const getCategoryClass = (category) => {
      const categories = {
        opini: "category-opini",
        artikel: "category-artikel",
        berita: "category-berita",
        editorial: "category-editorial",
      };
      return categories[category] || "category-default";
    };

    // Build card HTML
    card.innerHTML = `
            <div class="opinion-cover">
                <img src="${opinion.coverImage}" 
                     alt="${opinion.title}" 
                     onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
                <div class="opinion-views">
                    <i data-feather="eye"></i> ${opinion.views}
                </div>
            </div>
            <div class="opinion-content">
                <span class="opinion-category ${getCategoryClass(opinion.category)}">${opinion.category}</span>
                <h3 class="opinion-title">${truncate(opinion.title, 60)}</h3>
                <p class="opinion-description">${truncate(opinion.description, 150)}</p>
                <div class="opinion-meta">
                    <span class="opinion-author">
                        <i data-feather="user"></i> ${opinion.author_name}
                    </span>
                    <span class="opinion-date">
                        <i data-feather="calendar"></i> ${formatDate(opinion.uploadDate)}
                    </span>
                </div>
                ${
                  opinion.tags && opinion.tags.length > 0
                    ? `
                    <div class="journal-tags">
                        ${opinion.tags
                          .slice(0, 3)
                          .map((tag) => `<span class="tag">${tag}</span>`)
                          .join("")}
                        ${opinion.tags.length > 3 ? `<span class="tag-more">+${opinion.tags.length - 3}</span>` : ""}
                    </div>
                `
                    : ""
                }
                <div class="opinion-actions">
                    <button class="btn-view">
                        <i data-feather="eye"></i> Lihat Detail
                    </button>
                    <button class="btn-delete">
                        <i data-feather="trash-2"></i> Hapus
                    </button>
                </div>
            </div>
        `;

    // Add event listeners
    card.querySelector(".btn-view").addEventListener("click", () => {
      this.viewOpinion(opinion.id);
    });

    card.querySelector(".btn-delete").addEventListener("click", () => {
      this.deleteOpinion(opinion.id, opinion.title);
    });

    return card;
  }

  /**
   * View opinion detail
   */
  viewOpinion(id) {
    console.log("Viewing opinion:", id);
    this.updateOpinionViews(id);
    window.location.href = `explore_jurnal_user.html?id=${id}&type=opini`;
  }

  /**
   * Delete opinion from database
   */
  async deleteOpinion(id, title) {
    if (!confirm(`Yakin ingin menghapus opini "${title}"?`)) {
      return;
    }

    try {
      console.log(`Deleting opinion ID: ${id}`);

      // Show loading indicator
      const card = document.querySelector(`[data-opinion-id="${id}"]`);
      if (card) {
        card.style.opacity = "0.5";
        card.style.pointerEvents = "none";
      }

      // Use API function
      const result = await deleteOpinionAPI(id);

      if (result.ok) {
        console.log("Opinion deleted successfully");
        alert("Opini berhasil dihapus!");

        // Remove from local arrays
        this.opinions = this.opinions.filter(
          (o) => String(o.id) !== String(id),
        );
        this.filteredOpinions = this.filteredOpinions.filter(
          (o) => String(o.id) !== String(id),
        );

        // Re-render UI
        this.render();
        this.renderPagination();

        // Trigger event
        window.dispatchEvent(
          new CustomEvent("opinions:changed", {
            detail: { action: "deleted", id: id },
          }),
        );
      } else {
        throw new Error(
          result.message || "Gagal menghapus opini dari database",
        );
      }
    } catch (error) {
      console.error("Delete error:", error);
      alert("Gagal menghapus opini: " + error.message);

      // Restore card UI
      const card = document.querySelector(`[data-opinion-id="${id}"]`);
      if (card) {
        card.style.opacity = "1";
        card.style.pointerEvents = "auto";
      }
    }
  }

  /**
   * Update opinion views count
   */
  async updateOpinionViews(id) {
    try {
      const result = await updateViews(id, "opinion");

      if (result.ok) {
        const opinion = this.opinions.find((o) => String(o.id) === String(id));
        if (opinion) opinion.views = (opinion.views || 0) + 1;
      }
    } catch (error) {
      console.warn("Failed to update views:", error);
    }
  }

  /**
   * Setup sort dropdown
   */
  setupSort() {
    const sortSelect = document.getElementById("sortSelect");
    if (sortSelect) {
      sortSelect.addEventListener("change", (e) => {
        this.currentSort = e.target.value;
        this.applyFiltersAndSort();
      });
    }
  }

  /**
   * Setup search input
   */
  setupSearch() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        const query = e.target.value.toLowerCase().trim();

        if (!query) {
          this.filteredOpinions = [...this.opinions];
        } else {
          this.filteredOpinions = this.opinions.filter(
            (o) =>
              o.title.toLowerCase().includes(query) ||
              o.description.toLowerCase().includes(query) ||
              o.author_name.toLowerCase().includes(query) ||
              o.category.toLowerCase().includes(query),
          );
        }

        this.currentPage = 1;
        this.render();
        this.renderPagination();
      });
    }
  }

  /**
   * Apply filters and sorting
   */
  applyFiltersAndSort() {
    this.filteredOpinions = [...this.opinions];

    // Apply sorting
    if (this.currentSort === "newest") {
      this.filteredOpinions.sort(
        (a, b) => new Date(b.uploadDate) - new Date(a.uploadDate),
      );
    } else if (this.currentSort === "oldest") {
      this.filteredOpinions.sort(
        (a, b) => new Date(a.uploadDate) - new Date(b.uploadDate),
      );
    } else if (this.currentSort === "title") {
      this.filteredOpinions.sort((a, b) => a.title.localeCompare(b.title));
    } else if (this.currentSort === "views") {
      this.filteredOpinions.sort((a, b) => (b.views || 0) - (a.views || 0));
    }

    this.currentPage = 1;
    this.render();
    this.renderPagination();
  }

  /**
   * Filter by category
   */
  filterByCategory(category) {
    if (category === "all") {
      this.filteredOpinions = [...this.opinions];
    } else {
      this.filteredOpinions = this.opinions.filter(
        (o) => o.category === category,
      );
    }

    this.currentPage = 1;
    this.render();
    this.renderPagination();
  }

  /**
   * Render pagination
   */
  renderPagination() {
    const totalPages = Math.ceil(
      this.filteredOpinions.length / this.opinionsPerPage,
    );
    const paginationContainer = document.getElementById("pagination");

    if (!paginationContainer || totalPages <= 1) {
      if (paginationContainer) paginationContainer.innerHTML = "";
      return;
    }

    paginationContainer.innerHTML = "";

    // Previous button
    const prevBtn = document.createElement("button");
    prevBtn.textContent = "Previous";
    prevBtn.className = "pagination-btn";
    prevBtn.disabled = this.currentPage === 1;
    prevBtn.addEventListener("click", () => {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.render();
        this.renderPagination();
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
    paginationContainer.appendChild(prevBtn);

    // Page number buttons
    const maxVisiblePages = 5;
    let startPage = Math.max(
      1,
      this.currentPage - Math.floor(maxVisiblePages / 2),
    );
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage < maxVisiblePages - 1) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    // First page button if needed
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

    // Page number buttons
    for (let i = startPage; i <= endPage; i++) {
      const pageBtn = this.createPageButton(i);
      paginationContainer.appendChild(pageBtn);
    }

    // Last page button if needed
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

    // Next button
    const nextBtn = document.createElement("button");
    nextBtn.textContent = "Next";
    nextBtn.className = "pagination-btn";
    nextBtn.disabled = this.currentPage === totalPages;
    nextBtn.addEventListener("click", () => {
      if (this.currentPage < totalPages) {
        this.currentPage++;
        this.render();
        this.renderPagination();
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
    paginationContainer.appendChild(nextBtn);
  }

  /**
   * Create page button
   */
  createPageButton(pageNum) {
    const pageBtn = document.createElement("button");
    pageBtn.textContent = pageNum;
    pageBtn.className =
      pageNum === this.currentPage ? "pagination-btn active" : "pagination-btn";
    pageBtn.addEventListener("click", () => {
      this.currentPage = pageNum;
      this.render();
      this.renderPagination();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    return pageBtn;
  }

  /**
   * Utility methods
   */
  getOpinionById(id) {
    return this.opinions.find((o) => String(o.id) === String(id));
  }

  getTotalCount() {
    return this.opinions.length;
  }

  getTotalViews() {
    return this.opinions.reduce(
      (total, opinion) => total + (opinion.views || 0),
      0,
    );
  }
}

// Auto-initialize
let opinionsManager;
document.addEventListener("DOMContentLoaded", () => {
  opinionsManager = new OpinionManager();
  window.opinionsManager = opinionsManager;
  console.log("OpinionManager initialized");
});

console.log("opinion-manager.js loaded");
