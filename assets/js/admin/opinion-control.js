export function setupOpinionsPageControls() {
  // Search functionality
  const searchInput = document.getElementById("searchInput");
  if (searchInput && window.journalManager) {
    searchInput.addEventListener("input", (e) => {
      window.journalManager.searchJournals(e.target.value);
    });
  }

  // Sort dropdown
  const btnSort = document.getElementById("btnSort");
  const sortMenu = document.getElementById("sortMenu");

  if (btnSort && sortMenu) {
    btnSort.addEventListener("click", () => {
      sortMenu.classList.toggle("active");
    });

    const sortItems = sortMenu.querySelectorAll(".sort-item");
    sortItems.forEach((item) => {
      item.addEventListener("click", () => {
        const sortType = item.getAttribute("data-sort");
        sortItems.forEach((si) => si.classList.remove("active"));
        item.classList.add("active");
        sortMenu.classList.remove("active");

        if (window.journalManager) {
          window.journalManager.sortJournals(sortType);
        }
      });
    });

    document.addEventListener("click", (e) => {
      if (!btnSort.contains(e.target) && !sortMenu.contains(e.target)) {
        sortMenu.classList.remove("active");
      }
    });
  }

  // Update total count
  if (window.journalManager) {
    const totalEl = document.getElementById("totalJournals");
    if (totalEl) {
      setTimeout(() => {
        totalEl.textContent = window.journalManager.getTotalCount();
      }, 500);
    }
  }
}
