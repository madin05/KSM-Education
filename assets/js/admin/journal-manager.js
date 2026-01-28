import {
  listJournals,
  deleteJournal as deleteJournalAPI, // ← RENAME
  updateViews,
} from "../api.js";

// Import transformers
import { transformJournal } from "../utils/data-transformer.js";

export class JournalManager {
  constructor() {
    this.journalContainer =
      document.getElementById("journalContainer") ||
      document.getElementById("articlesGrid");

    this.journals = [];

    // Skip init on user dashboard
    if (window.location.pathname.includes("dashboard_user.html")) {
      console.warn("User dashboard page - JournalManager DISABLED");
      return;
    }

    if (this.journalContainer) {
      this.init();
    } else {
      console.warn("Journal container not found on this page - skipping init");
    }
  }

  async init() {
    console.log("JournalManager initializing...");

    localStorage.removeItem("journals");

    await this.loadJournals();
    this.renderJournals();

    window.addEventListener("journals:changed", async () => {
      console.log("Journals changed event received, reloading...");
      await this.loadJournals();
      this.renderJournals();
    });
  }

  async loadJournals() {
    try {
      console.log("Loading journals from database...");

      const data = await listJournals(100, 0);

      if (data.ok && data.results) {
        this.journals = data.results.map((j) => transformJournal(j));
        console.log(`Loaded ${this.journals.length} journals from database`);
      } else {
        console.warn("No journals found or database empty");
        this.journals = [];
      }
    } catch (error) {
      console.error("Error loading journals from database:", error);
      this.journals = [];
    }
  }

  renderJournals() {
    if (!this.journalContainer) {
      console.warn("Journal container not found!");
      return;
    }

    this.journalContainer.innerHTML = "";

    if (this.journals.length === 0) {
      const emptyDiv = document.createElement("div");
      emptyDiv.className = "empty-state";
      emptyDiv.innerHTML = `
                <div class="empty-state-icon">📚</div>
                <h3>Belum Ada Jurnal</h3>
                <p>Upload jurnal pertama kamu di form di bawah!</p>
            `;
      this.journalContainer.appendChild(emptyDiv);
      this.updateLatestNav();
      return;
    }

    const isAdmin =
      sessionStorage.getItem("userType") === "admin" ||
      window.location.pathname.includes("dashboard_admin.html") ||
      window.location.pathname.includes("journals.html");

    this.journals.forEach((journal) => {
      const card = this.createJournalCardWrapper(journal, isAdmin);
      this.journalContainer.appendChild(card);
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }

    this.updateLatestNav();
  }

  createJournalCardWrapper(journal, isAdmin) {
    const card = document.createElement("div");
    card.className = "journal-card";
    card.setAttribute("data-journal-id", journal.id);

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
                <h3 class="journal-title">${truncate(journal.title, 60)}</h3>
                <p class="journal-abstract">${truncate(journal.abstract, 150)}</p>
                
                <div class="journal-meta">
                    <span><i data-feather="user"></i> ${getFirstAuthor(journal.authors)}</span>
                    <span><i data-feather="calendar"></i> ${formatDate(journal.uploadDate)}</span>
                </div>
                
                ${
                  journal.tags && journal.tags.length > 0
                    ? `
                    <div class="journal-tags">
                        ${journal.tags
                          .slice(0, 3)
                          .map((tag) => `<span class="tag">${tag}</span>`)
                          .join("")}
                        ${journal.tags.length > 3 ? `<span class="tag-more">+${journal.tags.length - 3}</span>` : ""}
                    </div>
                `
                    : ""
                }
            </div>
        `;

    if (!isAdmin) {
      card.style.cursor = "pointer";
      card.addEventListener("click", () => {
        this.viewJournal(journal.id);
      });
    }

    if (isAdmin) {
      const actionsDiv = document.createElement("div");
      actionsDiv.className = "journal-actions";
      actionsDiv.innerHTML = `
                <button class="btn-view" data-action="view">
                    <i data-feather="eye"></i> Detail
                </button>
                <button class="btn-edit" data-action="edit">
                    <i data-feather="edit"></i> Edit
                </button>
                <button class="btn-delete" data-action="delete">
                    <i data-feather="trash-2"></i> Hapus
                </button>
                <button class="btn-share" data-action="share">
                    <i data-feather="share-2"></i> Share
                </button>
            `;

      actionsDiv
        .querySelector('[data-action="view"]')
        .addEventListener("click", (e) => {
          e.stopPropagation();
          this.viewJournal(journal.id);
        });

      actionsDiv
        .querySelector('[data-action="edit"]')
        .addEventListener("click", (e) => {
          e.stopPropagation();
          if (window.editJournalManager) {
            window.editJournalManager.openEditModal(journal.id);
          }
        });

      actionsDiv
        .querySelector('[data-action="delete"]')
        .addEventListener("click", (e) => {
          e.stopPropagation();
          this.deleteJournal(journal.id, journal.title);
        });

      actionsDiv
        .querySelector('[data-action="share"]')
        .addEventListener("click", (e) => {
          e.stopPropagation();
          this.handleShare(
            journal,
            `explore_jurnal_admin.html?id=${journal.id}&type=jurnal`,
          );
        });

      const contentDiv = card.querySelector(".journal-content");
      if (contentDiv) {
        contentDiv.appendChild(actionsDiv);
      }
    }

    return card;
  }

  viewJournal(id) {
    console.log("Viewing journal:", id);
    this.updateJournalViews(id);
    window.location.href = `explore_jurnal_admin.html?id=${id}&type=jurnal`;
  }

  async deleteJournal(id, title = "") {
    if (!id) {
      alert("ID journal tidak valid");
      return;
    }

    const confirmMsg = title
      ? `Yakin ingin menghapus jurnal "${title}"?\n\nData akan dihapus permanent dari database!`
      : `Yakin ingin menghapus jurnal ini?\n\nData akan dihapus permanent dari database!`;

    if (!confirm(confirmMsg)) return;

    try {
      const card = document.querySelector(`[data-journal-id="${id}"]`);
      if (card) {
        card.style.opacity = "0.5";
        card.style.pointerEvents = "none";
      }

      const result = await deleteJournalAPI(id);

      if (result.ok) {
        alert("Jurnal berhasil dihapus!");

        this.journals = this.journals.filter(
          (j) => String(j.id) !== String(id),
        );
        this.renderJournals();

        window.dispatchEvent(
          new CustomEvent("journals:changed", {
            detail: { action: "deleted", id: id },
          }),
        );

        if (window.statisticManager) {
          setTimeout(async () => {
            await window.statisticManager.fetchStatistics();
          }, 500);
        }

        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        throw new Error(
          result.message || "Gagal menghapus jurnal dari database",
        );
      }
    } catch (error) {
      alert("Gagal menghapus jurnal: " + error.message);

      const card = document.querySelector(`[data-journal-id="${id}"]`);
      if (card) {
        card.style.opacity = "1";
        card.style.pointerEvents = "auto";
      }
    }
  }

  async updateJournalViews(id) {
    try {
      const result = await updateViews(id, "journal");

      if (result.ok) {
        const journal = this.journals.find(
          (j) => j.id === id || j.id === String(id),
        );
        if (journal) journal.views = (journal.views || 0) + 1;
      }
    } catch (error) {
      console.warn("Failed to update views:", error);
    }
  }

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
          journal.authors.some((author) =>
            author.toLowerCase().includes(searchQuery),
          )) ||
        (Array.isArray(journal.tags) &&
          journal.tags.some((tag) => tag.toLowerCase().includes(searchQuery)))
      );
    });

    this.renderFilteredJournals(filtered, query);
  }

  renderFilteredJournals(filtered, query) {
    if (!this.journalContainer) return;

    this.journalContainer.innerHTML = "";

    if (filtered.length === 0) {
      const emptyDiv = document.createElement("div");
      emptyDiv.className = "empty-state";
      emptyDiv.innerHTML = `
                <div class="empty-state-icon">🔍</div>
                <h3>Tidak Ada Hasil</h3>
                <p>Tidak ditemukan jurnal dengan kata kunci "${query}"</p>
            `;
      this.journalContainer.appendChild(emptyDiv);
      return;
    }

    const isAdmin = sessionStorage.getItem("userType") === "admin";

    filtered.forEach((journal) => {
      const card = this.createJournalCardWrapper(journal, isAdmin);
      this.journalContainer.appendChild(card);
    });

    if (typeof feather !== "undefined") feather.replace();
  }

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
    }

    this.journals = sorted;
    this.renderJournals();
  }

  handleShare(item, url) {
    const fullUrl = window.location.origin + "/" + url;
    const shareText = `Lihat jurnal: ${item.title}`;

    if (navigator.share) {
      navigator
        .share({ title: item.title, text: shareText, url: fullUrl })
        .then(() => console.log("Shared successfully"))
        .catch(() => this.fallbackShare(fullUrl));
    } else {
      this.fallbackShare(fullUrl);
    }
  }

  fallbackShare(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard
        .writeText(url)
        .then(() => alert("Link berhasil disalin ke clipboard!"))
        .catch(() => prompt("Copy link ini:", url));
    } else {
      prompt("Copy link ini:", url);
    }
  }

  updateLatestNav() {
    const journals = this.journals || [];

    const navUser = document.getElementById("latestArticlesNavUser");
    if (navUser) {
      navUser.innerHTML =
        journals.length > 6
          ? `<button class="btn-see-all" onclick="window.location.href='journals_user.html'">Lihat semua artikel</button>`
          : "";
    }

    const navAdmin = document.getElementById("latestArticlesNavAdmin");
    if (navAdmin) {
      navAdmin.innerHTML =
        journals.length > 6
          ? `<button class="btn-see-all" onclick="window.location.href='journals.html'">Lihat semua artikel</button>`
          : "";
    }
  }

  getJournalById(id) {
    return this.journals.find((j) => j.id === id || j.id === String(id));
  }

  getTotalCount() {
    return this.journals.length;
  }

  getTotalViews() {
    return this.journals.reduce(
      (total, journal) => total + (journal.views || 0),
      0,
    );
  }
}

// Auto-initialize
let journalManager;
document.addEventListener("DOMContentLoaded", () => {
  if (window.journalManager) {
    console.warn("JournalManager already initialized, skipping...");
    return;
  }

  journalManager = new JournalManager();
  window.journalManager = journalManager;
  console.log("JournalManager initialized");
});

console.log("journal-manager.js loaded");
