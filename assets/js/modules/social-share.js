/**
 * Social Share Manager
 * Handles social media sharing functionality
 *
 * Dependencies: None
 */

class SocialShareManager {
  constructor() {
    this.currentShareUrl = "";
    this.currentShareTitle = "";
    this.init();
  }

  init() {
    // Setup modal close on overlay click
    document.addEventListener("DOMContentLoaded", () => {
      const shareModal = document.getElementById("shareModal");
      if (!shareModal) return;

      const overlay = shareModal.querySelector(".modal-overlay");
      if (overlay) {
        overlay.addEventListener("click", () => {
          this.closeModal();
        });
      }
    });

    console.log("SocialShareManager initialized");
  }

  openModal(id) {
    // Get article from available managers
    const article = this.getArticleById(id);

    if (!article) {
      alert("Data artikel tidak ditemukan.");
      return;
    }

    const baseUrl = window.location.origin;
    const path = window.location.pathname.substring(
      0,
      window.location.pathname.lastIndexOf("/"),
    );

    const articleType = article.type || "jurnal";
    const explorePage = "explore_jurnal_user.html";

    this.currentShareUrl = `${baseUrl}${path}/${explorePage}?id=${id}&type=${articleType}`;
    this.currentShareTitle = article.title || "Artikel";

    const input = document.getElementById("shareUrlInput");
    const modal = document.getElementById("shareModal");
    if (!input || !modal) return;

    input.value = this.currentShareUrl;
    modal.classList.add("active");
    document.body.style.overflow = "hidden";

    if (typeof feather !== "undefined") feather.replace();
  }

  closeModal() {
    const modal = document.getElementById("shareModal");
    if (!modal) return;
    modal.classList.remove("active");
    document.body.style.overflow = "auto";
  }

  copyLink() {
    if (!this.currentShareUrl) return;

    navigator.clipboard
      .writeText(this.currentShareUrl)
      .then(() => {
        if (typeof showToast === "function") {
          showToast("Link berhasil disalin!", "success");
        } else {
          alert("Link berhasil disalin!\n\n" + this.currentShareUrl);
        }
      })
      .catch(() => {
        alert("Gagal menyalin link, salin manual:\n\n" + this.currentShareUrl);
      });
  }

  shareToWhatsApp() {
    if (!this.currentShareUrl) return;
    const text = encodeURIComponent(
      `${this.currentShareTitle}\n\n${this.currentShareUrl}`,
    );
    window.open(`https://wa.me/?text=${text}`, "_blank");
  }

  shareToFacebook() {
    if (!this.currentShareUrl) return;
    window.open(
      `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(
        this.currentShareUrl,
      )}`,
      "_blank",
    );
  }

  shareToTwitter() {
    if (!this.currentShareUrl) return;
    const text = encodeURIComponent(this.currentShareTitle);
    window.open(
      `https://twitter.com/intent/tweet?url=${encodeURIComponent(
        this.currentShareUrl,
      )}&text=${text}`,
      "_blank",
    );
  }

  // Helper: Get article from available managers
  getArticleById(id) {
    // Try journalManager first
    if (window.journalManager?.getJournalById) {
      const article = window.journalManager.getJournalById(id);
      if (article) return article;
    }

    // Try paginationManager
    if (window.paginationManager?.getJournalById) {
      const article = window.paginationManager.getJournalById(id);
      if (article) return article;
    }

    // Try opinionManager
    if (window.opinionManager?.getOpinionById) {
      const article = window.opinionManager.getOpinionById(id);
      if (article) return article;
    }

    return null;
  }
}

// Auto-initialize and expose globally
let socialShareManager;
document.addEventListener("DOMContentLoaded", () => {
  socialShareManager = new SocialShareManager();
  window.socialShareManager = socialShareManager;
});

// \ BACKWARD COMPATIBLE: Expose functions globally
function openShareModal(id) {
  return window.socialShareManager?.openModal(id);
}

function closeShareModal() {
  return window.socialShareManager?.closeModal();
}

function copyShareLink() {
  return window.socialShareManager?.copyLink();
}

function shareToWhatsApp() {
  return window.socialShareManager?.shareToWhatsApp();
}

function shareToFacebook() {
  return window.socialShareManager?.shareToFacebook();
}

function shareToTwitter() {
  return window.socialShareManager?.shareToTwitter();
}

// Export for module system
if (typeof module !== "undefined" && module.exports) {
  module.exports = SocialShareManager;
}

console.log("Social Share Manager loaded");
