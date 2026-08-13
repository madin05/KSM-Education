// js/dashboard/utils.js
// Pure utilities — no DOM, no fetch, no side-effects.
// Loaded first so every downstream module can rely on these.

'use strict';

/** Default cover image used when an article has no cover. */
const FALLBACK_COVER =
  'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop';

/**
 * Safely parses a JSON string field; returns the original value if it is
 * already an array/object, or `fallback` on failure.
 * @param {string|any} value
 * @param {any} [fallback=[]]
 */
function parseJsonField(value, fallback = []) {
  if (Array.isArray(value) || (value && typeof value === 'object')) return value;
  if (!value) return fallback;
  try { return JSON.parse(value); } catch { return fallback; }
}

/**
 * Returns the first author name from any of the known author-related fields.
 * @param {object} article
 * @returns {string}
 */
function resolveFirstAuthor(article) {
  const src = article.authors ?? article.author;
  if (Array.isArray(src) && src.length > 0) return src[0];
  return article.penulis || 'ADMIN';
}

/**
 * Wraps fetch() with a hard timeout via AbortController.
 * @param {string} url
 * @param {RequestInit} [options={}]
 * @param {number} [timeout=8000]
 * @returns {Promise<Response>}
 */
function fetchWithTimeout(url, options = {}, timeout = 8000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  return fetch(url, { ...options, signal: controller.signal })
    .finally(() => clearTimeout(timer));
}

/**
 * Escapes a string for safe use inside an HTML attribute value.
 * @param {string|null|undefined} text
 * @returns {string}
 */
function escapeForAttribute(text) {
  return (text || '')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Returns a human-readable relative time string in Bahasa Indonesia.
 * Returns '' for null / undefined / invalid dates.
 * @param {string|Date|null} dateStr
 * @returns {string}
 */
function timeAgo(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return '';
  const diffMs   = Date.now() - date.getTime();
  const diffMin  = Math.floor(diffMs / 60_000);
  if (diffMin  <  1) return 'Baru saja';
  if (diffMin  < 60) return `${diffMin} menit yang lalu`;
  const diffHour = Math.floor(diffMin  / 60);
  if (diffHour < 24) return `${diffHour} jam yang lalu`;
  const diffDay  = Math.floor(diffHour / 24);
  if (diffDay  < 30) return `${diffDay} hari yang lalu`;
  return `${Math.floor(diffDay / 30)} bulan yang lalu`;
}

/**
 * Returns the canonical detail-page slug for a given article type.
 * @param {'jurnal'|'opini'} type
 * @returns {'explore_jurnal'|'explore_opini'}
 */
function resolveDetailPage(type) {
  return type === 'opini' ? 'explore_opini' : 'explore_jurnal';
}
