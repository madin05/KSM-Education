// js/dashboard/article_renderer.js
// Rendering layer: skeleton loader, article grid, "My Articles" list.
// Depends on: utils.js (FALLBACK_COVER, resolveFirstAuthor, escapeForAttribute)
//             article_store.js (window.articleStore)

'use strict';

// ─── Skeleton UI ──────────────────────────────────────────────────────────────
function showSkeletonUI() {
  const grid = document.getElementById('articlesGrid');
  if (!grid) return;

  grid.innerHTML = '';
  for (let i = 0; i < 6; i++) {
    const skeleton = document.createElement('div');
    skeleton.className = 'skeleton-card';
    skeleton.innerHTML = `
      <div class="skeleton-image skeleton"></div>
      <div class="skeleton-content">
        <div class="skeleton-title skeleton"></div>
        <div class="skeleton-text skeleton"></div>
        <div class="skeleton-text skeleton"></div>
        <div class="skeleton-text short skeleton"></div>
        <div class="skeleton-tag-container">
          <div class="skeleton-tag skeleton"></div>
          <div class="skeleton-tag skeleton"></div>
        </div>
        <div class="skeleton-meta">
          <div class="skeleton-avatar skeleton"></div>
          <div class="skeleton-text short skeleton"></div>
        </div>
      </div>
    `;
    grid.appendChild(skeleton);
  }
}

// ─── Article card template ────────────────────────────────────────────────────
function _buildTagsHTML(tags) {
  if (!Array.isArray(tags) || tags.length === 0) return '';
  const visible = tags.slice(0, 3).map(tag => `<span class="article-tag">${tag}</span>`).join('');
  const extra   = tags.length > 3 ? `<span class="article-tag-more">+${tags.length - 3}</span>` : '';
  return `<div class="article-tags">${visible}${extra}</div>`;
}

function _buildArticleCardHTML(article) {
  const title         = article.title  || article.judul  || 'UNTITLED';
  const author        = resolveFirstAuthor(article);
  const rawDate       = article.date   || article.uploadDate || new Date().toISOString();
  const formattedDate = new Date(rawDate).toLocaleDateString('id-ID', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
  const coverImage    = article.coverImage || article.cover || FALLBACK_COVER;
  const views         = article.views || 0;
  const abstract      = article.abstract  || article.abstrak || '';
  const excerpt       = abstract.length > 100 ? abstract.substring(0, 100) + '...' : abstract;
  const typeLabel     = article.type === 'opini' ? 'OPINI' : 'JURNAL';
  const typeClass     = article.type === 'opini' ? 'badge-opini' : 'badge-jurnal';
  const safeTitle     = escapeForAttribute(title);
  const fileData      = article.fileData || '';

  return `
    <div class="article-card"
         onclick="if(!event.target.closest('.dropdown-menu-container')) openArticleDetail('${article.id}','${article.type}')"
         style="cursor:pointer;">
      <div class="article-image-container">
        <img src="${coverImage}" alt="${safeTitle}" class="article-image"
             onerror="this.src='${FALLBACK_COVER}'">
        <div class="article-views-badge">
          <i data-feather="eye" style="width:14px;height:14px;"></i> ${views}
        </div>
        <span class="article-type-badge ${typeClass}">${typeLabel}</span>
      </div>
      <div class="article-content">
        <h3 class="article-title">${title}</h3>
        <p class="article-excerpt">${excerpt || 'Tidak ada deskripsi'}</p>
        <div class="article-meta">
          <span><i data-feather="user" style="width:14px;height:14px;"></i> ${author}</span>
          <span><i data-feather="calendar" style="width:14px;height:14px;"></i> ${formattedDate}</span>
        </div>
        ${_buildTagsHTML(article.tags)}
        <div class="card-actions">
          <div class="dropdown-menu-container">
            <button class="dropdown-toggle"
                    onclick="event.stopPropagation(); window.dashboardDropdownToggle('dashboard-dd-${article.id}')">
              <i data-feather="more-vertical"></i>
            </button>
            <div id="dashboard-dd-${article.id}" class="dropdown-content">
              <button class="dropdown-item-btn dd-download"
                      onclick="event.stopPropagation(); window.downloadDashboardArticle('${fileData}','${safeTitle}','${article.type}','${article.id}')">
                <i data-feather="download"></i> Download
              </button>
              <button class="dropdown-item-btn dd-share"
                      onclick="event.stopPropagation(); if(window.shareManager) window.shareManager.handleShare('${article.id}','${article.type}','${safeTitle}')">
                <i data-feather="share-2"></i> Share
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

// ─── Main grid renderer ───────────────────────────────────────────────────────
async function renderArticles() {
  const grid    = document.getElementById('articlesGrid');
  const navUser = document.getElementById('latestArticlesNavUser');

  showSkeletonUI();
  if (navUser) navUser.innerHTML = '';

  window.articleStore.articles = await window.articleStore.loadArticles();
  const articles = window.articleStore.articles;

  if (articles.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon"></div>
        <h3>BELUM ADA ARTIKEL</h3>
        <p>ARTIKEL AKAN MUNCUL DI SINI SETELAH ADMIN MENGUPLOAD JURNAL</p>
      </div>
    `;
    return;
  }

  grid.innerHTML = articles.slice(0, 6).map(_buildArticleCardHTML).join('');

  if (navUser) {
    navUser.innerHTML = articles.length > 6
      ? `<button class="btn-see-all" onclick="window.location.href='journals'">LIHAT SEMUA ARTIKEL</button>`
      : '';
  }

  feather.replace();
  console.log('Articles rendered, Share buttons ready');
}

// ─── "My Articles" list ───────────────────────────────────────────────────────

/**
 * Returns the display name derived from the logged-in user's email, or null.
 * @returns {string|null}
 */
function getCurrentUserDisplayName() {
  const userEmail = sessionStorage.getItem('userEmail');
  if (!userEmail) return null;
  return userEmail.split('@')[0].toUpperCase();
}

function renderMyArticles() {
  const list = document.getElementById('myArticlesList');
  if (!list) return;

  const myName   = getCurrentUserDisplayName();
  const articles = window.articleStore.articles;
  const mine     = myName
    ? articles.filter(a => (resolveFirstAuthor(a) || '').toUpperCase().includes(myName))
    : [];

  if (mine.length === 0) {
    list.innerHTML = `
      <div class="dtc-empty">
        <i data-feather="file-text"></i>
        <p>Belum ada artikel yang Anda upload.</p>
      </div>
    `;
    feather.replace();
    return;
  }

  list.innerHTML = mine.slice(0, 4).map(a => {
    const title   = a.title || a.judul || 'Untitled';
    const cover   = a.coverImage || a.cover || FALLBACK_COVER;
    const views   = a.views || 0;
    const dateStr = new Date(a.date || a.uploadDate || Date.now())
      .toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

    return `
      <div class="my-article-item">
        <img class="mai-thumb" src="${cover}" alt="${escapeForAttribute(title)}"
             onerror="this.src='${FALLBACK_COVER}'">
        <div class="mai-info">
          <div class="mai-title-row">
            <span class="mai-title">${title}</span>
            <span class="mai-status-badge published">Published</span>
          </div>
          <div class="mai-meta">
            <span><i data-feather="eye"></i> ${views} Views</span>
            <span><i data-feather="calendar"></i> ${dateStr}</span>
          </div>
        </div>
        <div class="mai-actions">
          <button type="button" class="mai-edit-btn"
                  onclick="window.editUserArticle('${a.id}','${a.type}')">Edit</button>
        </div>
      </div>
    `;
  }).join('');

  feather.replace();
}

// TODO: arahkan ke route edit yang sebenarnya. Placeholder ini mengasumsikan
// halaman detail punya mode edit lewat query "?edit=1" — sesuaikan kalau
// route edit Anda berbeda (modal upload dengan mode edit, atau halaman khusus).
window.editUserArticle = function (id, type) {
  window.location.href = `${resolveDetailPage(type)}?id=${id}&type=${type}&edit=1`;
};
