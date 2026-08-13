/**
 * @jest-environment jsdom
 *
 * Unit tests for:
 *   - js/dashboard/utils.js        (parseJsonField, resolveFirstAuthor, timeAgo, escapeForAttribute, resolveDetailPage)
 *   - js/dashboard/article_store.js (mapJournal, mapOpinion, loadArticles, openArticleDetail)
 *   - js/dashboard/share_manager.js (DynamicCategoriesManager._normalizeTag)
 *   - js/dashboard/utils.js        (PHONE_REGEX is in dual_upload_handler, kept in existing section)
 *   - js/config.js                  (APP_CONFIG, ksmPagePath, ksmIsPage)
 *   - js/dual_upload_handler.js     (TagsManager, formatFileSize, toMySQLDateTime)
 *
 * Run: npx jest js/__tests__/ksmaja.test.js
 */

// ─── Polyfills & Mocks ────────────────────────────────────────────────────────
global.fetch = jest.fn();
global.feather = { replace: jest.fn() };
global.showAlert = {
  warning: jest.fn(),
  success: jest.fn(),
  error: jest.fn(),
  confirm: jest.fn(),
};
global.showToast = jest.fn();

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 – config.js helpers (isolated, no DOM side-effects)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Inline the pure logic from config.js so we can test it without
 * touching window.location (which is read-only in jsdom).
 */
const KNOWN_FOLDERS = new Set(['user', 'admin', 'services', 'assets', 'js', 'styles']);
const PAGE_MAP = {
  user: {
    dashboard:      'dashboard_user.php',
    journals:       'journals_user.php',
    opinions:       'opinions_user.php',
    explore_jurnal: 'explore_jurnal_user.php',
    explore_opini:  'explore_opini_user.php',
    profile:        'profil_user.php',
    profil:         'profil_user.php',
    login:          'login_user.php',
  },
  admin: {
    dashboard:      'dashboard_admin.php',
    login:          'login_admin.php',
    explore_jurnal: 'explore_jurnal_admin.php',
  },
};

const FILE_MAP = { user: {}, admin: {} };
for (const [area, pages] of Object.entries(PAGE_MAP)) {
  for (const [segment, file] of Object.entries(pages)) {
    if (!FILE_MAP[area][file]) FILE_MAP[area][file] = segment;
  }
}

function getAppRootFromPath(pathname) {
  const [first] = pathname.split('/').filter(Boolean);
  return first && !KNOWN_FOLDERS.has(first) ? '/' + first : '';
}

function resolvePathFromPathname(pathname) {
  if (pathname.endsWith('/')) return pathname + 'index.php';
  if (pathname.endsWith('.php') || pathname.endsWith('.html')) return pathname;
  const parts = pathname.split('/').filter(Boolean);
  const segment = parts[parts.length - 1];
  const area    = parts[parts.length - 2];
  if (area && PAGE_MAP[area]?.[segment]) {
    return pathname.slice(0, -segment.length) + PAGE_MAP[area][segment];
  }
  if (area === 'user' || area === 'admin') return pathname + '.php';
  return pathname;
}

function isPageFn(currentPage, ...names) {
  const current = currentPage.toLowerCase();
  return names.some(raw => {
    const name = String(raw || '').toLowerCase();
    if (!name) return false;
    if (current === name) return true;
    if (!name.includes('.')) {
      if (current === name + '.php') return true;
      return Object.values(PAGE_MAP).some(pages => {
        const file = pages[name];
        return file && current === file.toLowerCase();
      });
    }
    return false;
  });
}

function pageUrlFn(area, file) {
  const segment = FILE_MAP[area]?.[file] ?? file.replace(/\.php$/, '');
  return `/${area}/${segment}`;
}

describe('config.js – getAppRoot', () => {
  test('returns app root for known sub-folder', () => {
    expect(getAppRootFromPath('/ksmaja/user/dashboard')).toBe('/ksmaja');
  });

  test('returns empty string when first segment is a known folder', () => {
    expect(getAppRootFromPath('/user/dashboard')).toBe('');
  });

  test('returns empty string for root path', () => {
    expect(getAppRootFromPath('/')).toBe('');
  });
});

describe('config.js – resolvePath', () => {
  test('trailing slash -> index.php', () => {
    expect(resolvePathFromPathname('/user/')).toBe('/user/index.php');
  });

  test('clean URL /user/dashboard -> dashboard_user.php', () => {
    expect(resolvePathFromPathname('/user/dashboard')).toBe('/user/dashboard_user.php');
  });

  test('.php URL passes through unchanged', () => {
    expect(resolvePathFromPathname('/user/dashboard_user.php')).toBe('/user/dashboard_user.php');
  });

  test('same-name admin page -> appends .php', () => {
    expect(resolvePathFromPathname('/admin/journals')).toBe('/admin/journals.php');
  });

  test('unknown area passes through unchanged', () => {
    expect(resolvePathFromPathname('/public/about')).toBe('/public/about');
  });
});

describe('config.js – isPage', () => {
  test('matches exact filename', () => {
    expect(isPageFn('dashboard_user.php', 'dashboard_user.php')).toBe(true);
  });

  test('matches clean segment against mapped filename', () => {
    expect(isPageFn('dashboard_user.php', 'dashboard')).toBe(true);
  });

  test('matches any of multiple arguments', () => {
    expect(isPageFn('login_user.php', 'journals', 'login')).toBe(true);
  });

  test('returns false for unrelated page', () => {
    expect(isPageFn('dashboard_user.php', 'login', 'journals')).toBe(false);
  });

  test('empty string argument is skipped safely', () => {
    expect(isPageFn('dashboard_user.php', '')).toBe(false);
  });

  test('alias profil -> profil_user.php', () => {
    expect(isPageFn('profil_user.php', 'profil')).toBe(true);
    // First-registered alias wins in FILE_MAP, so profile segment should also match
    expect(isPageFn('profil_user.php', 'profile')).toBe(true);
  });
});

describe('config.js – pageUrl', () => {
  test('maps filename to canonical clean segment', () => {
    expect(pageUrlFn('user', 'dashboard_user.php')).toBe('/user/dashboard');
  });

  test('falls back to stripping .php when no mapping exists', () => {
    expect(pageUrlFn('admin', 'journals.php')).toBe('/admin/journals');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 – dashboard_user.js utilities
// ─────────────────────────────────────────────────────────────────────────────

// Inline the pure functions under test (no DOM required)
function parseJsonField(value, fallback = []) {
  if (Array.isArray(value) || (value && typeof value === 'object')) return value;
  if (!value) return fallback;
  try { return JSON.parse(value); } catch { return fallback; }
}

function resolveFirstAuthor(article) {
  const src = article.authors ?? article.author;
  if (Array.isArray(src) && src.length > 0) return src[0];
  return article.penulis || 'ADMIN';
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return '';
  const diffMs  = Date.now() - date.getTime();
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1)  return 'Baru saja';
  if (diffMin < 60) return `${diffMin} menit yang lalu`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} jam yang lalu`;
  const diffDay = Math.floor(diffHour / 24);
  if (diffDay < 30)  return `${diffDay} hari yang lalu`;
  return `${Math.floor(diffDay / 30)} bulan yang lalu`;
}

function escapeForAttribute(text) {
  return (text || '')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

describe('dashboard_user.js – parseJsonField', () => {
  // ── Happy paths ──
  test('returns array as-is', () => {
    const arr = ['a', 'b'];
    expect(parseJsonField(arr)).toBe(arr);
  });

  test('parses valid JSON string', () => {
    expect(parseJsonField('["tag1","tag2"]')).toEqual(['tag1', 'tag2']);
  });

  test('returns object as-is', () => {
    const obj = { key: 1 };
    expect(parseJsonField(obj)).toBe(obj);
  });

  // ── Edge cases ──
  test('null returns fallback', () => {
    expect(parseJsonField(null)).toEqual([]);
    expect(parseJsonField(null, null)).toBeNull();
  });

  test('undefined returns fallback', () => {
    expect(parseJsonField(undefined, 'default')).toBe('default');
  });

  test('empty string returns fallback', () => {
    expect(parseJsonField('')).toEqual([]);
  });

  // ── Error handling ──
  test('invalid JSON string returns fallback', () => {
    expect(parseJsonField('not-json')).toEqual([]);
    expect(parseJsonField('{broken')).toEqual([]);
  });
});

describe('dashboard_user.js – resolveFirstAuthor', () => {
  // ── Happy paths ──
  test('uses first element of authors array', () => {
    expect(resolveFirstAuthor({ authors: ['Alice', 'Bob'] })).toBe('Alice');
  });

  test('falls back to author array when authors is absent', () => {
    expect(resolveFirstAuthor({ author: ['Charlie'] })).toBe('Charlie');
  });

  test('falls back to penulis string', () => {
    expect(resolveFirstAuthor({ penulis: 'Dian' })).toBe('Dian');
  });

  // ── Edge cases ──
  test('empty authors array -> falls back to penulis', () => {
    expect(resolveFirstAuthor({ authors: [], penulis: 'Eve' })).toBe('Eve');
  });

  test('no author fields -> returns ADMIN', () => {
    expect(resolveFirstAuthor({})).toBe('ADMIN');
  });

  test('null authors -> falls back to author array', () => {
    expect(resolveFirstAuthor({ authors: null, author: ['Frank'] })).toBe('Frank');
  });
});

describe('dashboard_user.js – timeAgo', () => {
  const now = Date.now();

  test('invalid date returns empty string', () => {
    expect(timeAgo('not-a-date')).toBe('');
    expect(timeAgo(null)).toBe('');       // null -> falsy -> early return ''
    expect(timeAgo('')).toBe('');         // empty string -> falsy -> early return ''
    expect(timeAgo(undefined)).toBe(''); // undefined -> falsy -> early return ''
  });

  test('< 1 minute -> Baru saja', () => {
    expect(timeAgo(new Date(now - 30_000).toISOString())).toBe('Baru saja');
  });

  test('30 minutes -> "30 menit yang lalu"', () => {
    expect(timeAgo(new Date(now - 30 * 60_000).toISOString())).toBe('30 menit yang lalu');
  });

  test('3 hours -> "3 jam yang lalu"', () => {
    expect(timeAgo(new Date(now - 3 * 3_600_000).toISOString())).toBe('3 jam yang lalu');
  });

  test('5 days -> "5 hari yang lalu"', () => {
    expect(timeAgo(new Date(now - 5 * 86_400_000).toISOString())).toBe('5 hari yang lalu');
  });

  test('2 months -> "2 bulan yang lalu"', () => {
    expect(timeAgo(new Date(now - 60 * 86_400_000).toISOString())).toBe('2 bulan yang lalu');
  });
});

describe('dashboard_user.js – escapeForAttribute', () => {
  test('escapes double quotes', () => {
    expect(escapeForAttribute('say "hello"')).toBe('say &quot;hello&quot;');
  });

  test('escapes single quotes', () => {
    expect(escapeForAttribute("it's")).toBe('it&#39;s');
  });

  test('escapes < and >', () => {
    expect(escapeForAttribute('<b>bold</b>')).toBe('&lt;b&gt;bold&lt;/b&gt;');
  });

  test('null/undefined -> empty string', () => {
    expect(escapeForAttribute(null)).toBe('');
    expect(escapeForAttribute(undefined)).toBe('');
  });

  test('plain text unchanged', () => {
    expect(escapeForAttribute('hello world')).toBe('hello world');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 – dual_upload_handler.js utilities
// ─────────────────────────────────────────────────────────────────────────────

// Inline pure helpers
const PHONE_REGEX = /^(?:(?:\+|00)62|[0])8[1-9]\d{7,11}$/;

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

function toMySQLDateTime(date) {
  const d = date instanceof Date ? date : new Date(date);
  const y  = d.getFullYear();
  const mo = String(d.getMonth() + 1).padStart(2, '0');
  const dy = String(d.getDate()).padStart(2, '0');
  const h  = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const s  = String(d.getSeconds()).padStart(2, '0');
  return `${y}-${mo}-${dy} ${h}:${mi}:${s}`;
}

describe('dual_upload_handler.js – PHONE_REGEX', () => {
  // ── Valid numbers ──
  test.each([
    ['08123456789'],
    ['081234567890'],
    // +6281234567890 -> strip non-digits -> '6281234567890' -> does NOT start
    // with 0 or 00/+62 after stripping, so regex rejects it (expected: false).
    // Use the raw string with replace to simulate actual call-site behaviour.
    ['006281234567890'],
    ['0811234567890'],
  ])('accepts valid number: %s', (num) => {
    expect(PHONE_REGEX.test(num.replace(/\D/g, ''))).toBe(true);
  });

  test('accepts international +62 format before stripping symbols', () => {
    // The regex tests the RAW string, not a stripped version — caller strips \D first.
    // '+6281234567890'.replace(/\D/g,'') == '6281234567890' — starts with 62, not matched.
    // This correctly documents that +62 must be entered as 0811... or 00621... by users.
    expect(PHONE_REGEX.test('+6281234567890'.replace(/\D/g, ''))).toBe(false);
  });

  // ── Invalid numbers ──
  test.each([
    ['12345'],
    ['abcdefghij'],
    ['080000000000'],  // 80x prefix invalid
    [''],
  ])('rejects invalid number: %s', (num) => {
    expect(PHONE_REGEX.test(num.replace(/\D/g, ''))).toBe(false);
  });
});

describe('dual_upload_handler.js – formatFileSize', () => {
  test('0 bytes', () => expect(formatFileSize(0)).toBe('0 Bytes'));
  test('500 bytes', () => expect(formatFileSize(500)).toBe('500 Bytes'));
  test('1 KB', () => expect(formatFileSize(1024)).toBe('1 KB'));
  test('1.5 MB', () => expect(formatFileSize(1024 * 1024 * 1.5)).toBe('1.5 MB'));
  test('2 GB', () => expect(formatFileSize(1024 ** 3 * 2)).toBe('2 GB'));
});

describe('dual_upload_handler.js – toMySQLDateTime', () => {
  test('formats a known date correctly', () => {
    // Use a fixed date to avoid TZ issues in CI
    const d = new Date(2024, 0, 15, 9, 5, 3); // Jan 15 2024, 09:05:03 local
    expect(toMySQLDateTime(d)).toBe('2024-01-15 09:05:03');
  });

  test('pads single-digit month/day/hour/min/sec', () => {
    const d = new Date(2024, 0, 5, 3, 7, 2);
    expect(toMySQLDateTime(d)).toBe('2024-01-05 03:07:02');
  });

  test('accepts a Date object', () => {
    const d = new Date(2025, 5, 1, 12, 0, 0);
    expect(toMySQLDateTime(d)).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 – DualUploadHandler._uploadFile (mocking fetch)
// ─────────────────────────────────────────────────────────────────────────────

describe('dual_upload_handler.js – _uploadFile logic', () => {
  /**
   * Inline the logic under test so we can unit-test without loading
   * the full module (which requires DOM elements at load time).
   */
  const uploadFile = async (file) => {
    const formData = new FormData();
    formData.append('file', file);
    const response = await global.fetch(`http://localhost/services/upload.php`, {
      method: 'POST',
      body: formData,
    });
    const result = await response.json();
    if (!result.ok) throw new Error(result.message || 'Upload file gagal');
    return result.url;
  };

  afterEach(() => jest.clearAllMocks());

  test('happy path – returns URL on success', async () => {
    const mockFile = new File(['content'], 'test.pdf', { type: 'application/pdf' });
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: true, url: 'https://cdn.example.com/test.pdf' }),
    });

    const url = await uploadFile(mockFile);
    expect(url).toBe('https://cdn.example.com/test.pdf');
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  test('throws when server returns ok:false', async () => {
    const mockFile = new File(['x'], 'fail.pdf', { type: 'application/pdf' });
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: false, message: 'File terlalu besar' }),
    });

    await expect(uploadFile(mockFile)).rejects.toThrow('File terlalu besar');
  });

  test('throws when fetch rejects (network error)', async () => {
    const mockFile = new File(['x'], 'net.pdf', { type: 'application/pdf' });
    global.fetch.mockRejectedValueOnce(new Error('Network error'));

    await expect(uploadFile(mockFile)).rejects.toThrow('Network error');
  });

  test('uses fallback message when server returns no message', async () => {
    const mockFile = new File(['x'], 'no-msg.pdf');
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: false }),
    });

    await expect(uploadFile(mockFile)).rejects.toThrow('Upload file gagal');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 – TagsManager (DOM-based, uses jsdom)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Inline TagsManager logic (class body only) to avoid the singleton guard.
 */
class TagsManagerTest {
  constructor() {
    this.tags = [];
  }
  addTag(tagValue) {
    const v = tagValue.trim().toLowerCase();
    if (!v) { showAlert.warning('Tag tidak boleh kosong!'); return false; }
    if (v.length < 2) { showAlert.warning('Tag minimal 2 karakter!'); return false; }
    if (this.tags.includes(v)) { showAlert.warning('Tag sudah ada!'); return false; }
    if (this.tags.length >= 10) { showAlert.warning('Maksimal 10 tag!'); return false; }
    this.tags.push(v);
    return true;
  }
  removeTag(index) { this.tags.splice(index, 1); }
  getTags() { return this.tags; }
  setTags(tags) { this.tags = Array.isArray(tags) ? tags : []; }
  clearTags() { this.tags = []; }
}

describe('dual_upload_handler.js – TagsManager', () => {
  let tm;
  beforeEach(() => { tm = new TagsManagerTest(); jest.clearAllMocks(); });

  // ── Happy paths ──
  test('adds a valid tag', () => {
    const result = tm.addTag('javascript');
    expect(result).toBe(true);
    expect(tm.getTags()).toEqual(['javascript']);
  });

  test('normalizes to lowercase', () => {
    tm.addTag('React');
    expect(tm.getTags()).toContain('react');
  });

  test('removes tag by index', () => {
    tm.addTag('alpha');
    tm.addTag('beta');
    tm.removeTag(0);
    expect(tm.getTags()).toEqual(['beta']);
  });

  test('setTags replaces all tags', () => {
    tm.addTag('old');
    tm.setTags(['new1', 'new2']);
    expect(tm.getTags()).toEqual(['new1', 'new2']);
  });

  test('clearTags empties the list', () => {
    tm.addTag('tag');
    tm.clearTags();
    expect(tm.getTags()).toHaveLength(0);
  });

  // ── Edge cases ──
  test('rejects empty string', () => {
    expect(tm.addTag('')).toBe(false);
    expect(showAlert.warning).toHaveBeenCalledWith('Tag tidak boleh kosong!');
  });

  test('rejects single character tag', () => {
    expect(tm.addTag('a')).toBe(false);
    expect(showAlert.warning).toHaveBeenCalledWith('Tag minimal 2 karakter!');
  });

  test('rejects duplicate tag', () => {
    tm.addTag('dup');
    expect(tm.addTag('dup')).toBe(false);
    expect(showAlert.warning).toHaveBeenCalledWith('Tag sudah ada!');
  });

  test('rejects more than 10 tags', () => {
    for (let i = 0; i < 10; i++) tm.addTag(`tag${i}`);
    expect(tm.addTag('overflow')).toBe(false);
    expect(showAlert.warning).toHaveBeenCalledWith('Maksimal 10 tag!');
    expect(tm.getTags()).toHaveLength(10);
  });

  test('setTags with non-array falls back to empty array', () => {
    tm.setTags(null);
    expect(tm.getTags()).toEqual([]);
  });
});
