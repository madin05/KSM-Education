// ===== Toast Helper (Global) =====
function showToast(msg, type = "ok") {
  const t = document.getElementById("toast");
  if (!t) return;
  t.textContent = msg;
  t.className = "toast" + (type === "error" ? " error" : "");
  t.style.display = "block";
  clearTimeout(window.__toastTimer__);
  window.__toastTimer__ = setTimeout(() => {
    t.style.display = "none";
  }, 2000);
}

// ===== Hash Search Handler =====
function setupHashSearch() {
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

// ===== PREVIEW VIEWER =====
class PreviewViewer {
  constructor() {
    this.modal = document.getElementById("previewModal");
    this.body = document.getElementById("previewBody");
    this.title = document.getElementById("previewTitle");
    this.info = document.getElementById("previewInfo");
    this.closeBtn = document.getElementById("closePreviewModal");
    this.currentId = null;

    if (!this.modal || !this.body) return;

    const overlay = this.modal.querySelector(".modal-overlay");
    overlay?.addEventListener("click", () => this.close());
    this.closeBtn?.addEventListener("click", () => this.close());
  }

  openById(id) {
    this.currentId = id;
    const journal = this.resolveJournal(id);
    if (!journal) {
      alert("Jurnal tidak ditemukan!");
      return;
    }
    this.openWithJournal(journal);
  }

  resolveJournal(id) {
    const idNum = Number(id);
    if (window.journalManager?.journals) {
      const j = window.journalManager.journals.find((x) => x.id === idNum);
      if (j) return j;
    }
    if (window.paginationManager?.journals) {
      const j = window.paginationManager.journals.find((x) => x.id === idNum);
      if (j) return j;
    }
    try {
      const list = JSON.parse(localStorage.getItem("journals") || "[]");
      return list.find((x) => x.id === idNum) || null;
    } catch {
      return null;
    }
  }

  openWithJournal(j) {
    this.title.textContent = j.title || "Untitled";
    const authorsText = Array.isArray(j.author) ? j.author.join(", ") : j.author || "Unknown";
    this.info.textContent = `${j.date || ""} • ${authorsText}`;
    this.body.innerHTML = "";

    const ext = (j.fileName || "").split(".").pop().toLowerCase();
    const canPreviewPDF = !!j.fileData && ext === "pdf";
    const canPreviewImage = !!j.coverImage && /^data:image\//.test(j.coverImage);

    if (canPreviewPDF) {
      const iframe = document.createElement("iframe");
      iframe.src = j.fileData;
      this.body.appendChild(iframe);
    } else if (canPreviewImage) {
      const img = document.createElement("img");
      img.src = j.coverImage;
      this.body.appendChild(img);
    } else {
      const box = document.createElement("div");
      box.className = "preview-fallback";
      box.innerHTML = `
        <div>Preview tidak tersedia untuk tipe file ini (${ext || "unknown"}).</div>
        <div class="hint">Gunakan menu Download di kartu/list untuk mengunduh file.</div>
      `;
      this.body.appendChild(box);
    }

    this.open();
  }

  open() {
    this.modal.classList.add("active");
    document.body.style.overflow = "hidden";
    try {
      feather.replace();
    } catch {}
  }

  close() {
    this.modal.classList.remove("active");
    document.body.style.overflow = "auto";
    this.currentId = null;
    this.body.innerHTML = "";
  }
}

// ===== SEARCH FUNCTIONALITY =====
class SearchManager {
  constructor() {
    this.searchInput = document.querySelector(".search-box input");
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
      const title = item.querySelector(".journal-title")?.textContent.toLowerCase() || "";
      const description =
        item.querySelector(".journal-description")?.textContent.toLowerCase() || "";
      const tags = item.dataset.tags?.toLowerCase() || "";

      if (title.includes(term) || description.includes(term) || tags.includes(term)) {
        item.style.display = "flex";
      } else {
        item.style.display = "none";
      }
    });
  }
}

// ===== MULTIPLE AUTHORS MANAGER (Support Multi-Instance) =====
class AuthorsManager {
  constructor(suffix = "") {
    this.suffix = suffix;
    this.authorsContainer = document.getElementById(`authorsContainer${suffix}`);
    this.addAuthorBtn = document.getElementById(`addAuthorBtn${suffix}`);
    this.authorCount = 1;

    if (this.authorsContainer && this.addAuthorBtn) this.init();
  }

  init() {
    this.addAuthorBtn.addEventListener("click", () => {
      this.addAuthorField();
    });
  }

  addAuthorField() {
    this.authorCount++;
    const authorGroup = document.createElement("div");
    authorGroup.className = "author-input-group";
    authorGroup.dataset.authorIndex = this.authorCount - 1;

    authorGroup.innerHTML = `
      <input type="text" 
             class="author-input" 
             placeholder="Nama Penulis ${this.authorCount}" 
             ${this.authorCount === 1 ? "required" : ""}>
      <button type="button" class="btn-remove-author">
        <i data-feather="x"></i>
      </button>
    `;

    this.authorsContainer.appendChild(authorGroup);

    const removeBtn = authorGroup.querySelector(".btn-remove-author");
    removeBtn.addEventListener("click", () => {
      this.removeAuthorField(authorGroup);
    });

    feather.replace();
    this.updatePlaceholders();
  }

  removeAuthorField(authorGroup) {
    const authorGroups = this.authorsContainer.querySelectorAll(".author-input-group");
    if (authorGroups.length <= 1) {
      alert("Minimal harus ada 1 penulis!");
      return;
    }
    authorGroup.remove();
    this.authorCount--;
    this.updatePlaceholders();
  }

  updatePlaceholders() {
    const authorInputs = this.authorsContainer.querySelectorAll(".author-input");
    authorInputs.forEach((input, index) => {
      input.placeholder = `Nama Penulis ${index + 1}`;
      if (index === 0) input.required = true;
    });

    const removeButtons = this.authorsContainer.querySelectorAll(".btn-remove-author");
    removeButtons.forEach((btn, index) => {
      btn.style.display = index === 0 && authorInputs.length === 1 ? "none" : "flex";
    });
  }

  getAuthors() {
    const authorInputs = this.authorsContainer.querySelectorAll(".author-input");
    const authors = [];
    authorInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) authors.push(value);
    });
    return authors;
  }

  clearAuthors() {
    const authorGroups = this.authorsContainer.querySelectorAll(".author-input-group");
    authorGroups.forEach((group, index) => {
      if (index > 0) group.remove();
    });
    const firstInput = this.authorsContainer.querySelector(".author-input");
    if (firstInput) firstInput.value = "";
    this.authorCount = 1;
    this.updatePlaceholders();
  }
}

// ===== EDIT JOURNAL MANAGER =====
// ===== EDIT JOURNAL MANAGER (UPDATED WITH TAGS & PENGURUS) =====
class EditJournalManager {
  constructor() {
    this.modal = document.getElementById("editModal");
    this.form = document.getElementById("editForm");
    this.closeBtn = document.getElementById("closeEditModal");
    this.cancelBtn = document.getElementById("cancelEdit");
    this.authorsContainer = document.getElementById("editAuthorsContainer");
    this.addAuthorBtn = document.getElementById("editAddAuthorBtn");
    this.pengurusContainer = document.getElementById("editPengurusContainer");
    this.addPengurusBtn = document.getElementById("editAddPengurusBtn");
    this.tagsContainer = document.getElementById("editTagsContainer");
    this.tagInput = document.getElementById("editTagInput");
    this.addTagBtn = document.getElementById("editAddTagBtn");
    this.currentJournalId = null;

    if (!this.modal || !this.form) {
      console.warn("Edit modal not found in DOM");
      return;
    }

    this.init();
  }

  init() {
    this.closeBtn?.addEventListener("click", () => this.closeEditModal());
    this.cancelBtn?.addEventListener("click", () => this.closeEditModal());

    this.modal.querySelector(".modal-overlay")?.addEventListener("click", () => {
      this.closeEditModal();
    });

    // Authors
    this.addAuthorBtn?.addEventListener("click", () => {
      this.addAuthorField();
    });

    // ✅ Pengurus
    this.addPengurusBtn?.addEventListener("click", () => {
      this.addPengurusField();
    });

    // ✅ Tags
    this.addTagBtn?.addEventListener("click", () => {
      this.addTag();
    });

    // ✅ Tag input - Enter key
    this.tagInput?.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        this.addTag();
      }
    });

    this.form.addEventListener("submit", (e) => {
      e.preventDefault();
      this.handleEditSubmit();
    });

    console.log("✅ EditJournalManager initialized (with Tags & Pengurus)");
  }

  // ===== ADD TAG =====
  addTag() {
    const tag = this.tagInput.value.trim();

    if (!tag) {
      alert("Masukkan tag terlebih dahulu");
      return;
    }

    const tagElement = document.createElement("span");
    tagElement.className = "tag-item";
    tagElement.innerHTML = `
      ${tag}
      <span class="tag-remove" onclick="this.remove()">&times;</span>
    `;

    this.tagsContainer.appendChild(tagElement);
    this.tagInput.value = "";
  }

  // ===== ADD PENGURUS FIELD =====
  addPengurusField() {
    const pengurusGroups = this.pengurusContainer.querySelectorAll(".pengurus-input-group");
    const nextIndex = pengurusGroups.length;

    const pengurusGroup = document.createElement("div");
    pengurusGroup.className = "pengurus-input-group";
    pengurusGroup.dataset.pengurusIndex = nextIndex;

    pengurusGroup.innerHTML = `
      <input type="text" 
             class="pengurus-input" 
             placeholder="Nama Pengurus ${nextIndex + 1}">
      <button type="button" class="btn-remove-pengurus">
        <i data-feather="x"></i>
      </button>
    `;

    this.pengurusContainer.appendChild(pengurusGroup);

    const removeBtn = pengurusGroup.querySelector(".btn-remove-pengurus");
    removeBtn.addEventListener("click", () => {
      pengurusGroup.remove();
      this.updatePengurusPlaceholders();
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }

    this.updatePengurusPlaceholders();
  }

  updatePengurusPlaceholders() {
    const pengurusGroups = this.pengurusContainer.querySelectorAll(".pengurus-input-group");
    pengurusGroups.forEach((group, index) => {
      const input = group.querySelector(".pengurus-input");
      if (input) {
        input.placeholder = `Nama Pengurus ${index + 1}`;
      }
    });
  }

  openEditModal(journalId) {
    console.log("Opening edit modal for journal ID:", journalId);

    // Ambil data journal dari database
    this.fetchJournalData(journalId);
  }

  async fetchJournalData(journalId) {
    try {
      const response = await fetch(`/ksmaja/api/get_journal.php?id=${journalId}`);
      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Failed to load journal");
      }

      const journal = result.journal;
      console.log("Journal data loaded:", journal);

      this.currentJournalId = journalId;

      // Isi field-field form
      document.getElementById("editJournalId").value = journalId;
      document.getElementById("editJudulJurnal").value = journal.title || "";
      document.getElementById("editEmail").value = journal.email || "";
      document.getElementById("editKontak").value = journal.contact || "";
      document.getElementById("editVolume").value = journal.volume || "";
      document.getElementById("editAbstrak").value = journal.abstract || "";

      // ✅ Populate Tags
      this.populateTags(journal.tags);

      // ✅ Populate Pengurus
      this.populatePengurus(journal.pengurus);

      // ✅ Populate Authors
      this.populateAuthors(journal.authors);

      // Tampilkan modal
      this.modal.classList.add("active");
      document.body.style.overflow = "hidden";

      if (typeof feather !== "undefined") {
        feather.replace();
      }
    } catch (error) {
      console.error("Error loading journal:", error);
      alert("❌ Gagal memuat data jurnal: " + error.message);
    }
  }

  // ===== POPULATE TAGS =====
  populateTags(tags) {
    this.tagsContainer.innerHTML = "";

    let tagsArray = [];
    if (Array.isArray(tags)) {
      tagsArray = tags;
    } else if (typeof tags === "string" && tags.trim()) {
      try {
        tagsArray = JSON.parse(tags);
      } catch (e) {
        tagsArray = [tags];
      }
    }

    tagsArray.forEach((tag) => {
      const tagElement = document.createElement("span");
      tagElement.className = "tag-item";
      tagElement.innerHTML = `
        ${tag}
        <span class="tag-remove" onclick="this.parentElement.remove()">&times;</span>
      `;
      this.tagsContainer.appendChild(tagElement);
    });
  }

  // ===== POPULATE PENGURUS =====
  populatePengurus(pengurus) {
    this.pengurusContainer.innerHTML = "";

    let pengurusArray = [];
    if (Array.isArray(pengurus)) {
      pengurusArray = pengurus;
    } else if (typeof pengurus === "string" && pengurus.trim()) {
      try {
        pengurusArray = JSON.parse(pengurus);
      } catch (e) {
        pengurusArray = [pengurus];
      }
    }

    if (pengurusArray.length === 0) {
      this.addPengurusField(); // Add one empty field
      return;
    }

    pengurusArray.forEach((name, index) => {
      const pengurusGroup = document.createElement("div");
      pengurusGroup.className = "pengurus-input-group";
      pengurusGroup.dataset.pengurusIndex = index;

      pengurusGroup.innerHTML = `
        <input type="text" 
               class="pengurus-input" 
               placeholder="Nama Pengurus ${index + 1}" 
               value="${name || ""}">
        <button type="button" class="btn-remove-pengurus">
          <i data-feather="x"></i>
        </button>
      `;

      this.pengurusContainer.appendChild(pengurusGroup);

      const removeBtn = pengurusGroup.querySelector(".btn-remove-pengurus");
      removeBtn.addEventListener("click", () => {
        pengurusGroup.remove();
        this.updatePengurusPlaceholders();
      });
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  populateAuthors(authors) {
    this.authorsContainer.innerHTML = "";

    let authorsArray = [];
    if (Array.isArray(authors)) {
      authorsArray = authors;
    } else if (typeof authors === "string" && authors.trim()) {
      try {
        authorsArray = JSON.parse(authors);
      } catch (e) {
        authorsArray = [authors];
      }
    }

    if (authorsArray.length === 0) {
      authorsArray = [""];
    }

    authorsArray.forEach((author, index) => {
      const authorGroup = document.createElement("div");
      authorGroup.className = "author-input-group";
      authorGroup.dataset.authorIndex = index;

      authorGroup.innerHTML = `
        <input type="text" 
               class="author-input" 
               placeholder="Nama Penulis ${index + 1}" 
               value="${author || ""}"
               ${index === 0 ? "required" : ""}>
        <button type="button" class="btn-remove-author" style="display: ${
          index === 0 && authorsArray.length === 1 ? "none" : "flex"
        }">
          <i data-feather="x"></i>
        </button>
      `;

      this.authorsContainer.appendChild(authorGroup);

      const removeBtn = authorGroup.querySelector(".btn-remove-author");
      removeBtn.addEventListener("click", () => {
        this.removeAuthorField(authorGroup);
      });
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  addAuthorField() {
    const authorGroups = this.authorsContainer.querySelectorAll(".author-input-group");
    const nextIndex = authorGroups.length;

    const authorGroup = document.createElement("div");
    authorGroup.className = "author-input-group";
    authorGroup.dataset.authorIndex = nextIndex;

    authorGroup.innerHTML = `
      <input type="text" 
             class="author-input" 
             placeholder="Nama Penulis ${nextIndex + 1}">
      <button type="button" class="btn-remove-author">
        <i data-feather="x"></i>
      </button>
    `;

    this.authorsContainer.appendChild(authorGroup);

    const removeBtn = authorGroup.querySelector(".btn-remove-author");
    removeBtn.addEventListener("click", () => {
      this.removeAuthorField(authorGroup);
    });

    if (typeof feather !== "undefined") {
      feather.replace();
    }

    this.updateAuthorButtons();
  }

  removeAuthorField(authorGroup) {
    const authorGroups = this.authorsContainer.querySelectorAll(".author-input-group");
    if (authorGroups.length <= 1) {
      alert("Minimal harus ada 1 penulis!");
      return;
    }
    authorGroup.remove();
    this.updateAuthorButtons();
  }

  updateAuthorButtons() {
    const authorGroups = this.authorsContainer.querySelectorAll(".author-input-group");
    authorGroups.forEach((group, index) => {
      const removeBtn = group.querySelector(".btn-remove-author");
      if (removeBtn) {
        removeBtn.style.display = index === 0 && authorGroups.length === 1 ? "none" : "flex";
      }

      const input = group.querySelector(".author-input");
      if (input) {
        input.placeholder = `Nama Penulis ${index + 1}`;
        input.required = index === 0;
      }
    });
  }

  async handleEditSubmit() {
    const authors = this.getAuthors();
    if (authors.length === 0) {
      alert("Minimal harus ada 1 penulis!");
      return;
    }

    const judul = document.getElementById("editJudulJurnal").value.trim();
    const abstrak = document.getElementById("editAbstrak").value.trim();
    const email = document.getElementById("editEmail").value.trim();
    const contact = document.getElementById("editKontak").value.trim();
    const volume = document.getElementById("editVolume").value.trim();

    if (!judul || !abstrak) {
      alert("Judul dan abstrak harus diisi!");
      return;
    }

    const tags = this.getTags();
    const pengurus = this.getPengurus();

    try {
      // ✅ GANTI DENGAN FORMDATA!
      const formData = new FormData();

      // Add journal ID
      formData.append("id", this.currentJournalId);

      // Add text fields
      if (judul) formData.append("title", judul);
      if (abstrak) formData.append("abstract", abstrak);
      if (email) formData.append("email", email);
      if (contact) formData.append("contact", contact);
      if (volume) formData.append("volume", volume);

      // Add JSON arrays
      if (authors.length > 0) formData.append("authors", JSON.stringify(authors));
      if (tags.length > 0) formData.append("tags", JSON.stringify(tags));
      if (pengurus.length > 0) formData.append("pengurus", JSON.stringify(pengurus));

      // ✅ HANDLE FILE UPLOAD
      const fileInput = document.getElementById("editFileInput");
      if (fileInput && fileInput.files[0]) {
        formData.append("file", fileInput.files[0]);
        console.log("📄 Uploading new file:", fileInput.files[0].name);
      }

      // ✅ HANDLE COVER UPLOAD
      const coverInput = document.getElementById("editCoverInput");
      if (coverInput && coverInput.files[0]) {
        formData.append("cover", coverInput.files[0]);
        console.log("🖼️ Uploading new cover:", coverInput.files[0].name);
      }

      // Show loading
      this.showLoading("Menyimpan perubahan...");

      // ✅ SEND REQUEST KE update_journal.php
      const response = await fetch("/ksmaja/api/update_journal.php", {
        method: "POST",
        body: formData, // ← FormData, bukan JSON!
        // ❌ JANGAN SET Content-Type header!
      });

      const result = await response.json();

      this.hideLoading();

      if (!result.ok) {
        throw new Error(result.message || "Failed to update journal");
      }

      alert("✅ Jurnal berhasil diupdate!");
      this.closeEditModal();

      // Clear cache & reload
      if ("caches" in window) {
        caches.keys().then((names) => {
          names.forEach((name) => caches.delete(name));
        });
      }

      window.location.href = window.location.href.split("?")[0] + "?nocache=" + Date.now();
    } catch (error) {
      console.error("Edit journal error:", error);
      this.hideLoading();
      alert("❌ Gagal update jurnal: " + error.message);
    }
  }

  // ✅ TAMBAH METHOD LOADING
  showLoading(message) {
    let overlay = document.getElementById("editLoadingOverlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "editLoadingOverlay";
      overlay.innerHTML = `
      <div class="loading-spinner"></div>
      <p class="loading-message">${message}</p>
    `;
      overlay.style.cssText = `
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.8); display: flex; flex-direction: column;
      justify-content: center; align-items: center; z-index: 10000; color: white;
    `;
      document.body.appendChild(overlay);
    } else {
      overlay.querySelector(".loading-message").textContent = message;
      overlay.style.display = "flex";
    }
  }

  hideLoading() {
    const overlay = document.getElementById("editLoadingOverlay");
    if (overlay) {
      overlay.style.display = "none";
    }
  }

  getTags() {
    const tagElements = this.tagsContainer.querySelectorAll(".tag-item");
    const tags = [];
    tagElements.forEach((tagEl) => {
      const text = tagEl.textContent.replace("×", "").trim();
      if (text) tags.push(text);
    });
    return tags;
  }

  getPengurus() {
    const pengurusInputs = this.pengurusContainer.querySelectorAll(".pengurus-input");
    const pengurus = [];
    pengurusInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) pengurus.push(value);
    });
    return pengurus;
  }

  getAuthors() {
    const authorInputs = this.authorsContainer.querySelectorAll(".author-input");
    const authors = [];
    authorInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) authors.push(value);
    });
    return authors;
  }

  closeEditModal() {
    this.modal.classList.remove("active");
    document.body.style.overflow = "auto";
    this.currentJournalId = null;
    this.form.reset();

    // Clear dynamic fields
    if (this.tagsContainer) this.tagsContainer.innerHTML = "";
    if (this.pengurusContainer) this.pengurusContainer.innerHTML = "";
    if (this.authorsContainer) this.authorsContainer.innerHTML = "";
  }
}

// function setupNavDropdown() {
//   const dropdowns = document.querySelectorAll(".nav-dropdown");
//   console.log("Found dropdowns:", dropdowns.length);

//   dropdowns.forEach((dd) => {
//     const btn = dd.querySelector(".nav-link.has-caret");
//     const menu = dd.querySelector(".dropdown-menu");
//     if (!btn || !menu) return;

//     btn.addEventListener("click", (e) => {
//       console.log("Nav dropdown clicked");
//       e.preventDefault();
//       e.stopPropagation();

//       // cek class sebelum
//       console.log("before:", dd.className);

//       if (dd.classList.contains("open")) {
//         dd.classList.remove("open");
//       } else {
//         dd.classList.add("open");
//       }

//       // cek class sesudah
//       console.log("after:", dd.className);
//     });
//   });

//   // sementara: matikan auto-close global dulu
//   // document.addEventListener("click", () => {
//   //   dropdowns.forEach((dd) => dd.classList.remove("open"));
//   // });
// }

// ===== LOGIN STATUS SYNC =====
function syncLoginStatusUI() {
  const isLoggedIn = sessionStorage.getItem("userLoggedIn") === "true";
  const isAdmin = sessionStorage.getItem("userType") === "admin";

  // Dispatch custom event untuk notify semua manager
  window.dispatchEvent(
    new CustomEvent("loginStatusChanged", {
      detail: { isLoggedIn, isAdmin },
    })
  );

  // Re-render journal jika ada
  if (window.journalManager && typeof window.journalManager.renderJournals === "function") {
    window.journalManager.renderJournals();
  }

  // Re-render pagination jika ada
  if (window.paginationManager && typeof window.paginationManager.render === "function") {
    window.paginationManager.render();
  }
}

// Listen untuk login status changes
window.addEventListener("adminLoginStatusChanged", syncLoginStatusUI);

// ===== INITIALIZE ALL SYSTEMS =====
document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM ready, init start");

  // ===== CLEAR OLD LOCALSTORAGE =====
  localStorage.removeItem("journals");
  localStorage.removeItem("opinions");

  // console.log("Before setupNavDropdown");
  // setupNavDropdown();
  // console.log("After setupNavDropdown");

  setupHashSearch();

  if (typeof LoginManager !== "undefined") {
    window.loginManager = new LoginManager();
  }

  if (document.getElementById("journalFullContainer")) {
    // UNTUK HALAMAN journals.html
    if (typeof EditJournalManager !== "undefined")
      window.editJournalManager = new EditJournalManager();
    if (typeof PaginationManager !== "undefined")
      window.paginationManager = new PaginationManager();
    window.previewViewer = new PreviewViewer();
    console.log("Journals page systems initialized");

    // SYNC LOGIN STATUS UNTUK RENDER TOMBOL
    syncLoginStatusUI();
    return;
  }

  if (document.getElementById("opinionsContainer")) {
    if (typeof OpinionsPageManager !== "undefined") {
      window.opinionsPageManager = new OpinionsPageManager();
      console.log("Opinions page systems initialized");
    }
    return;
  }

  // UNTUK HALAMAN dashboard_admin.html DAN index.html
  if (typeof StatisticsManager !== "undefined") window.statsManager = new StatisticsManager();

  // ===== COMMENT INI - JOURNALMANAGER UDAH AUTO-INIT DI JURNAL.JS =====
  // if (typeof JournalManager !== "undefined") window.journalManager = new JournalManager();

  if (typeof OpinionManager !== "undefined") window.opinionManager = new OpinionManager();
  if (typeof SearchManager !== "undefined") window.searchManager = new SearchManager();
  if (typeof UploadTabsManager !== "undefined") window.uploadTabsManager = new UploadTabsManager();
  if (typeof EditJournalManager !== "undefined")
    window.editJournalManager = new EditJournalManager();

  window.previewViewer = new PreviewViewer();

  if (window.loginManager) {
    window.loginManager.syncLoginStatus();
  }

  // Stats manager - safely initialize
  try {
    if (window.statsManager && typeof window.statsManager.updateArticleCount === "function") {
      setTimeout(() => {
        try {
          window.statsManager.updateArticleCount();
          if (typeof window.statsManager.startCounterAnimation === "function") {
            window.statsManager.startCounterAnimation();
          }
        } catch (e) {
          console.warn("Stats manager error (safe to ignore):", e);
        }
      }, 100);
    }
  } catch (e) {
    console.warn("Stats manager not available");
  }

  // SYNC LOGIN STATUS UNTUK RENDER TOMBOL DI ADMIN
  syncLoginStatusUI();

  console.log("All systems initialized successfully");
});
