export class SearchManager {
  constructor(searchInputSelector = ".search-box input") {
    this.searchInput = document.querySelector(searchInputSelector);
    if (this.searchInput) this.setupSearch();
  }

  setupSearch() {
    this.searchInput.addEventListener("input", (e) => {
      this.filterJournals(e.target.value);
    });
  }

  filterJournals(searchTerm) {
    const term = searchTerm.toLowerCase();
    const journalItems = document.querySelectorAll(".journal-item");

    journalItems.forEach((item) => {
      const title =
        item.querySelector(".journal-title")?.textContent.toLowerCase() || "";
      const description =
        item.querySelector(".journal-description")?.textContent.toLowerCase() ||
        "";
      const tags = item.dataset.tags?.toLowerCase() || "";

      if (
        title.includes(term) ||
        description.includes(term) ||
        tags.includes(term)
      ) {
        item.style.display = "flex";
      } else {
        item.style.display = "none";
      }
    });
  }
}

export function setupHashSearch() {
  if (location.hash === "#search") {
    const search = document.querySelector(".search-box input");
    if (search) {
      setTimeout(() => {
        search.focus();
        search.scrollIntoView({ behavior: "smooth", block: "center" });
      }, 100);
    }
  }
}
