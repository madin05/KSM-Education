// Import API functions
import { uploadFileToServer, createJournal, createOpinion } from "../api.js";

// Import managers
import { AuthorsManager } from "./managers/authors-manager.js";
import { PengurusManager } from "./managers/pengurus-manager.js";
import { TagsManager } from "./managers/tags-manager.js";
import { FileUploadManager, CoverUploadManager } from './file-upload-manager.js';

// SINGLETON GUARD
if (window._dualUploadHandlerLoaded) {
  console.warn("dual_upload_handler.js already loaded!");
} else {
  window._dualUploadHandlerLoaded = true;

  class DualUploadHandler {
    constructor() {
      if (DualUploadHandler._instance) {
        console.warn("DualUploadHandler already exists");
        return DualUploadHandler._instance;
      }
      DualUploadHandler._instance = this;

      console.log("DualUploadHandler initialized");

      this.isSubmittingJurnal = false;
      this.isSubmittingOpini = false;

      setTimeout(() => {
        this.initJurnalForm();
        this.initOpiniForm();
      }, 100);
    }

    initJurnalForm() {
      const form = document.getElementById("uploadFormJurnal");
      if (!form) {
        console.error("uploadFormJurnal not found!");
        return;
      }

      console.log("Initializing Jurnal form...");

      try {
        // Init managers (FileUploadManager & CoverUploadManager diasumsikan ada di global/legacy)
        this.jurnalFileManager = new FileUploadManager("Jurnal");
        this.jurnalCoverManager = new CoverUploadManager("Jurnal");

        // Init modular managers
        this.jurnalAuthorsManager = new AuthorsManager("Jurnal");
        this.jurnalPengurusManager = new PengurusManager("Jurnal");
        this.jurnalTagsManager = new TagsManager("Jurnal");

        if (form.dataset.handlerBound === "true") {
          console.warn("Form sudah ter-bind, skip");
          return;
        }

        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          await this.handleJurnalSubmit();
        });

        form.dataset.handlerBound = "true";
        console.log("Jurnal form ready");
      } catch (error) {
        console.error("Error in initJurnalForm:", error);
      }
    }

    async handleJurnalSubmit() {
      if (this.isSubmittingJurnal) {
        console.warn("Submit sedang diproses...");
        return;
      }

      this.isSubmittingJurnal = true;
      this.disableSubmitButton("uploadFormJurnal");

      try {
        // Validasi login
        if (!window.loginManager || !window.loginManager.isAdmin()) {
          alert("Login sebagai admin terlebih dahulu!");
          if (window.loginManager) window.loginManager.openLoginModal();
          return;
        }

        // Validasi file
        if (!this.jurnalFileManager.getUploadedFile()) {
          alert("Upload file jurnal terlebih dahulu!");
          return;
        }

        // Validasi authors
        const authors = this.jurnalAuthorsManager.getAuthors();
        if (authors.length === 0) {
          alert("Minimal 1 penulis!");
          return;
        }

        // Validasi pengurus
        const pengurus = this.jurnalPengurusManager.getPengurus();
        if (pengurus.length === 0) {
          alert("Minimal 1 pengurus!");
          return;
        }

        // Get form data
        const judul = document.getElementById("judulJurnal").value.trim();
        const email = document.getElementById("emailJurnal").value.trim();
        const kontak = document.getElementById("kontakJurnal").value.trim();
        const abstrak = document.getElementById("abstrakJurnal").value.trim();
        const volume = document.getElementById("volumeJurnal").value.trim();

        if (!judul || !email || !kontak || !abstrak || !volume) {
          alert("Semua field harus diisi!");
          return;
        }

        // Validasi nomor HP
        const phoneRegex = /^(?:(?:\+|00)62|[0])8[1-9]\d{7,11}$/;
        if (!phoneRegex.test(kontak.replace(/\D/g, ""))) {
          alert(
            "Nomor kontak harus berupa nomor HP yang valid!\n\nFormat: 08XXXXXXXXX",
          );
          return;
        }

        // Konfirmasi
        const file = this.jurnalFileManager.getUploadedFile();
        const confirmMsg = `Yakin mau upload jurnal ini?\n\nJudul: ${judul}\nPenulis: ${authors.join(", ")}\nPengurus: ${pengurus.join(", ")}\nKontak: ${kontak}\n\nUkuran: ${this.formatFileSize(file.size)}`;

        if (!confirm(confirmMsg)) {
          console.log("Upload dibatalkan");
          return;
        }

        this.showLoading("Mengupload jurnal ke server...");

        // Upload file PDF
        const fileResult = await uploadFileToServer(file);
        if (!fileResult.ok) {
          throw new Error(fileResult.message || "Upload file gagal");
        }
        console.log("File uploaded:", fileResult.url);

        // Upload cover (optional)
        let coverUrl = null;
        const coverFile = this.jurnalCoverManager.getCoverFile();
        if (coverFile) {
          this.updateLoadingMessage("Mengupload cover image...");
          const coverResult = await uploadFileToServer(coverFile);
          if (coverResult.ok) {
            coverUrl = coverResult.url;
            console.log("Cover uploaded:", coverUrl);
          }
        }

        // Create journal via API
        this.updateLoadingMessage("Menyimpan metadata ke database...");

        const metadata = {
          title: judul,
          abstract: abstrak,
          authors: authors,
          tags: this.jurnalTagsManager.getTags(),
          fileUrl: fileResult.url,
          coverUrl: coverUrl,
          email: email,
          contact: kontak,
          pengurus: pengurus,
          volume: volume,
          client_temp_id: "upload_" + Date.now(),
          client_updated_at: this.toMySQLDateTime(new Date()),
        };

        const createResult = await createJournal(metadata);
        if (!createResult.ok) {
          throw new Error(createResult.message || "Gagal menyimpan metadata");
        }

        console.log("Journal created with ID:", createResult.id);

        this.hideLoading();
        alert("Jurnal berhasil diupload!");

        window.dispatchEvent(
          new CustomEvent("journals:changed", {
            detail: { id: createResult.id, action: "created" },
          }),
        );

        this.resetJurnalForm();

        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } catch (error) {
        console.error("Upload error:", error);
        this.hideLoading();
        alert("Gagal upload: " + error.message);
      } finally {
        this.isSubmittingJurnal = false;
        this.enableSubmitButton("uploadFormJurnal");
      }
    }

    initOpiniForm() {
      const form = document.getElementById("uploadFormOpini");
      if (!form) {
        console.error("uploadFormOpini not found!");
        return;
      }

      console.log("Initializing Opini form...");

      try {
        this.opiniFileManager = new FileUploadManager("Opini");
        this.opiniCoverManager = new CoverUploadManager("Opini");
        this.opiniAuthorsManager = new AuthorsManager("Opini");
        this.opiniTagsManager = new TagsManager("Opini");

        if (form.dataset.handlerBound === "true") {
          console.warn("Form sudah ter-bind, skip");
          return;
        }

        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          await this.handleOpiniSubmit();
        });

        form.dataset.handlerBound = "true";
        console.log("Opini form ready");
      } catch (error) {
        console.error("Error in initOpiniForm:", error);
      }
    }

    async handleOpiniSubmit() {
      if (this.isSubmittingOpini) {
        console.warn("Submit sedang diproses...");
        return;
      }

      this.isSubmittingOpini = true;
      this.disableSubmitButton("uploadFormOpini");

      try {
        // Validasi login
        if (!window.loginManager || !window.loginManager.isAdmin()) {
          alert("Login sebagai admin terlebih dahulu!");
          if (window.loginManager) window.loginManager.openLoginModal();
          return;
        }

        // Validasi file
        const file = this.opiniFileManager.getUploadedFile();
        if (!file) {
          alert("Upload file opini terlebih dahulu!");
          return;
        }

        // Validasi authors
        const authors = this.opiniAuthorsManager.getAuthors();
        if (authors.length === 0) {
          alert("Minimal 1 penulis!");
          return;
        }

        // Get form data
        const judul = document.getElementById("judulOpini").value.trim();
        const email = document.getElementById("emailOpini").value.trim();
        const kontak = document.getElementById("kontakOpini").value.trim();
        const abstrak = document.getElementById("abstrakOpini").value.trim();

        if (!judul || !email || !kontak || !abstrak) {
          alert("Semua field harus diisi!");
          return;
        }

        // Validasi nomor HP
        const phoneRegex = /^(?:(?:\+|00)62|[0])8[1-9]\d{7,11}$/;
        if (!phoneRegex.test(kontak.replace(/\D/g, ""))) {
          alert(
            "Nomor kontak harus berupa nomor HP yang valid!\n\nFormat: 08XXXXXXXXX",
          );
          return;
        }

        // Konfirmasi
        const confirmMsg = `Yakin mau upload opini ini?\n\nJudul: ${judul}\nPenulis: ${authors.join(", ")}\nKontak: ${kontak}\n\nUkuran: ${this.formatFileSize(file.size)}`;

        if (!confirm(confirmMsg)) {
          console.log("Upload dibatalkan");
          return;
        }

        this.showLoading("Mengupload opini ke server...");

        // Upload file
        const fileResult = await uploadFileToServer(file);
        if (!fileResult.ok) {
          throw new Error(fileResult.message || "Upload file gagal");
        }
        console.log("File uploaded:", fileResult.url);

        // Upload cover (optional)
        let coverUrl = null;
        const coverFile = this.opiniCoverManager.getCoverFile();
        if (coverFile) {
          this.updateLoadingMessage("Mengupload cover image...");
          const coverResult = await uploadFileToServer(coverFile);
          if (coverResult.ok) {
            coverUrl = coverResult.url;
            console.log("Cover uploaded:", coverUrl);
          }
        }

        // Create opinion via API
        this.updateLoadingMessage("Menyimpan metadata ke database...");

        const metadata = {
          title: judul,
          description: abstrak,
          category: "opini",
          author_name: authors.join(", "),
          email: email,
          contact: kontak,
          tags: JSON.stringify(this.opiniTagsManager.getTags()),
          fileUrl: fileResult.url,
          coverUrl: coverUrl,
          client_updated_at: this.toMySQLDateTime(new Date()),
        };

        const createResult = await createOpinion(metadata);
        if (!createResult.ok) {
          throw new Error(createResult.message || "Gagal menyimpan metadata");
        }

        console.log("Opinion created with ID:", createResult.id);

        this.hideLoading();
        alert("Artikel Opini berhasil diupload!");

        window.dispatchEvent(
          new CustomEvent("opinions:changed", {
            detail: { id: createResult.id, action: "created" },
          }),
        );

        this.resetOpiniForm();

        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } catch (error) {
        console.error("Upload error:", error);
        this.hideLoading();
        alert("Gagal upload: " + error.message);
      } finally {
        this.isSubmittingOpini = false;
        this.enableSubmitButton("uploadFormOpini");
      }
    }

    // Utility methods
    toMySQLDateTime(date) {
      const d = date instanceof Date ? date : new Date(date);
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      const hours = String(d.getHours()).padStart(2, "0");
      const minutes = String(d.getMinutes()).padStart(2, "0");
      const seconds = String(d.getSeconds()).padStart(2, "0");
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    formatFileSize(bytes) {
      if (bytes === 0) return "0 Bytes";
      const k = 1024;
      const sizes = ["Bytes", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
    }

    showLoading(message) {
      let overlay = document.getElementById("uploadLoadingOverlay");
      if (!overlay) {
        overlay = document.createElement("div");
        overlay.id = "uploadLoadingOverlay";
        overlay.innerHTML = `
                    <div class="loading-spinner"></div>
                    <p class="loading-message">${message}</p>
                `;
        overlay.style.cssText = `
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.8); display: flex; flex-direction: column;
                    justify-content: center; align-items: center; z-index: 9999; color: white;
                `;

        const style = document.createElement("style");
        style.textContent = `
                    .loading-spinner {
                        width: 60px; height: 60px;
                        border: 4px solid rgba(255,255,255,0.3);
                        border-top: 4px solid white;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
        document.head.appendChild(style);
        document.body.appendChild(overlay);
      } else {
        overlay.querySelector(".loading-message").textContent = message;
        overlay.style.display = "flex";
      }
    }

    updateLoadingMessage(message) {
      const overlay = document.getElementById("uploadLoadingOverlay");
      if (overlay) {
        overlay.querySelector(".loading-message").textContent = message;
      }
    }

    hideLoading() {
      const overlay = document.getElementById("uploadLoadingOverlay");
      if (overlay) {
        overlay.style.display = "none";
      }
    }

    resetJurnalForm() {
      document.getElementById("uploadFormJurnal").reset();
      this.jurnalFileManager.removeFile();
      this.jurnalCoverManager.removeCover();
      this.jurnalAuthorsManager.clearAuthors();
      this.jurnalPengurusManager.clearPengurus();
      this.jurnalTagsManager.clearTags();
    }

    resetOpiniForm() {
      document.getElementById("uploadFormOpini").reset();
      if (this.opiniFileManager) this.opiniFileManager.removeFile();
      if (this.opiniCoverManager) this.opiniCoverManager.removeCover();
      this.opiniAuthorsManager.clearAuthors();
      this.opiniTagsManager.clearTags();
    }

    disableSubmitButton(formId) {
      const submitBtn = document.querySelector(
        `#${formId} button[type="submit"]`,
      );
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.dataset.originalText = submitBtn.textContent;
        submitBtn.textContent = "Sedang memproses...";
      }
    }

    enableSubmitButton(formId) {
      const submitBtn = document.querySelector(
        `#${formId} button[type="submit"]`,
      );
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.originalText || "SUBMIT";
      }
    }
  }

  // Initialize
  document.addEventListener("DOMContentLoaded", () => {
    window.dualUploadHandler = new DualUploadHandler();
    console.log("DualUploadHandler ready");
  });
}

console.log("dual_upload_handler.js loaded");
