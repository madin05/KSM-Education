// Import config
import { CONFIG } from "../config.js";

// Module-scoped locks
let _fileDialogLock = false;
let _coverDialogLock = false;

// Tracking initialized elements
const _initializedDropzones = new Set();
const _initializedCovers = new Set();

/**
 * Base class untuk upload manager (DRY principle)
 */
class BaseUploadManager {
  constructor(suffix, elementIds, config) {
    this.suffix = suffix;
    this.config = config;

    // Destructure element IDs
    Object.assign(this, elementIds);

    // Get DOM elements
    this.dropZone = document.getElementById(this.dropZoneId);
    this.fileInput = document.getElementById(this.fileInputId);
    this.preview = document.getElementById(this.previewId);
    this.removeBtn = document.getElementById(this.removeBtnId);

    this.uploadedFile = null;
  }

  validateFile(file) {
    // Check file type
    const fileExtension = "." + file.name.split(".").pop().toLowerCase();
    const isValidType = this.config.allowedTypes.includes(file.type);
    const isValidExt = this.config.allowedExtensions?.includes(fileExtension);

    if (!isValidType && !isValidExt) {
      alert(this.config.errorMessages.invalidType);
      return false;
    }

    // Check file size
    if (file.size > this.config.maxFileSize) {
      const maxSizeMB = (this.config.maxFileSize / (1024 * 1024)).toFixed(0);
      alert(`Ukuran file maksimal ${maxSizeMB}MB!`);
      return false;
    }

    return true;
  }

  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  }

  setupDragAndDrop(handler) {
    this.dropZone.ondragover = (e) => {
      e.preventDefault();
      this.dropZone.classList.add("dragover");
    };

    this.dropZone.ondragleave = (e) => {
      e.preventDefault();
      this.dropZone.classList.remove("dragover");
    };

    this.dropZone.ondrop = (e) => {
      e.preventDefault();
      this.dropZone.classList.remove("dragover");
      handler(e.dataTransfer.files);
    };
  }

  getUploadedFile() {
    return this.uploadedFile;
  }
}

/**
 * Manager untuk upload file PDF (Jurnal/Opini)
 */
export class FileUploadManager extends BaseUploadManager {
  constructor(suffix = "") {
    const elementIds = {
      dropZoneId: `dropZone${suffix}`,
      fileInputId: `fileInput${suffix}`,
      previewId: `filePreview${suffix}`,
      removeBtnId: `removeFile${suffix}`,
    };

    const config = {
      maxFileSize: CONFIG.MAX_FILE_SIZE || 10 * 1024 * 1024, // 10MB
      allowedTypes: ["application/pdf"],
      allowedExtensions: [".pdf"],
      errorMessages: {
        invalidType: "File harus berformat PDF!",
      },
    };

    super(suffix, elementIds, config);

    if (this.dropZone && this.fileInput) {
      const elementId = this.dropZone.id;
      if (_initializedDropzones.has(elementId)) {
        console.warn(`FileUploadManager: ${elementId} already initialized!`);
        return;
      }
      _initializedDropzones.add(elementId);
      this.init();
    }
  }

  init() {
    const openDialog = () => {
      if (_fileDialogLock) {
        console.log("BLOCKED: Dialog already opening");
        return;
      }

      _fileDialogLock = true;
      this.dropZone.style.pointerEvents = "none";

      console.log("Opening file dialog at:", Date.now());
      this.fileInput.click();

      setTimeout(() => {
        this.dropZone.style.pointerEvents = "auto";
        _fileDialogLock = false;
      }, 1000);
    };

    this.dropZone.onclick = openDialog;

    this.fileInput.onchange = (e) => {
      this.handleFiles(e.target.files);
      _fileDialogLock = false;
      this.dropZone.style.pointerEvents = "auto";
    };

    this.setupDragAndDrop((files) => this.handleFiles(files));

    if (this.removeBtn) {
      this.removeBtn.onclick = (e) => {
        e.stopPropagation();
        this.removeFile();
      };
    }

    console.log("FileUploadManager initialized:", this.suffix);
  }

  handleFiles(files) {
    if (files.length === 0) return;
    const file = files[0];
    if (!this.validateFile(file)) return;
    this.uploadedFile = file;
    this.showFilePreview(file);
    this.dropZone.style.display = "none";
  }

  showFilePreview(file) {
    const fileName = document.getElementById(`fileName${this.suffix}`);
    const fileSize = document.getElementById(`fileSize${this.suffix}`);

    if (fileName) fileName.textContent = file.name;
    if (fileSize) fileSize.textContent = this.formatFileSize(file.size);
    if (this.preview) this.preview.style.display = "block";
  }

  removeFile() {
    this.uploadedFile = null;
    this.fileInput.value = "";
    if (this.preview) this.preview.style.display = "none";
    this.dropZone.style.display = "block";
  }

  getFileDataURL() {
    return new Promise((resolve, reject) => {
      if (!this.uploadedFile) {
        resolve(null);
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => resolve(e.target.result);
      reader.onerror = (e) => reject(e);
      reader.readAsDataURL(this.uploadedFile);
    });
  }
}

/**
 * Manager untuk upload cover image
 */
export class CoverUploadManager extends BaseUploadManager {
  constructor(suffix = "") {
    const elementIds = {
      dropZoneId: `coverDropZone${suffix}`,
      fileInputId: `coverInput${suffix}`,
      previewId: `coverPreview${suffix}`,
      removeBtnId: `removeCover${suffix}`,
    };

    const config = {
      maxFileSize: CONFIG.MAX_COVER_SIZE || 2 * 1024 * 1024, // 2MB
      allowedTypes: CONFIG.ALLOWED_IMAGE_TYPES || [
        "image/jpeg",
        "image/png",
        "image/gif",
      ],
      errorMessages: {
        invalidType: "File harus berformat JPG, PNG, atau GIF!",
      },
    };

    super(suffix, elementIds, config);

    this.coverImage = document.getElementById(`coverImage${suffix}`);

    if (this.dropZone && this.fileInput) {
      const elementId = this.dropZone.id;
      if (_initializedCovers.has(elementId)) {
        console.warn(`CoverUploadManager: ${elementId} already initialized!`);
        return;
      }
      _initializedCovers.add(elementId);
      this.init();
    }
  }

  init() {
    const openDialog = () => {
      if (_coverDialogLock) {
        console.log("BLOCKED: Cover dialog already opening");
        return;
      }

      _coverDialogLock = true;
      this.dropZone.style.pointerEvents = "none";

      console.log("Opening cover dialog at:", Date.now());
      this.fileInput.click();

      setTimeout(() => {
        this.dropZone.style.pointerEvents = "auto";
        _coverDialogLock = false;
      }, 1000);
    };

    this.dropZone.onclick = openDialog;

    this.fileInput.onchange = (e) => {
      this.handleFiles(e.target.files);
      _coverDialogLock = false;
      this.dropZone.style.pointerEvents = "auto";
    };

    this.setupDragAndDrop((files) => this.handleFiles(files));

    if (this.removeBtn) {
      this.removeBtn.onclick = (e) => {
        e.stopPropagation();
        this.removeCover();
      };
    }

    console.log("CoverUploadManager initialized:", this.suffix);
  }

  handleFiles(files) {
    if (files.length === 0) return;
    const file = files[0];
    if (!this.validateFile(file)) return;
    this.uploadedFile = file;
    this.showCoverPreview(file);
  }

  showCoverPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      if (this.coverImage) this.coverImage.src = e.target.result;
      if (this.preview) this.preview.style.display = "block";
      this.dropZone.style.display = "none";
    };
    reader.readAsDataURL(file);
  }

  removeCover() {
    this.uploadedFile = null;
    this.fileInput.value = "";
    if (this.coverImage) this.coverImage.src = "";
    if (this.preview) this.preview.style.display = "none";
    this.dropZone.style.display = "block";
  }

  getCoverFile() {
    return this.uploadedFile;
  }

  getUploadedCover() {
    return this.uploadedFile;
  }

  getCoverDataURL() {
    if (this.coverImage && this.coverImage.src) {
      return this.coverImage.src;
    }
    return null;
  }
}

console.log("file-upload-manager.js loaded");
