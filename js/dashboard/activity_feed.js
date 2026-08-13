// js/dashboard/activity_feed.js
// Activity feed: fetches from services/user_activity.php and renders the list.
// Depends on: utils.js (timeAgo)
//             article_store.js (window.articleStore.checkLoginStatus)

'use strict';

/**
 * Fetches user activity entries from the server.
 * Returns [] when the user is not logged in or authFetch is unavailable.
 * @returns {Promise<object[]>}
 */
async function fetchActivityEntries() {
  if (!window.articleStore.checkLoginStatus() || typeof authFetch !== 'function') return [];

  const response = await authFetch(
    `${window.APP_CONFIG.apiBase}/user_activity.php?limit=6`,
  );
  if (!response.ok) throw new Error(`HTTP ${response.status}`);

  const data = await response.json();
  if (!data.ok) throw new Error(data.message || 'Activity feed unavailable');

  return Array.isArray(data.activities) ? data.activities : [];
}

/**
 * Renders an empty/error state message into the activity list container.
 * @param {HTMLElement} list
 * @param {string} icon  - feather icon name
 * @param {string} message
 */
function renderActivityMessage(list, icon, message) {
  list.innerHTML = `
    <div class="dtc-empty">
      <i data-feather="${icon}"></i>
      <p>${message}</p>
    </div>
  `;
  feather.replace();
}

/** Fetches and renders the activity feed into #activityList. */
async function renderActivityFeed() {
  const list = document.getElementById('activityList');
  if (!list) return;

  let entries = [];
  try {
    entries = await fetchActivityEntries();
  } catch (error) {
    console.error('Failed to load activity feed:', error);
    renderActivityMessage(list, 'alert-circle', 'Aktivitas gagal dimuat. Coba muat ulang halaman.');
    return;
  }

  if (entries.length === 0) {
    renderActivityMessage(list, 'activity', 'Belum ada aktivitas terbaru.');
    return;
  }

  list.innerHTML = entries.map(entry => `
    <div class="activity-item">
      <span class="activity-icon ${entry.colorClass}">
        <i data-feather="${entry.icon}"></i>
      </span>
      <div class="activity-text"><p>${entry.text}</p></div>
      <span class="activity-time">${timeAgo(entry.time)}</span>
    </div>
  `).join('');

  feather.replace();
}
