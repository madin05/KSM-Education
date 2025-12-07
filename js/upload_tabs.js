// ===== UPLOAD TABS MANAGER =====
class UploadTabsManager {
  constructor() {
    this.tabs = document.querySelectorAll(".upload-tab");
    this.containers = document.querySelectorAll(".upload-form-container");
    this.init();
  }

  init() {
    if (this.tabs.length === 0) return;

    this.tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        const targetTab = tab.dataset.tab;
        this.switchTab(targetTab);
      });
    });

    console.log("Upload Tabs initialized");
  }

  switchTab(targetTab) {
    // Remove active class from all tabs
    this.tabs.forEach((tab) => tab.classList.remove("active"));

    // Add active class to clicked tab
    const activeTab = document.querySelector(
      `.upload-tab[data-tab="${targetTab}"]`
    );
    if (activeTab) {
      activeTab.classList.add("active");
    }

    // Hide all containers
    this.containers.forEach((container) => {
      container.classList.remove("active");
    });

    // Show target container
    const targetContainer = document.getElementById(`form-${targetTab}`);
    if (targetContainer) {
      targetContainer.classList.add("active");
    }

    console.log(`Switched to ${targetTab} tab`);
  }
}

console.log("upload_tabs.js loaded");
