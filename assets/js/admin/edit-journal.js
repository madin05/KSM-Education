export class EditJournalManager {
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

    this.modal
      .querySelector(".modal-overlay")
      ?.addEventListener("click", () => {
        this.closeEditModal();
      });

    this.addAuthorBtn?.addEventListener("click", () => {
      this.addAuthorField();
    });

    this.addPengurusBtn?.addEventListener("click", () => {
      this.addPengurusField();
    });

    this.addTagBtn?.addEventListener("click", () => {
      this.addTag();
    });

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

    console.log(" EditJournalManager initialized");
  }

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
      <span class="tag-remove" onclick="this.parentElement.remove()">&times;</span>
    `;

    this.tagsContainer.appendChild(tagElement);
    this.tagInput.value = "";
  }

  addPengurusField() {
    const pengurusGroups = this.pengurusContainer.querySelectorAll(
      ".pengurus-input-group",
    );
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
    const pengurusGroups = this.pengurusContainer.querySelectorAll(
      ".pengurus-input-group",
    );
    pengurusGroups.forEach((group, index) => {
      const input = group.querySelector(".pengurus-input");
      if (input) {
        input.placeholder = `Nama Pengurus ${index + 1}`;
      }
    });
  }

  openEditModal(journalId) {
    console.log("Opening edit modal for journal ID:", journalId);
    this.fetchJournalData(journalId);
  }

  async fetchJournalData(journalId) {
    try {
      const response = await fetch(
        `/ksmaja/api/get_journal.php?id=${journalId}`,
      );
      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Failed to load journal");
      }

      const journal = result.journal;
      console.log("Journal data loaded:", journal);

      this.currentJournalId = journalId;

      document.getElementById("editJournalId").value = journalId;
      document.getElementById("editJudulJurnal").value = journal.title || "";
      document.getElementById("editEmail").value = journal.email || "";
      document.getElementById("editKontak").value = journal.contact || "";
      document.getElementById("editVolume").value = journal.volume || "";
      document.getElementById("editAbstrak").value = journal.abstract || "";

      this.populateTags(journal.tags);
      this.populatePengurus(journal.pengurus);
      this.populateAuthors(journal.authors);

      this.modal.classList.add("active");
      document.body.style.overflow = "hidden";

      if (typeof feather !== "undefined") {
        feather.replace();
      }
    } catch (error) {
      console.error("Error loading journal:", error);
      alert("Gagal memuat data jurnal: " + error.message);
    }
  }

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
      this.addPengurusField();
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
    const authorGroups = this.authorsContainer.querySelectorAll(
      ".author-input-group",
    );
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
    const authorGroups = this.authorsContainer.querySelectorAll(
      ".author-input-group",
    );
    if (authorGroups.length <= 1) {
      alert("Minimal harus ada 1 penulis!");
      return;
    }
    authorGroup.remove();
    this.updateAuthorButtons();
  }

  updateAuthorButtons() {
    const authorGroups = this.authorsContainer.querySelectorAll(
      ".author-input-group",
    );
    authorGroups.forEach((group, index) => {
      const removeBtn = group.querySelector(".btn-remove-author");
      if (removeBtn) {
        removeBtn.style.display =
          index === 0 && authorGroups.length === 1 ? "none" : "flex";
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
      const formData = new FormData();

      formData.append("id", this.currentJournalId);

      if (judul) formData.append("title", judul);
      if (abstrak) formData.append("abstract", abstrak);
      if (email) formData.append("email", email);
      if (contact) formData.append("contact", contact);
      if (volume) formData.append("volume", volume);

      if (authors.length > 0)
        formData.append("authors", JSON.stringify(authors));
      if (tags.length > 0) formData.append("tags", JSON.stringify(tags));
      if (pengurus.length > 0)
        formData.append("pengurus", JSON.stringify(pengurus));

      const fileInput = document.getElementById("editFileInput");
      if (fileInput && fileInput.files[0]) {
        formData.append("file", fileInput.files[0]);
        console.log("Uploading new file:", fileInput.files[0].name);
      }

      const coverInput = document.getElementById("editCoverInput");
      if (coverInput && coverInput.files[0]) {
        formData.append("cover", coverInput.files[0]);
        console.log("Uploading new cover:", coverInput.files[0].name);
      }

      this.showLoading("Menyimpan perubahan...");

      const response = await fetch("/ksmaja/api/update_journal.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      this.hideLoading();

      if (!result.ok) {
        throw new Error(result.message || "Failed to update journal");
      }

      alert(" Jurnal berhasil diupdate!");
      this.closeEditModal();

      if ("caches" in window) {
        caches.keys().then((names) => {
          names.forEach((name) => caches.delete(name));
        });
      }

      window.location.href =
        window.location.href.split("?")[0] + "?nocache=" + Date.now();
    } catch (error) {
      console.error("Edit journal error:", error);
      this.hideLoading();
      alert("❌ Gagal update jurnal: " + error.message);
    }
  }

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
    const pengurusInputs =
      this.pengurusContainer.querySelectorAll(".pengurus-input");
    const pengurus = [];
    pengurusInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) pengurus.push(value);
    });
    return pengurus;
  }

  getAuthors() {
    const authorInputs =
      this.authorsContainer.querySelectorAll(".author-input");
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

    if (this.tagsContainer) this.tagsContainer.innerHTML = "";
    if (this.pengurusContainer) this.pengurusContainer.innerHTML = "";
    if (this.authorsContainer) this.authorsContainer.innerHTML = "";
  }
}
