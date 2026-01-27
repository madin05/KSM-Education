import { showToast } from "../modules/toast.js";
import { PreviewViewer } from "../modules/preview.js";
import { SearchManager, setupHashSearch } from "../modules/search.js";
import { EditJournalManager } from "./edit-journal.js";
import { setupOpinionsPageControls } from "./opinions-controls.js";

// Login Status Sync (keep di sini karena spesifik admin dashboard)
function syncLoginStatusUI() {
  const isLoggedIn = sessionStorage.getItem("userLoggedIn") === "true";
  const isAdmin = sessionStorage.getItem("userType") === "admin";

  window.dispatchEvent(
    new CustomEvent("loginStatusChanged", {
      detail: { isLoggedIn, isAdmin },
    }),
  );

  if (window.journalManager?.renderJournals) {
    window.journalManager.renderJournals();
  }

  if (window.paginationManager?.render) {
    window.paginationManager.render();
  }
}

window.addEventListener("adminLoginStatusChanged", syncLoginStatusUI);

// Initialize All Systems
document.addEventListener("DOMContentLoaded", () => {
  console.log("Admin Initialization...");

  // Clear cache
  localStorage.removeItem("journals");
  localStorage.removeItem("opinions");

  setupHashSearch();

  if (window.feather) feather.replace();

  // Initialize Login Manager
  if (typeof LoginManager !== "undefined") {
    window.loginManager = new LoginManager();
  }

  // Upload Tabs (admin dashboard)
  if (
    document.querySelector(".upload-tab") &&
    typeof UploadTabsManager !== "undefined"
  ) {
    window.uploadTabsManager = new UploadTabsManager();
    console.log("✓ UploadTabsManager initialized");
  }

  // Page: journals.html
  if (document.getElementById("journalFullContainer")) {
    window.editJournalManager = new EditJournalManager();
    if (typeof PaginationManager !== "undefined") {
      window.paginationManager = new PaginationManager();
    }
    window.previewViewer = new PreviewViewer();
    console.log("Journals page systems initialized");
    syncLoginStatusUI();
    return;
  }

  // Page: opinions.html (ADMIN MODE)
  if (
    window.location.pathname.includes("opinions.html") &&
    document.getElementById("journalContainer")
  ) {
    console.log("Opinions page (ADMIN MODE)");
    window.editJournalManager = new EditJournalManager();
    setTimeout(() => setupOpinionsPageControls(), 500);
    return;
  }

  // Dashboard admin
  if (typeof StatisticsManager !== "undefined") {
    window.statsManager = new StatisticsManager();
  }

  window.searchManager = new SearchManager();
  window.editJournalManager = new EditJournalManager();
  window.previewViewer = new PreviewViewer();

  if (window.loginManager) {
    window.loginManager.syncLoginStatus();
  }

  // Update statistics
  setTimeout(() => {
    if (window.statsManager?.updateArticleCount) {
      window.statsManager.updateArticleCount();
      if (window.statsManager.startCounterAnimation) {
        window.statsManager.startCounterAnimation();
      }
    }
  }, 100);

  syncLoginStatusUI();
  console.log("✓ Admin systems initialized");
});

console.log("init-admin.js loaded");
