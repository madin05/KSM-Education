// ===== EXPLORE JURNAL USER - DATABASE VERSION =====
console.log("Starting explore_jurnal_user.js (Database Mode)");

// Initialize Feather Icons
if (typeof feather !== "undefined") {
  feather.replace();
} else {
  console.warn("Feather icons not loaded");
}

// Initialize PDF Extractor
let pdfExtractor = null;
if (typeof PDFTextExtractor !== "undefined") {
  pdfExtractor = new PDFTextExtractor();
  console.log("PDF Extractor ready");
} else {
  console.warn("PDF Extractor not available - text extraction disabled");
}

// ===== GET ARTICLE BY ID FROM DATABASE =====
async function getArticleById(id, type) {
  console.log("Getting article from database:", id, type);

  try {
    if (type === "jurnal") {
      const response = await fetch(`${window.APP_CONFIG.apiBase}/get_journal.php?id=${id}`);
      const data = await response.json();

      console.log("API Response:", data);

      if (data.ok && data.journal) {
        const j = data.journal;

        // Parse JSON fields
        let authors = j.authors;
        if (typeof authors === "string") {
          try {
            authors = JSON.parse(authors);
          } catch (e) {
            authors = [authors];
          }
        }

        let tags = j.tags;
        if (typeof tags === "string") {
          try {
            tags = JSON.parse(tags);
          } catch (e) {
            tags = [tags];
          }
        }

        let pengurus = j.pengurus;
        if (typeof pengurus === "string") {
          try {
            pengurus = JSON.parse(pengurus);
          } catch (e) {
            pengurus = [pengurus];
          }
        }

        return {
          id: j.id,
          title: j.title,
          judul: j.title,
          abstract: j.abstract,
          abstrak: j.abstract,
          authors: authors,
          author: authors,
          penulis: authors.length > 0 ? authors[0] : "Unknown",
          tags: tags,
          pengurus: pengurus,
          volume: j.volume,
          date: j.created_at,
          uploadDate: j.created_at,
          fileData: j.file_url,
          file: j.file_url,
          pdfUrl: j.file_url,
          coverImage: j.cover_url,
          cover: j.cover_url,
          email: j.email,
          contact: j.contact,
          kontak: j.contact,
          phone: j.contact,
          views: j.views || 0,
          type: "jurnal",
        };
      } else {
        console.error(" Journal not found:", data);
        return null;
      }
    } else if (type === "opini") {
      const response = await fetch(`${window.APP_CONFIG.apiBase}/get_opinion.php?id=${id}`);
      const data = await response.json();

      console.log("Opinion Response:", data);

      const o = data.opinion || data.result;

      if (data.ok && o) {
        return {
          id: o.id,
          title: o.title,
          judul: o.title,
          description: o.description,
          abstract: o.description,
          abstrak: o.description,
          category: o.category || "opini",
          author: [o.author_name || "Anonymous"],
          authors: [o.author_name || "Anonymous"],
          penulis: o.author_name || "Anonymous",
          email: o.email || "",
          kontak: o.kontak || o.contact || "",
          date: o.created_at,
          uploadDate: o.created_at,
          coverImage: o.cover_url,
          cover: o.cover_url,
          fileUrl: o.file_url,
          fileData: o.file_url,
          file: o.file_url,
          pdfUrl: o.file_url,
          views: o.views || 0,
          type: "opini",
        };
      } else {
        console.error(" Opinion not found:", data);
        return null;
      }
    }
  } catch (error) {
    console.error(" Error fetching article:", error);
    return null;
  }
}

// ===== LOAD ARTICLE DETAIL =====
async function loadArticleDetail() {
  console.log("Loading article detail...");

  const urlParams = new URLSearchParams(window.location.search);
  const articleId = urlParams.get("id");
  const articleType = urlParams.get("type") || "jurnal";

  console.log("Article ID:", articleId, "Type:", articleType);

  const loadingState = document.getElementById("loadingState");
  const errorState = document.getElementById("errorState");
  const articleDetail = document.getElementById("articleDetail");

  if (loadingState) {
    loadingState.style.display = "flex";
  }
  if (errorState) {
    errorState.style.display = "none";
  }
  if (articleDetail) {
    articleDetail.style.display = "none";
  }

  if (!articleId) {
    showError(
      "Article ID missing from URL\n\nDebug Info:\nArticle ID: null\nType: " +
        articleType
    );
    return;
  }

  try {
    const article = await getArticleById(articleId, articleType);

    if (!article) {
      showError(
        "Article not found in database\n\nDebug Info:\nArticle ID: " +
          articleId +
          "\nType: " +
          articleType +
          "\nError: Article not found in database"
      );
      return;
    }

    console.log(" Article loaded:", article);

    if (loadingState) {
      loadingState.style.display = "none";
    }

    if (articleDetail) {
      articleDetail.style.display = "block";
      console.log(" Article detail shown");
    }

    await displayArticle(article, articleType);
  } catch (error) {
    console.error(" Error loading article:", error);
    showError(
      "Failed to load article\n\nDebug Info:\nArticle ID: " +
        articleId +
        "\nType: " +
        articleType +
        "\nError: " +
        error.message
    );
  }
}

// ===== DISPLAY ARTICLE =====
async function displayArticle(article, type) {
  console.log("Displaying article:", article.title);

  // Title
  const titleElement = document.getElementById("articleTitle");
  if (titleElement) {
    titleElement.textContent = article.title || article.judul || "Untitled";
  }

  // Cover Image
  const coverImg = document.getElementById("articleCover");
  if (coverImg) {
    const coverUrl =
      article.coverImage ||
      article.cover ||
      "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&h=400&fit=crop";

    coverImg.style.display = "block";
    coverImg.src = coverUrl;
    coverImg.onerror = () => {
      coverImg.src =
        "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&h=400&fit=crop";
    };

    console.log(" Cover image set:", coverUrl);
  }

  // Meta info
  const dateElement = document.getElementById("articleDate");
  if (dateElement) {
    const date = new Date(article.date || article.uploadDate);
    dateElement.textContent = date.toLocaleDateString("id-ID", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }

  const viewsElement = document.getElementById("articleViews");
  if (viewsElement) {
    viewsElement.textContent = article.views || 0;
  }

  // Abstract
  const abstractElement = document.getElementById("articleAbstract");
  if (abstractElement) {
    abstractElement.textContent =
      article.abstract || article.abstrak || "No abstract available";
  }

  // Tags
  const tagsSection = document.getElementById("tagsSection");
  const tagsContainer = document.getElementById("articleTags");
  if (tagsSection && tagsContainer) {
    if (article.tags && article.tags.length > 0) {
      tagsSection.style.display = "block";
      tagsContainer.innerHTML = article.tags
        .map((tag) => `<span class="tag">${tag}</span>`)
        .join("");
      console.log(" Tags displayed:", article.tags.length);
    } else {
      tagsSection.style.display = "none";
    }
  }

  // Authors
  const authorsContainer = document.getElementById("articleAuthors");
  if (authorsContainer) {
    if (
      article.authors &&
      Array.isArray(article.authors) &&
      article.authors.length > 0
    ) {
      authorsContainer.innerHTML = article.authors
        .map(
          (author) => `
          <div class="author-card">
            <i data-feather="user"></i>
            <span>${author}</span>
          </div>
        `
        )
        .join("");
      console.log(" Authors displayed:", article.authors);
    } else {
      const singleAuthor =
        article.author || article.penulis || "Unknown Author";
      authorsContainer.innerHTML = `
        <div class="author-card">
          <i data-feather="user"></i>
          <span>${singleAuthor}</span>
        </div>
      `;
    }
  }

  // Pengurus (only for jurnal)
  const pengurusSection = document.getElementById("pengurusSection");
  const pengurusContainer = document.getElementById("articlePengurus");
  if (pengurusSection && pengurusContainer) {
    if (type === "jurnal" && article.pengurus && article.pengurus.length > 0) {
      pengurusSection.style.display = "block";
      pengurusContainer.innerHTML = article.pengurus
        .map(
          (pengurus) => `
          <div class="author-card">
            <i data-feather="briefcase"></i>
            <span>${pengurus}</span>
          </div>
        `
        )
        .join("");
      console.log(" Pengurus displayed:", article.pengurus.length);
    } else {
      pengurusSection.style.display = "none";
    }
  }

  // Contact
  const emailLink = document.getElementById("articleEmail");
  const phoneEl = document.getElementById("articlePhone");

  if (emailLink) {
    const email = article.email || "-";
    emailLink.textContent = email;
    emailLink.href = email !== "-" ? `mailto:${email}` : "#";
    console.log("Email set:", email);
  }

  if (phoneEl) {
    const kontak = article.kontak || article.contact || article.phone || "-";
    phoneEl.textContent = kontak;
    console.log("Kontak set:", kontak);
  }

  // Volume
  const volumeSection = document.getElementById("volumeSection");
  const volumeElement = document.getElementById("articleVolume");

  if (volumeSection && volumeElement) {
    if (type === "jurnal" && article.volume) {
      volumeSection.style.display = "block";
      volumeElement.textContent = article.volume;
      console.log(" Volume displayed:", article.volume);
    } else {
      volumeSection.style.display = "none";
    }
  }

  // PDF VIEWER
  const pdfSection = document.getElementById("pdfSection");
  if (pdfSection) {
    const pdfUrl =
      article.file_url || article.fileData || article.file || article.pdfUrl;

    if (pdfUrl) {
      pdfSection.style.display = "block";

      // Base64 encode the file path to hide '.pdf' from download managers (e.g. IDM)
      const encodedFile = btoa(pdfUrl);
      const servePdfUrl =
        window.APP_CONFIG.apiBase + "/serve_pdf.php?file=" + encodeURIComponent(encodedFile);

      const pdfViewer = document.getElementById("pdfViewer");
      if (pdfViewer) {
        if (typeof pdfjsLib !== "undefined") {
          renderPdfWithPdfJs(servePdfUrl + "&preview=1", pdfViewer);
        } else {
          pdfViewer.innerHTML = `<iframe id="pdfIframe" src="${servePdfUrl}"></iframe>`;
        }
      }

      const downloadLink = document.getElementById("pdfDownload");
      if (downloadLink) {
        // Use serve_pdf.php so the server sends correct Content-Disposition header
        downloadLink.href = servePdfUrl;
        downloadLink.removeAttribute("download"); // let serve_pdf handle disposition
        downloadLink.setAttribute("target", "_blank");
      }
    } else {
      pdfSection.style.display = "none";
    }
  }


  // Replace feather icons
  if (typeof feather !== "undefined") {
    feather.replace();
  }

  console.log(" Article displayed successfully");
}

// ===== SHOW ERROR =====
function showError(message) {
  console.error(" Showing error:", message);

  const loadingState = document.getElementById("loadingState");
  const errorState = document.getElementById("errorState");

  if (loadingState) loadingState.style.display = "none";
  if (errorState) {
    errorState.style.display = "flex";
    const errorDebug = errorState.querySelector("p");
    if (errorDebug) {
      errorDebug.innerHTML = `
        ${errorDebug.textContent}<br><br>
        <strong>Debug Info:</strong><br>
        ${message.replace(/\n/g, "<br>")}
      `;
    }
  }
}


// ===== SEARCH FUNCTIONALITY =====
const searchInput = document.getElementById("searchInput");
const searchModal = document.getElementById("searchModal");
const closeSearchModal = document.getElementById("closeSearchModal");
const searchResults = document.getElementById("searchResults");
let searchTimeout;

if (searchInput) {
  searchInput.addEventListener("input", function (e) {
    const query = e.target.value.trim();
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
      if (query.length >= 2) {
        performSearch(query);
        if (searchModal) searchModal.style.display = "flex";
      } else {
        if (searchModal) searchModal.style.display = "none";
      }
    }, 300);
  });
}

if (closeSearchModal) {
  closeSearchModal.addEventListener("click", function () {
    if (searchModal) searchModal.style.display = "none";
  });
}

if (searchModal) {
  searchModal.addEventListener("click", function (e) {
    if (e.target === searchModal) {
      searchModal.style.display = "none";
    }
  });
}

async function performSearch(query) {
  try {
    const journalsResp = await fetch(
      `${window.APP_CONFIG.apiBase}/list_journals.php?limit=50&offset=0`
    );
    const journalsData = await journalsResp.json();

    const opinionsResp = await fetch(
      `${window.APP_CONFIG.apiBase}/list_opinion.php?limit=50&offset=0`
    );
    const opinionsData = await opinionsResp.json();

    const journals = journalsData.ok
      ? journalsData.results.map((j) => ({ ...j, type: "jurnal" }))
      : [];
    const opinions = opinionsData.ok
      ? opinionsData.results.map((o) => ({ ...o, type: "opini" }))
      : [];

    const allArticles = [...journals, ...opinions];

    const results = allArticles.filter((article) => {
      const title = (article.title || "").toLowerCase();
      const abstract = (
        article.abstract ||
        article.description ||
        ""
      ).toLowerCase();
      const searchQuery = query.toLowerCase();

      return title.includes(searchQuery) || abstract.includes(searchQuery);
    });

    displaySearchResults(results, query);
  } catch (error) {
    console.error(" Search error:", error);
  }
}

function displaySearchResults(results, query) {
  if (!searchResults) return;

  if (results.length === 0) {
    searchResults.innerHTML = `
      <div class="search-no-results">
        <p>Tidak ada artikel yang cocok dengan pencarian "${query}"</p>
      </div>
    `;
    return;
  }

  searchResults.innerHTML = results
    .map(
      (article) => `
    <div class="search-result-item" onclick="window.location.href='explore_jurnal_user.php?id=${
      article.id
    }&type=${article.type}'">
      <div class="search-result-title">${article.title}</div>
      <div class="search-result-excerpt">${(
        article.abstract ||
        article.description ||
        ""
      ).substring(0, 150)}...</div>
      <div class="search-result-meta">
        <span class="badge">${
          article.type === "jurnal" ? "Jurnal" : "Opini"
        }</span>
      </div>
    </div>
  `
    )
    .join("");
}

async function renderPdfWithPdfJs(pdfUrl, container) {
  container.innerHTML = `
    <div id="pdfLoading" style="text-align: center; padding: 3rem; color: #666;">
      <div class="loading-spinner" style="border: 4px solid rgba(0,0,0,0.1); width: 36px; height: 36px; border-radius: 50%; border-left-color: #2c5f7d; animation: spin 1s linear infinite; margin: 0 auto 10px;"></div>
      <style>
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
      </style>
      <p>Memuat lembar PDF...</p>
    </div>
  `;

  try {
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

    // Fetch the PDF binary stream manually to bypass extension interceptions (like IDM)
    const response = await fetch(pdfUrl);
    if (!response.ok) throw new Error("Gagal mengunduh file PDF.");
    const arrayBuffer = await response.arrayBuffer();

    const loadingTask = pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer) });
    const pdf = await loadingTask.promise;

    container.innerHTML = ""; // Clear loading

    if (pdf.numPages === 0) {
      container.innerHTML = `<p style="text-align:center;color:#888;padding:40px 0;">Dokumen PDF kosong.</p>`;
      return;
    }

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum);
      
      const containerWidth = container.clientWidth || 800;
      const unscaledViewport = page.getViewport({ scale: 1.0 });
      const scale = containerWidth / unscaledViewport.width;
      const viewport = page.getViewport({ scale: Math.min(scale, 1.5) });

      const canvas = document.createElement("canvas");
      canvas.className = "pdf-page-canvas";
      canvas.style.display = "block";
      canvas.style.margin = "15px auto";
      canvas.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
      canvas.style.maxWidth = "100%";
      canvas.style.background = "white";
      canvas.style.borderRadius = "8px";

      const context = canvas.getContext("2d");
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      const renderContext = {
        canvasContext: context,
        viewport: viewport,
      };

      container.appendChild(canvas);
      await page.render(renderContext).promise;
    }

  } catch (error) {
    console.error("PDF.js render failed:", error);
    container.innerHTML = `<iframe id="pdfIframe" src="${pdfUrl}" style="width: 100%; height: 650px; border: none;"></iframe>`;
  }
}

// ===== INITIALIZE =====
document.addEventListener("DOMContentLoaded", () => {
  console.log("Explore Jurnal User initialized (Database Mode)");

  loadArticleDetail();

  if (typeof feather !== "undefined") feather.replace();
});

console.log(" explore_jurnal_user.js loaded (Database Mode)");
