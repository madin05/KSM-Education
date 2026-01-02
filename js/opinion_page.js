// opinions_page.js - Handle opinions page rendering (Database Mode)
class OpinionsPageManager {
  constructor() {
    this.container = document.getElementById("opinionsContainer");
    this.paginationContainer =
      document.getElementById("paginationContainer") ||
      document.getElementById("pagination");
    this.searchInput = document.getElementById("searchInput");
    this.sortSelect =
      document.getElementById("sortOpinions") ||
      document.getElementById("sortSelect");
    this.filterSelect = document.getElementById("filterSelect");

    this.opinions = [];
    this.filteredOpinions = [];
    this.currentPage = 1;
    this.itemsPerPage = 9;
    this.totalPages = 0;

    if (!this.container) {
      console.warn("⚠️ Opinions container not found!");
      return;
    }

    console.log("OpinionsPageManager initializing (Database Mode)...");
    this.init();
  }

  async init() {
    await this.loadOpinionsFromDatabase();
    this.setupEventListeners();
    this.render();

    // Listen for opinions changes
    window.addEventListener("opinions:changed", () => {
      console.log("Opinions changed, reloading...");
      this.loadOpinionsFromDatabase();
    });
  }

  async loadOpinionsFromDatabase() {
    try {
      console.log("Loading opinions from database...");

      const response = await fetch(
        "/ksmaja/api/list_opinions.php?limit=100&offset=0"
      );
      const result = await response.json();

      if (result.ok) {
        this.opinions = result.results || [];
        this.filteredOpinions = [...this.opinions];
        console.log(`Loaded ${this.opinions.length} opinions from database`);
        this.applyFilters();
      } else {
        console.error("Failed to load opinions:", result.message);
        this.opinions = [];
        this.filteredOpinions = [];
      }
    } catch (error) {
      console.error("Error loading opinions:", error);
      this.opinions = [];
      this.filteredOpinions = [];
    }
  }

  setupEventListeners() {
    if (this.searchInput) {
      this.searchInput.addEventListener("input", () => {
        this.applyFilters();
      });
    }

    if (this.sortSelect) {
      this.sortSelect.addEventListener("change", () => {
        this.applyFilters();
      });
    }

    if (this.filterSelect) {
      this.filterSelect.addEventListener("change", () => {
        this.applyFilters();
      });
    }
  }

  applyFilters() {
    let filtered = [...this.opinions];

    // Search filter
    if (this.searchInput) {
      const searchTerm = this.searchInput.value.toLowerCase();
      if (searchTerm) {
        filtered = filtered.filter((opinion) => {
          const title = (opinion.title || "").toLowerCase();
          const description = (opinion.description || "").toLowerCase();
          const author = (opinion.author_name || "").toLowerCase();
          return (
            title.includes(searchTerm) ||
            description.includes(searchTerm) ||
            author.includes(searchTerm)
          );
        });
      }
    }

    // Category filter
    if (this.filterSelect) {
      const category = this.filterSelect.value;
      if (category && category !== "all") {
        filtered = filtered.filter((opinion) => opinion.category === category);
      }
    }

    // Sort
    if (this.sortSelect) {
      const sortBy = this.sortSelect.value;
      filtered.sort((a, b) => {
        switch (sortBy) {
          case "newest":
            return new Date(b.created_at) - new Date(a.created_at);
          case "oldest":
            return new Date(a.created_at) - new Date(b.created_at);
          case "title":
            return (a.title || "").localeCompare(b.title || "");
          case "views":
            return (b.views || 0) - (a.views || 0);
          default:
            return 0;
        }
      });
    }

    this.filteredOpinions = filtered;
    this.currentPage = 1;
    this.render();
  }

  render() {
    if (!this.container) return;

    // Calculate pagination
    this.totalPages = Math.ceil(
      this.filteredOpinions.length / this.itemsPerPage
    );
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    const endIndex = startIndex + this.itemsPerPage;
    const currentOpinions = this.filteredOpinions.slice(startIndex, endIndex);

    // Render opinions
    if (currentOpinions.length === 0) {
      this.container.innerHTML = `
        <div class="empty-state">
          <p>Tidak ada artikel opini yang ditemukan</p>
        </div>
      `;
    } else {
      this.container.innerHTML = currentOpinions
        .map((opinion) => this.renderOpinionCard(opinion))
        .join("");
    }

    // Render pagination
    this.renderPagination();

    // Replace feather icons
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  renderOpinionCard(opinion) {
    const isAdmin = sessionStorage.getItem("userType") === "admin";
    const coverUrl = opinion.cover_url || "./assets/default-cover.jpg";
    const fileUrl = opinion.file_url || "";
    const date = opinion.created_at
      ? new Date(opinion.created_at).toLocaleDateString("id-ID")
      : "";
    const views = opinion.views || 0;

    return `
      <div class="opinion-card" data-id="${opinion.id}">
        <div class="opinion-cover" style="background-image: url('${coverUrl}')">
          ${
            isAdmin
              ? `
            <div class="card-actions">
              <button class="action-btn edit-btn" onclick="editOpinion(${opinion.id})" title="Edit">
                <i data-feather="edit-2"></i>
              </button>
              <button class="action-btn delete-btn" onclick="deleteOpinion(${opinion.id})" title="Delete">
                <i data-feather="trash-2"></i>
              </button>
            </div>
          `
              : ""
          }
        </div>
        <div class="opinion-content">
          <h3 class="opinion-title">${this.escapeHtml(
            opinion.title || "Untitled"
          )}</h3>
          <p class="opinion-author">${this.escapeHtml(
            opinion.author_name || "Unknown"
          )}</p>
          <p class="opinion-description">${this.truncate(
            opinion.description || "",
            100
          )}</p>
          <div class="opinion-meta">
            <span class="meta-item">
              <i data-feather="calendar"></i>
              ${date}
            </span>
            <span class="meta-item">
              <i data-feather="eye"></i>
              ${views} views
            </span>
          </div>
          <div class="opinion-actions">
            ${
              fileUrl
                ? `
              <a href="${fileUrl}" target="_blank" class="btn-primary btn-sm">
                <i data-feather="file-text"></i>
                Baca PDF
              </a>
            `
                : ""
            }
          </div>
        </div>
      </div>
    `;
  }

  renderPagination() {
    if (!this.paginationContainer || this.totalPages <= 1) {
      if (this.paginationContainer) {
        this.paginationContainer.innerHTML = "";
      }
      return;
    }

    let html = "";

    // Previous button
    if (this.currentPage > 1) {
      html += `<button class="page-btn" onclick="opinionsPageManager.goToPage(${
        this.currentPage - 1
      })">
        <i data-feather="chevron-left"></i>
      </button>`;
    }

    // Page numbers
    for (let i = 1; i <= this.totalPages; i++) {
      if (
        i === 1 ||
        i === this.totalPages ||
        (i >= this.currentPage - 2 && i <= this.currentPage + 2)
      ) {
        html += `<button class="page-btn ${
          i === this.currentPage ? "active" : ""
        }" 
          onclick="opinionsPageManager.goToPage(${i})">${i}</button>`;
      } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
        html += `<span class="page-dots">...</span>`;
      }
    }

    // Next button
    if (this.currentPage < this.totalPages) {
      html += `<button class="page-btn" onclick="opinionsPageManager.goToPage(${
        this.currentPage + 1
      })">
        <i data-feather="chevron-right"></i>
      </button>`;
    }

    this.paginationContainer.innerHTML = html;

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  goToPage(page) {
    this.currentPage = page;
    this.render();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  truncate(text, length) {
    if (text.length <= length) return this.escapeHtml(text);
    return this.escapeHtml(text.substring(0, length)) + "...";
  }
}

// Global functions for card actions
async function deleteOpinion(id) {
  if (!confirm("Yakin mau hapus artikel opini ini?")) return;

  try {
    const response = await fetch(`/ksmaja/api/delete_opinion.php?id=${id}`, {
      method: "DELETE",
    });

    const result = await response.json();

    if (result.ok) {
      alert("Artikel opini berhasil dihapus!");
      window.opinionsPageManager.loadOpinionsFromDatabase();
    } else {
      alert("Gagal menghapus: " + result.message);
    }
  } catch (error) {
    console.error("Delete error:", error);
    alert("Gagal menghapus artikel opini");
  }
}

function editOpinion(id) {
  // Redirect to edit page or open edit modal
  alert("Edit opinion ID: " + id + "\n\nFitur edit belum diimplementasi");
}

console.log("opinions_page.js loaded (Database Mode)");
