// Import API functions
import {
  createOpinion,
  deleteOpinion as deleteOpinionAPI,
  getOpinion,
  updateOpinion,
} from "../api.js";

/**
 * Opinion Upload Manager - Handle opinion creation and updates
 * Different from OpinionManager (which handles display/list)
 */
export class OpinionUploadManager {
  constructor() {
    console.log("OpinionUploadManager initialized");
  }

  /**
   * Add new opinion (upload to database)
   */
  async addOpinion(opinionData) {
    console.log("Adding opinion:", opinionData.judulJurnal);

    try {
      const dbResult = await this.uploadOpinionToDatabase(opinionData);
      console.log("Opinion saved to database with ID:", dbResult.id);
      return dbResult;
    } catch (error) {
      console.error("Database upload failed:", error);
      throw error;
    }
  }

  /**
   * Upload opinion to database via API
   */
  async uploadOpinionToDatabase(opinionData) {
    try {
      console.log("Uploading opinion to database...");

      const formData = new FormData();

      // Basic fields
      formData.append("title", opinionData.judulJurnal);
      formData.append("description", opinionData.abstrak);
      formData.append("category", "opini");
      formData.append("author_name", opinionData.namaPenulis.join(", "));
      formData.append("email", opinionData.email || "");
      formData.append("contact", opinionData.kontak || "");

      // Tags (JSON array)
      if (opinionData.tags && opinionData.tags.length > 0) {
        formData.append("tags", JSON.stringify(opinionData.tags));
      }

      // PDF file
      if (opinionData.fileData) {
        if (opinionData.fileData instanceof File) {
          formData.append("file_pdf", opinionData.fileData);
        } else if (opinionData.fileData instanceof Blob) {
          formData.append(
            "file_pdf",
            opinionData.fileData,
            opinionData.fileName || "opinion.pdf",
          );
        }
      }

      // Cover image
      if (opinionData.coverImage) {
        if (opinionData.coverImage instanceof File) {
          formData.append("cover_image", opinionData.coverImage);
        } else if (
          typeof opinionData.coverImage === "string" &&
          opinionData.coverImage.startsWith("data:")
        ) {
          const blob = this.base64ToBlob(opinionData.coverImage);
          formData.append("cover_image", blob, "cover.jpg");
        }
      }

      // Use API function instead of manual fetch
      const result = await createOpinion(formData);

      if (result.ok) {
        console.log("Opinion uploaded successfully, ID:", result.id);

        // Trigger event for other components
        window.dispatchEvent(
          new CustomEvent("opinions:changed", {
            detail: {
              action: "uploaded",
              id: result.id,
            },
          }),
        );

        return result;
      } else {
        throw new Error(result.message || "Upload failed");
      }
    } catch (error) {
      console.error("Upload error:", error);
      throw error;
    }
  }

  /**
   * Delete opinion
   */
  async deleteOpinion(id) {
    try {
      // Use API function
      const result = await deleteOpinionAPI(id);

      if (result.ok) {
        console.log("Opinion deleted:", id);

        // Trigger event
        window.dispatchEvent(
          new CustomEvent("opinions:changed", {
            detail: {
              action: "deleted",
              id: id,
            },
          }),
        );

        return true;
      }
      return false;
    } catch (error) {
      console.error("Delete error:", error);
      return false;
    }
  }

  /**
   * Get opinion by ID
   */
  async getOpinionById(id) {
    try {
      // Use API function
      const result = await getOpinion(id);

      if (result.ok && result.result) {
        return result.result;
      }
      return null;
    } catch (error) {
      console.error("Get opinion error:", error);
      return null;
    }
  }

  /**
   * Update existing opinion
   */
  async updateOpinion(id, updatedData) {
    try {
      const formData = new FormData();
      formData.append("id", id);

      // Append all updated fields
      for (const key in updatedData) {
        if (updatedData[key] instanceof File) {
          formData.append(key, updatedData[key]);
        } else if (typeof updatedData[key] === "object") {
          formData.append(key, JSON.stringify(updatedData[key]));
        } else {
          formData.append(key, updatedData[key]);
        }
      }

      // Use API function
      const result = await updateOpinion(id, formData);

      if (result.ok) {
        console.log("Opinion updated:", id);

        // Trigger event
        window.dispatchEvent(
          new CustomEvent("opinions:changed", {
            detail: {
              action: "updated",
              id: id,
            },
          }),
        );

        return true;
      }
      return false;
    } catch (error) {
      console.error("Update error:", error);
      return false;
    }
  }

  /**
   * Convert base64 to Blob
   */
  base64ToBlob(base64) {
    const parts = base64.split(";base64,");
    const contentType = parts[0].split(":")[1];
    const raw = window.atob(parts[1]);
    const rawLength = raw.length;
    const uInt8Array = new Uint8Array(rawLength);

    for (let i = 0; i < rawLength; ++i) {
      uInt8Array[i] = raw.charCodeAt(i);
    }

    return new Blob([uInt8Array], { type: contentType });
  }

  /**
   * Get current date formatted
   */
  getCurrentDate() {
    const days = [
      "MINGGU",
      "SENIN",
      "SELASA",
      "RABU",
      "KAMIS",
      "JUMAT",
      "SABTU",
    ];
    const months = [
      "JANUARY",
      "FEBRUARY",
      "MARCH",
      "APRIL",
      "MAY",
      "JUNE",
      "JULY",
      "AUGUST",
      "SEPTEMBER",
      "OCTOBER",
      "NOVEMBER",
      "DECEMBER",
    ];

    const now = new Date();
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();

    return `${dayName} - ${day} ${month} ${year}`;
  }
}

// Auto-initialize and expose to window
let opinionUploadManager;
document.addEventListener("DOMContentLoaded", () => {
  opinionUploadManager = new OpinionUploadManager();
  window.opinionUploadManager = opinionUploadManager;
  console.log("OpinionUploadManager ready");
});

console.log("opinions-upload-manager.js loaded");
