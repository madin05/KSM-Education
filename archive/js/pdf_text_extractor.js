//  PDF TEXT EXTRACTOR USING PDF.js 
// Add this at the top of your HTML file:
// <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

class PDFTextExtractor {
  constructor() {
    // Set PDF.js worker
    if (typeof pdfjsLib !== "undefined") {
      pdfjsLib.GlobalWorkerOptions.workerSrc =
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
      console.log("PDF.js initialized");
    } else {
      console.error("PDF.js not loaded");
    }
  }

  async extractTextFromPDF(pdfUrl) {
    try {
      console.log("Loading PDF:", pdfUrl);

      const loadingTask = pdfjsLib.getDocument(pdfUrl);
      const pdf = await loadingTask.promise;

      console.log("PDF loaded, pages:", pdf.numPages);

      let fullText = "";

      // Extract text from all pages
      for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();

        // Combine text items
        const pageText = textContent.items.map((item) => item.str).join(" ");

        fullText += pageText + "\n\n";
      }

      console.log("Text extracted, length:", fullText.length);
      return this.formatExtractedText(fullText);
    } catch (error) {
      console.error("Error extracting PDF text:", error);
      return null;
    }
  }

  formatExtractedText(text) {
    // Clean up the text
    let formatted = text
      .replace(/\s+/g, " ") // Remove extra spaces
      .replace(/\n{3,}/g, "\n\n") // Remove excessive line breaks
      .trim();

    // Split into paragraphs
    const paragraphs = formatted
      .split(/\n\n+/)
      .filter((p) => p.trim().length > 50); // Filter out very short segments

    return paragraphs;
  }

  async renderPDFContent(pdfUrl, targetElement) {
    const showLoading = () => {
      targetElement.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: #666;">
          <div class="loading-spinner"></div>
          <p>Memuat konten PDF...</p>
        </div>
      `;
    };

    const showError = () => {
      targetElement.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: #d32f2f;">
          <i data-feather="alert-circle"></i>
          <p>Gagal memuat konten PDF. Silakan gunakan tombol Download PDF.</p>
        </div>
      `;
      if (typeof feather !== "undefined") feather.replace();
    };

    try {
      showLoading();

      const paragraphs = await this.extractTextFromPDF(pdfUrl);

      if (!paragraphs || paragraphs.length === 0) {
        showError();
        return;
      }

      // Render formatted content
      targetElement.innerHTML = paragraphs
        .map((para, index) => {
          // Check if paragraph looks like a heading
          if (para.length < 100 && para.split(" ").length < 15) {
            return `<h4>${para}</h4>`;
          }
          return `<p>${para}</p>`;
        })
        .join("");

      console.log("PDF content rendered");
    } catch (error) {
      console.error("Error rendering PDF:", error);
      showError();
    }
  }
}

// Export for use
window.PDFTextExtractor = PDFTextExtractor;
console.log("PDF Text Extractor loaded");
