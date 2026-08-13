// js/dashboard/share_manager.js
// Share + Categories + Dropdown/Download/Social-share globals.
// Depends on: utils.js (escapeForAttribute, resolveDetailPage)

'use strict';

// ─── ShareManager ─────────────────────────────────────────────────────────────
class ShareManager {
  constructor() {
    this._init();
  }

  _init() {
    console.log('Initializing Share Manager...');
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this._setupEventListeners());
    } else {
      this._setupEventListeners();
    }
  }

  _setupEventListeners() {
    // Delegated listener — handles .btn-share-article buttons rendered after init
    document.body.addEventListener('click', (e) => {
      const shareBtn = e.target.closest('.btn-share-article');
      if (!shareBtn) return;
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      this.handleShare(
        shareBtn.getAttribute('data-article-id'),
        shareBtn.getAttribute('data-article-type'),
        shareBtn.getAttribute('data-article-title'),
      );
    }, true);

    console.log('Share event listeners attached');
  }

  handleShare(articleId, articleType, articleTitle) {
    const base      = window.location.origin;
    const dir       = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const shareUrl  = `${base}${dir}/${resolveDetailPage(articleType)}?id=${articleId}&type=${articleType}`;
    console.log('Sharing URL:', shareUrl);
    this.copyToClipboard(shareUrl, articleTitle);
  }

  async copyToClipboard(url, title) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
      } else {
        this._fallbackCopy(url);
      }
      this._showShareSuccess(title);
    } catch {
      try {
        this._fallbackCopy(url);
        this._showShareSuccess(title);
      } catch {
        showToast('Gagal menyalin link. Silakan coba lagi.', 'error');
      }
    }
  }

  _fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    Object.assign(ta.style, { position: 'fixed', left: '-999999px', top: '-999999px' });
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy');
    } finally {
      ta.remove();
    }
  }

  _showShareSuccess(title) {
    const short = title.length > 40 ? title.substring(0, 40) + '...' : title;
    if (typeof showToast === 'function') {
      showToast(`"${short}"`, 'success', 'Link berhasil disalin!');
    } else {
      showAlert.success(`Link berhasil disalin!\n"${short}"`, 'Sukses');
    }
  }
}

// ─── DynamicCategoriesManager ─────────────────────────────────────────────────
class DynamicCategoriesManager {
  constructor() {
    /** @type {Map<string, number>} */
    this.categories = new Map();
    this._loadCategories();
  }

  async _loadCategories() {
    try {
      console.log('Loading dynamic categories from database...');
      const t    = Date.now();
      const base = window.APP_CONFIG.apiBase;
      const opt  = { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } };

      const [journalsRes, opinionsRes] = await Promise.all([
        fetch(`${base}/list_journals.php?limit=100&offset=0&t=${t}`, opt),
        fetch(`${base}/list_opinions.php?limit=1000&offset=0&t=${t}`, opt)
          .catch(() => ({ json: async () => ({ ok: false, results: [] }) })),
      ]);

      const [jData, oData] = await Promise.all([
        journalsRes.json(),
        opinionsRes.json(),
      ]);

      const all = [
        ...(jData.ok ? jData.results : []),
        ...(oData.ok ? oData.results : []),
      ];

      this._processArticleTags(all);
      this._renderCategories();
      console.log(`Loaded ${this.categories.size} dynamic categories`);
    } catch (error) {
      console.error('Error loading categories:', error);
      this._renderFallbackCategories();
    }
  }

  _processArticleTags(articles) {
    this.categories.clear();
    articles.forEach(article => {
      const tags = parseJsonField(article.tags);
      if (!Array.isArray(tags)) return;
      tags.forEach(tag => {
        const norm = this._normalizeTag(tag);
        if (norm) this.categories.set(norm, (this.categories.get(norm) || 0) + 1);
      });
    });
    // Sort by frequency desc
    this.categories = new Map(
      [...this.categories.entries()].sort((a, b) => b[1] - a[1]),
    );
  }

  _normalizeTag(tag) {
    if (!tag || typeof tag !== 'string') return null;
    return tag.trim().split(' ')
      .map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
      .join(' ');
  }

  _renderCategories() {
    const grid = document.querySelector('.categories-grid');
    if (!grid) { console.warn('Categories grid element not found'); return; }

    const top = [...this.categories.entries()].slice(0, 12);
    if (top.length === 0) {
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:var(--ksm-c-muted-text,#999);padding:40px 0;">
        Belum ada kategori. Tambahkan tags saat upload artikel.</div>`;
      return;
    }

    grid.innerHTML = top.map(([cat, count]) => `
      <div class="category-card"
           onclick="window.location.href='journals?category=${encodeURIComponent(cat)}'"
           style="cursor:pointer;">
        <span class="category-name">${this._escapeHtml(cat)}</span>
        <span class="category-count">(${count})</span>
      </div>
    `).join('');

    console.log(`Rendered ${top.length} categories to UI`);
  }

  _renderFallbackCategories() {
    const grid = document.querySelector('.categories-grid');
    if (!grid) return;
    grid.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;color:var(--ksm-c-muted-text,#999);padding:40px 0;">
        <div style="font-size:48px;margin-bottom:16px;opacity:.3;"></div>
        <p>Belum ada kategori.</p>
        <small style="opacity:.7;">Kategori akan muncul otomatis dari tags artikel.</small>
      </div>
    `;
  }

  _escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// ─── Dropdown toggle ──────────────────────────────────────────────────────────
window.dashboardDropdownToggle = function (dropdownId) {
  const dropdown = document.getElementById(dropdownId);
  if (!dropdown) return;
  document.querySelectorAll('.dropdown-content').forEach(el => {
    if (el.id !== dropdownId) el.style.display = 'none';
  });
  dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
};

// Close dropdowns on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown-menu-container')) {
    document.querySelectorAll('.dropdown-content').forEach(el => {
      el.style.display = 'none';
    });
  }
});

// ─── Download article ─────────────────────────────────────────────────────────
window.downloadDashboardArticle = async function (fileUrlOrId, itemTitle, dataType, itemId) {
  try {
    let fileUrl = fileUrlOrId;

    if (!fileUrl || (!fileUrl.includes('/') && !fileUrl.includes('http'))) {
      const id       = fileUrlOrId || itemId;
      const endpoint = dataType === 'opini'
        ? `${window.APP_CONFIG.apiBase}/get_opinion.php?id=${id}`
        : `${window.APP_CONFIG.apiBase}/get_journal.php?id=${id}`;

      console.log('Fetching file URL from API:', endpoint);
      const resp = await fetch(endpoint);
      const data = await resp.json();

      if (!data.ok) {
        showAlert.error(data.message || 'File tidak ditemukan!', 'Download Gagal');
        return;
      }
      fileUrl = data.data?.file_url || data.file_url || data.fileUrl;
    }

    if (!fileUrl) {
      showAlert.error('File tidak ditemukan!', 'Download Gagal');
      return;
    }

    console.log('Triggering download for:', fileUrl);
    const link      = document.createElement('a');
    link.href       = fileUrl;
    link.download   = `${itemTitle}.pdf`;
    link.target     = '_blank';
    document.body.appendChild(link);
    link.click();
    setTimeout(() => document.body.removeChild(link), 100);

    if (typeof showToast === 'function') {
      showToast(`${itemTitle}.pdf berhasil diunduh!`, 'success', 'Download Sukses');
    } else {
      showAlert.success(`${itemTitle}.pdf berhasil diunduh!`, 'Download Sukses');
    }
  } catch (error) {
    console.error('Download error:', error);
    showAlert.error('Gagal download file: ' + error.message, 'Download Gagal');
  } finally {
    document.querySelectorAll('.dropdown-content').forEach(el => el.style.display = 'none');
  }
};

// ─── Share modal ──────────────────────────────────────────────────────────────
window.openDashboardShareModal = function (itemId, itemTitle, dataType) {
  const pageSlug    = resolveDetailPage(dataType);
  const fullShareUrl = `${window.location.origin}${window.APP_CONFIG.ROOT}/${pageSlug}?id=${itemId}&type=${dataType}`;

  let modal = document.getElementById('dashboardShareModal');
  if (!modal) {
    modal    = document.createElement('div');
    modal.id = 'dashboardShareModal';
    modal.innerHTML = `
      <div class="modal">
        <div class="modal-overlay" onclick="document.getElementById('dashboardShareModal').style.display='none'"></div>
        <div class="modal-content" style="max-width:400px">
          <button type="button" class="close-modal"
                  onclick="document.getElementById('dashboardShareModal').style.display='none'">
            <i data-feather="x"></i>
          </button>
          <h2 style="margin-bottom:20px">Bagikan ${itemTitle}</h2>
          <div style="display:flex;flex-direction:column;gap:12px">
            <input type="text" id="dashboardShareUrlInput" value="${fullShareUrl}" readonly
                   style="padding:12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
            <button onclick="window.copyDashboardLink()"
                    class="share-btn copy"
                    style="width:100%;padding:10px;border:none;cursor:pointer;border-radius:6px;background:#3498db;color:white;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i data-feather="copy" style="width:16px;height:16px;"></i> Copy Link
            </button>
            <button onclick="window.shareToDashboardWhatsApp('${fullShareUrl}','${itemTitle}')"
                    class="share-btn wa"
                    style="width:100%;padding:10px;border:none;cursor:pointer;border-radius:6px;background:#25d366;color:white;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i data-feather="message-circle" style="width:16px;height:16px;"></i> Share ke WhatsApp
            </button>
            <button onclick="window.shareToDashboardFacebook('${fullShareUrl}')"
                    class="share-btn fb"
                    style="width:100%;padding:10px;border:none;cursor:pointer;border-radius:6px;background:#1877f2;color:white;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i data-feather="facebook" style="width:16px;height:16px;"></i> Share ke Facebook
            </button>
            <button onclick="window.shareToDashboardTwitter('${fullShareUrl}','${itemTitle}')"
                    class="share-btn x"
                    style="width:100%;padding:10px;border:none;cursor:pointer;border-radius:6px;background:#000;color:white;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i data-feather="x" style="width:16px;height:16px;"></i> Share ke X
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  } else {
    modal.querySelector('#dashboardShareUrlInput').value = fullShareUrl;
  }

  modal.style.display = 'block';
  if (typeof feather !== 'undefined') feather.replace();
  document.querySelectorAll('.dropdown-content').forEach(el => el.style.display = 'none');
};

// ─── Copy link (used inside share modal) ─────────────────────────────────────
window.copyDashboardLink = function () {
  const input = document.getElementById('dashboardShareUrlInput');
  if (!input?.value) return;

  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(input.value)
      .then(()  => showAlert.success('Link berhasil disalin ke clipboard!', 'Informasi'))
      .catch(()  => showAlert.error('Gagal menyalin link', 'Error'));
  } else {
    input.select();
    try {
      document.execCommand('copy');
      showAlert.success('Link berhasil disalin ke clipboard!', 'Informasi');
    } catch {
      showAlert.error('Gagal menyalin link', 'Error');
    }
  }
};

// ─── Social share helpers ─────────────────────────────────────────────────────
window.shareToDashboardWhatsApp = function (url, title) {
  const wa = `https://wa.me/?text=${encodeURIComponent(`Cek artikel "${title}" di sini: ${url}`)}`;
  showAlert.success('Membuka WhatsApp...', 'Informasi');
  setTimeout(() => window.open(wa, '_blank'), 300);
};

window.shareToDashboardFacebook = function (url) {
  const fb = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}&quote=Cek%20artikel%20ini`;
  showAlert.success('Membuka Facebook...', 'Informasi');
  setTimeout(() => window.open(fb, '_blank', 'width=600,height=400'), 300);
};

window.shareToDashboardTwitter = function (url, title) {
  const tw = `https://twitter.com/intent/tweet?text=${encodeURIComponent(`Cek artikel "${title}" di KSM Education`)}&url=${encodeURIComponent(url)}`;
  showAlert.success('Membuka X (Twitter)...', 'Informasi');
  setTimeout(() => window.open(tw, '_blank'), 300);
};

// ─── Initialise on load ───────────────────────────────────────────────────────
console.log('Initializing Share & Dynamic Categories...');
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.shareManager              = new ShareManager();
    window.dynamicCategoriesManager = new DynamicCategoriesManager();
  });
} else {
  window.shareManager              = new ShareManager();
  window.dynamicCategoriesManager = new DynamicCategoriesManager();
}
