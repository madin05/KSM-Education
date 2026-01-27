import { showToast } from "./toast.js";

export function setupShareHandler() {
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".btn-share-article");
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const id = btn.dataset.articleId || btn.getAttribute("data-article-id");
    const type =
      btn.dataset.articleType || btn.getAttribute("data-article-type");
    const title =
      btn.dataset.articleTitle || btn.getAttribute("data-article-title");

    const page =
      type === "opini" ? "explore_opini_user.html" : "explore_jurnal_user.html";
    const baseUrl = window.location.origin;
    const path = window.location.pathname.substring(
      0,
      window.location.pathname.lastIndexOf("/"),
    );
    const shareUrl = `${baseUrl}${path}/${page}?id=${id}&type=${type}`;

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(shareUrl);
      } else {
        fallbackCopy(shareUrl);
      }
      const shortTitle =
        title.length > 40 ? title.substring(0, 40) + "..." : title;
      showToast(
        `✓ Link disalin!<br><small style="opacity: 0.8">"${shortTitle}"</small>`,
        "success",
      );
    } catch (err) {
      console.error("Copy failed:", err);
      showToast("Gagal menyalin link. Silakan coba lagi.", "error");
    }
  });
}

function fallbackCopy(text) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  document.body.appendChild(textArea);
  textArea.select();
  document.execCommand("copy");
  textArea.remove();
}
