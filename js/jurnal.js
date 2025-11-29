// ===== JOURNAL MANAGEMENT - DATABASE VERSION =====
//  All features preserved, fully integrated with MySQL database

class JournalManager {
  constructor() {
    this.journalContainer = document.getElementById("journalContainer");
    this.journals = [];
    this.init();
  }

  async init() {
    console.log("🚀 JournalManager initializing (Database Mode)...");
    await this.loadJournals();
    this.renderJournals();

    //  FEATURE: Listen to journal changes from other tabs/components
    window.addEventListener("journals:changed", async () => {
      console.log("📡 Journals changed event received, reloading...");
      await this.loadJournals();
      this.renderJournals();
    });
  }

  // ===== LOAD JOURNALS FROM DATABASE =====
  async loadJournals() {
    try {
      console.log("Loading journals from database...");

      const response = await fetch("/ksmaja/api/list_journals.php?limit=100&offset=0");
      const data = await response.json();

      if (data.ok && data.results) {
        //  Transform database format to app format
        this.journals = data.results.map((j) => {
          // Parse JSON fields safely
          const parseJsonField = (field) => {
            if (!field) return [];
            if (Array.isArray(field)) return field;
            try {
              return JSON.parse(field);
            } catch (e) {
              console.warn("Failed to parse JSON field:", field);
              return [];
            }
          };

          return {
            id: String(j.id), // Ensure string ID for consistency
            title: j.title || "Untitled",
            abstract: j.abstract || "",
            authors: parseJsonField(j.authors),
            tags: parseJsonField(j.tags),
            pengurus: parseJsonField(j.pengurus),
            volume: j.volume,
            date: j.created_at,
            uploadDate: j.created_at,
            fileData: j.file_url,
            file: j.file_url,
            coverImage:
              j.cover_url ||
              "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
            email: j.email || "",
            contact: j.contact || "",
            phone: j.contact || "",
            views: parseInt(j.views) || 0,
          };
        });

        console.log(` Loaded ${this.journals.length} journals from database`);
      } else {
        console.warn("⚠️ No journals found in database response");
        this.journals = [];
      }
    } catch (error) {
      console.error("❌ Error loading journals from database:", error);

      //  FEATURE: Fallback to localStorage if database fails
      console.warn("⚠️ Falling back to localStorage...");
      const stored = localStorage.getItem("journals");
      if (stored) {
        try {
          this.journals = JSON.parse(stored);
          console.log(`📦 Loaded ${this.journals.length} journals from localStorage (fallback)`);
        } catch (e) {
          console.error("Failed to parse localStorage data:", e);
          this.journals = [];
        }
      } else {
        this.journals = [];
      }
    }
  }

  // ===== RENDER JOURNALS =====
  renderJournals() {
    if (!this.journalContainer) {
      console.warn("Journal container not found!");
      return;
    }

    this.journalContainer.innerHTML = "";

    //  FEATURE: Empty state UI
    if (this.journals.length === 0) {
      this.journalContainer.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">📚</div>
          <h3>Belum Ada Jurnal</h3>
          <p>Upload jurnal pertama kamu di form di bawah!</p>
        </div>
      `;
      return;
    }

    //  FEATURE: Render all journals
    this.journals.forEach((journal) => {
      const card = this.createJournalCard(journal);
      this.journalContainer.appendChild(card);
    });

    //  FEATURE: Refresh feather icons
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  // ===== CREATE JOURNAL CARD =====
  createJournalCard(journal) {
    console.log("Creating card for journal:", {
      id: journal.id,
      title: journal.title,
      email: journal.email,
      contact: journal.contact,
      authors: journal.authors,
    });

    const card = document.createElement("div");
    card.className = "journal-card";
    card.setAttribute("data-journal-id", journal.id);

    // FEATURE: Helper function for text truncation
    const truncateText = (text, maxLength) => {
      if (!text) return "";
      return text.length > maxLength ? text.substring(0, maxLength) + "..." : text;
    };

    // FEATURE: Format date nicely
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

    // FEATURE: Get first author or default
    const getFirstAuthor = (authors) => {
      if (Array.isArray(authors) && authors.length > 0) {
        return authors[0];
      }
      return "Unknown Author";
    };

    card.innerHTML = `
      <div class="journal-cover">
        <img src="${journal.coverImage}" 
             alt="${journal.title}" 
             onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
        <div class="journal-views">
          <i data-feather="eye"></i> ${journal.views}
        </div>
      </div>
      <div class="journal-content">
        <h3 class="journal-title">${truncateText(journal.title, 60)}</h3>
        <p class="journal-abstract">${truncateText(
          journal.abstract || "No abstract available",
          150
        )}</p>
        <div class="journal-meta">
          <span class="journal-author">
            <i data-feather="user"></i> ${getFirstAuthor(journal.authors)}
          </span>
          <span class="journal-date">
            <i data-feather="calendar"></i> ${formatDate(journal.uploadDate)}
          </span>
        </div>
        ${
          journal.tags && journal.tags.length > 0
            ? `
          <div class="journal-tags">
            ${journal.tags
              .slice(0, 3)
              .map((tag) => `<span class="tag">${tag}</span>`)
              .join("")}
            ${
              journal.tags.length > 3
                ? `<span class="tag-more">+${journal.tags.length - 3}</span>`
                : ""
            }
          </div>
        `
            : ""
        }
        <div class="journal-actions">
          <button class="btn-view" onclick="journalManager.viewJournal('${journal.id}')">
            <i data-feather="eye"></i> Lihat Detail
          </button>
          <button class="btn-edit" onclick="window.editJournalManager.openEditModal('${
            journal.id
          }')">
            <i data-feather="edit"></i> Edit
          </button>
          <button class="btn-delete" onclick="journalManager.deleteJournal('${
            journal.id
          }', '${journal.title.replace(/'/g, "\\'")}')">
            <i data-feather="trash-2"></i> Hapus
          </button>
        </div>
        
      </div>
    `;

    return card;
  }

  // ===== VIEW JOURNAL DETAIL =====
  viewJournal(id) {
    console.log("👁️ Viewing journal:", id);

    //  FEATURE: Update views count in database
    this.updateViews(id);

    //  FEATURE: Navigate to detail page
    window.location.href = `explore_jurnal_user.html?id=${id}&type=jurnal`;
  }

  // ===== DELETE JOURNAL FROM DATABASE =====
  // ===== DELETE JOURNAL FROM DATABASE =====
  async deleteJournal(id, title = "") {
    //  Validate ID
    if (!id) {
      alert("❌ ID journal tidak valid");
      return;
    }

    //  PREVENT ACCIDENTAL DELETE
    const confirmMsg = title
      ? `Yakin ingin menghapus jurnal "${title}"?\n\nData akan dihapus permanent dari database!`
      : `Yakin ingin menghapus jurnal ini?\n\nData akan dihapus permanent dari database!`;

    if (!confirm(confirmMsg)) {
      console.log("Delete cancelled by user");
      return;
    }

    try {
      console.log(`🗑️ Deleting journal ID: ${id}`);

      //  Show loading state
      const card = document.querySelector(`[data-journal-id="${id}"]`);
      if (card) {
        card.style.opacity = "0.5";
        card.style.pointerEvents = "none";
      }

      //  METHOD 1: DELETE request with ID in URL (RECOMMENDED)
      const response = await fetch(`/ksmaja/api/delete_journal.php?id=${encodeURIComponent(id)}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
      });

      const result = await response.json();
      console.log("Delete response:", result);

      if (result.ok) {
        console.log(" Journal deleted from database successfully");

        //  Success notification
        alert(" Jurnal berhasil dihapus!");

        //  Remove from local arrays (support both string and number IDs)
        this.journals = this.journals.filter((j) => {
          return String(j.id) !== String(id);
        });

        //  Re-render UI
        this.renderJournals();

        //  Trigger event for other components
        window.dispatchEvent(
          new CustomEvent("journals:changed", {
            detail: {
              action: "deleted",
              id: id,
            },
          })
        );

        //  Reload statistics
        if (window.statisticManager) {
          setTimeout(async () => {
            await window.statisticManager.fetchStatistics();
          }, 500);
        }

        console.log(" Journal deleted and UI updated");

        //  Optional: Reload page after 1 second
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        throw new Error(result.message || "Gagal menghapus jurnal dari database");
      }
    } catch (error) {
      console.error("❌ Delete error:", error);

      //  Error notification
      alert("❌ Gagal menghapus jurnal: " + error.message);

      //  Restore card UI on error
      const card = document.querySelector(`[data-journal-id="${id}"]`);
      if (card) {
        card.style.opacity = "1";
        card.style.pointerEvents = "auto";
      }
    }
  }

  // ===== UPDATE VIEWS COUNT =====
  async updateViews(id) {
    try {
      console.log("👁️ Updating views for journal:", id);

      //  Update views in database
      const response = await fetch(`/ksmaja/api/update_views.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: id,
          type: "journal",
        }),
      });

      const result = await response.json();

      if (result.ok) {
        console.log(" Views updated in database");

        //  FEATURE: Update local data
        const journal = this.journals.find((j) => j.id === id || j.id === String(id));
        if (journal) {
          journal.views = (journal.views || 0) + 1;
        }
      }
    } catch (error) {
      console.warn("⚠️ Failed to update views:", error);
      //  FEATURE: Silent fail - don't break user experience
    }
  }

  // ===== SEARCH JOURNALS =====
  searchJournals(query) {
    if (!query || query.trim() === "") {
      this.renderJournals();
      return;
    }

    const searchQuery = query.toLowerCase().trim();
    const filtered = this.journals.filter((journal) => {
      return (
        journal.title.toLowerCase().includes(searchQuery) ||
        journal.abstract.toLowerCase().includes(searchQuery) ||
        (Array.isArray(journal.authors) &&
          journal.authors.some((author) => author.toLowerCase().includes(searchQuery))) ||
        (Array.isArray(journal.tags) &&
          journal.tags.some((tag) => tag.toLowerCase().includes(searchQuery)))
      );
    });

    //  FEATURE: Render filtered results
    this.renderFilteredJournals(filtered, query);
  }

  // ===== RENDER FILTERED JOURNALS =====
  renderFilteredJournals(filtered, query) {
    if (!this.journalContainer) return;

    this.journalContainer.innerHTML = "";

    if (filtered.length === 0) {
      this.journalContainer.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">🔍</div>
          <h3>Tidak Ada Hasil</h3>
          <p>Tidak ditemukan jurnal dengan kata kunci "${query}"</p>
        </div>
      `;
      return;
    }

    filtered.forEach((journal) => {
      const card = this.createJournalCard(journal);
      this.journalContainer.appendChild(card);
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  // ===== FILTER BY TAG =====
  filterByTag(tag) {
    const filtered = this.journals.filter((journal) => {
      return Array.isArray(journal.tags) && journal.tags.includes(tag);
    });

    this.renderFilteredJournals(filtered, `tag: ${tag}`);
  }

  // ===== SORT JOURNALS =====
  sortJournals(sortBy) {
    let sorted = [...this.journals];

    switch (sortBy) {
      case "newest":
        sorted.sort((a, b) => new Date(b.uploadDate) - new Date(a.uploadDate));
        break;
      case "oldest":
        sorted.sort((a, b) => new Date(a.uploadDate) - new Date(b.uploadDate));
        break;
      case "title":
        sorted.sort((a, b) => a.title.localeCompare(b.title));
        break;
      case "views":
        sorted.sort((a, b) => (b.views || 0) - (a.views || 0));
        break;
      default:
        // No sorting
        break;
    }

    this.journals = sorted;
    this.renderJournals();
  }

  // ===== GET JOURNAL BY ID =====
  getJournalById(id) {
    return this.journals.find((j) => j.id === id || j.id === String(id));
  }

  // ===== GET TOTAL JOURNALS COUNT =====
  getTotalCount() {
    return this.journals.length;
  }

  // ===== GET TOTAL VIEWS =====
  getTotalViews() {
    return this.journals.reduce((total, journal) => total + (journal.views || 0), 0);
  }
}

// ===== INITIALIZE WHEN DOM IS READY =====
let journalManager;
document.addEventListener("DOMContentLoaded", () => {
  journalManager = new JournalManager();
  console.log(" JournalManager initialized (Full Database Integration)");

  //  FEATURE: Expose to window for console access
  window.journalManager = journalManager;
});

console.log("📚 jurnal.js loaded (Database Mode)");
