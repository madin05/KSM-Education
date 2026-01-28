/**
 * Upload Tabs Manager
 * Manages tab switching between Jurnal and Opini upload forms
 *
 * Dependencies: None
 */

class UploadTabsManager {
  constructor() {
    this.tabs = null;
    this.jurnalTab = null;
    this.opiniTab = null;
    this.init();
  }

  init() {
    // Wait for DOM if not ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.setupTabs());
    } else {
      setTimeout(() => this.setupTabs(), 50);
    }
  }

  setupTabs() {
    this.tabs = document.querySelectorAll(".upload-tab");
    this.jurnalTab = document.getElementById("jurnalTab");
    this.opiniTab = document.getElementById("opiniTab");

    if (this.tabs.length === 0 || !this.jurnalTab || !this.opiniTab) {
      console.warn("Tabs not ready, retrying...");
      setTimeout(() => this.setupTabs(), 100);
      return;
    }

    console.log("Tabs found:", this.tabs.length);

    // Set initial state
    this.jurnalTab.style.display = "block";
    this.opiniTab.style.display = "none";

    // Setup click handlers
    this.tabs.forEach((tab) => {
      tab.addEventListener("click", (e) => this.handleTabClick(e, tab));
    });

    console.log("Tabs initialized");
  }

  handleTabClick(e, tab) {
    e.preventDefault();
    e.stopPropagation();

    const targetTab = tab.getAttribute("data-tab");
    console.log("Switching to:", targetTab);

    // Remove all active
    this.tabs.forEach((t) => t.classList.remove("active"));

    // Add active to clicked tab
    tab.classList.add("active");

    // Show target content
    this.switchTab(targetTab);

    // Refresh icons
    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  switchTab(tabName) {
    // Hide all
    this.jurnalTab.style.display = "none";
    this.opiniTab.style.display = "none";

    // Show target
    if (tabName === "jurnal") {
      this.jurnalTab.style.display = "block";
      console.log("Showing Jurnal");
    } else if (tabName === "opini") {
      this.opiniTab.style.display = "block";
      console.log("Showing Opini");
    }
  }

  getCurrentTab() {
    const activeTab = document.querySelector(".upload-tab.active");
    return activeTab ? activeTab.getAttribute("data-tab") : "jurnal";
  }
}

// Auto-initialize
let uploadTabsManager;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    uploadTabsManager = new UploadTabsManager();
    window.uploadTabsManager = uploadTabsManager;
  });
} else {
  uploadTabsManager = new UploadTabsManager();
  window.uploadTabsManager = uploadTabsManager;
}

// Export
if (typeof module !== "undefined" && module.exports) {
  module.exports = UploadTabsManager;
}

console.log("UploadTabsManager loaded");