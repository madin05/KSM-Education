import { CONFIG } from "./config.js";

const API_BASE = CONFIG.API_BASE;

/**
 * Private Helper: Handle response
 */
async function handleResponse(response) {
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  return await response.json();
}

/**
 * Private Helper: Wrapper untuk fetch request
 */
async function sendRequest(endpoint, options = {}) {
  try {
    const url = `${API_BASE}${endpoint}`;

    const config = {
      credentials: "same-origin",
      ...options,
    };

    if (config.body && typeof config.body === "string" && !config.headers) {
      config.headers = { "Content-Type": "application/json" };
    }

    const res = await fetch(url, config);

    if (!res.ok) {
      let errorMsg = res.statusText;
      try {
        const errorData = await res.json();
        errorMsg = errorData.message || errorMsg;
      } catch (e) {
        // Ignore json parsing error
      }
      throw new Error(`Server Error (${res.status}): ${errorMsg}`);
    }

    return await res.json();
  } catch (err) {
    console.error(`API Request Failed [${endpoint}]:`, err);
    return { ok: false, message: err.message || "Network/Server Error" };
  }
}

/**
 * FILE UPLOAD (XHR for progress)
 */
export async function uploadFileToServer(file, onProgress) {
  const endpoint = API_BASE + "/upload.php";
  const form = new FormData();
  form.append("file", file);

  try {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", endpoint, true);

    if (typeof onProgress === "function") {
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          onProgress(percent, e.loaded, e.total);
        }
      };
    }

    const res = await new Promise((resolve, reject) => {
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            resolve(JSON.parse(xhr.responseText));
          } catch (err) {
            reject(new Error("Invalid JSON response from server"));
          }
        } else {
          reject(new Error(`Upload failed: ${xhr.statusText}`));
        }
      };
      xhr.onerror = () => reject(new Error("Network error during upload"));
      xhr.send(form);
    });

    if (res && res.ok) {
      return { ok: true, url: res.url || null, id: res.id || null };
    }
    return { ok: false, message: res.message || "Upload returned ok=false" };
  } catch (err) {
    console.error("Upload error:", err);
    return { ok: false, message: err.message };
  }
}

// JOURNAL API

export async function createJournal(metadata) {
  return await sendRequest("/create_journal.php", {
    method: "POST",
    body: JSON.stringify(metadata),
  });
}

export async function listJournals(limit = 50, offset = 0) {
  const timestamp = Date.now();
  const query = `?limit=${encodeURIComponent(limit)}&offset=${encodeURIComponent(offset)}&_=${timestamp}`;
  return await sendRequest(`/list_journals.php${query}`);
}

export async function getJournal(id) {
  return await sendRequest(`/get_journal.php?id=${encodeURIComponent(id)}`);
}

export async function updateJournal(payload) {
  return await sendRequest("/update_journal.php", {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function deleteJournal(id) {
  return await sendRequest("/delete_journal.php", {
    method: "DELETE",
    body: JSON.stringify({ id }),
  });
}

// OPINION API

export async function createOpinion(opinionData) {
  const config = { method: "POST" };

  if (opinionData instanceof FormData) {
    config.body = opinionData;
  } else {
    config.body = JSON.stringify(opinionData);
    config.headers = { "Content-Type": "application/json" };
  }

  return await sendRequest("/create_opinion.php", config);
}

export async function listOpinions(limit = 50, offset = 0, category = null) {
  const timestamp = Date.now();
  let query = `?limit=${encodeURIComponent(limit)}&offset=${encodeURIComponent(offset)}&_=${timestamp}`;

  if (category && category !== "all") {
    query += `&category=${encodeURIComponent(category)}`;
  }

  return await sendRequest(`/list_opinions.php${query}`);
}

export async function getOpinion(id) {
  return await sendRequest(`/get_opinion.php?id=${encodeURIComponent(id)}`);
}

export async function updateOpinion(id, updatedData) {
  const config = { method: "POST" };

  if (updatedData instanceof FormData) {
    if (!updatedData.has("id")) updatedData.append("id", id);
    config.body = updatedData;
  } else {
    config.body = JSON.stringify({ id, ...updatedData });
    config.headers = { "Content-Type": "application/json" };
  }

  return await sendRequest("/update_opinion.php", config);
}

export async function deleteOpinion(id) {
  return await sendRequest(`/delete_opinion.php?id=${encodeURIComponent(id)}`, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
  });
}

export async function getStats() {
  const timestamp = Date.now();
  const response = await fetch(`${API_BASE}/get_stats.php?t=${timestamp}`, {
    cache: "no-store",
    headers: {
      "Cache-Control": "no-cache, no-store, must-revalidate",
      Pragma: "no-cache",
    },
  });
  return handleResponse(response);
}

export async function trackVisitor(pageUrl) {
  const response = await fetch(`${API_BASE}/track_visitor.php`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "page_url=" + encodeURIComponent(pageUrl),
  });
  return handleResponse(response);
}

// SYNC & VIEWS API

export async function syncPush(changes) {
  return await sendRequest("/sync_push.php", {
    method: "POST",
    body: JSON.stringify({ changes }),
  });
}

export async function syncPull(since) {
  const query = since ? `?since=${encodeURIComponent(since)}` : "";
  return await sendRequest(`/sync_pull.php${query}`);
}

export async function updateViews(id, type) {
  return await sendRequest("/update_views.php", {
    method: "POST",
    body: JSON.stringify({ id, type }),
  });
}

console.log("api.js loaded");
