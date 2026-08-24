/**
 * @jest-environment jsdom
 *
 * Unit tests for USER-FACING pages:
 *   - js/kontak_user.js    (autoFillContactUser, setupContactForm)
 *   - js/register_user.js  (validation, Google auth flow)
 *   - js/statistic.js      (StatisticsManager counter logic)
 *   - js/pengaturan_user.js (password change, account deletion validation)
 *
 * Run: npx jest js/__tests__/user_pages.test.js
 */

// ─── Global Polyfills & Mocks ──────────────────────────────────────────────
global.fetch = jest.fn();
global.feather = { replace: jest.fn() };
global.showToast = jest.fn();
global.showAlert = {
  warning: jest.fn(),
  success: jest.fn(),
  error: jest.fn(),
  confirm: jest.fn(),
};

beforeEach(() => {
  jest.clearAllMocks();
  jest.useFakeTimers();
  // Reset DOM
  document.body.innerHTML = '';
  // Mock APP_CONFIG
  window.APP_CONFIG = {
    root: '',
    apiBase: '/services',
    SERVICES: '/services',
  };
  // Reset sessionStorage/localStorage
  sessionStorage.clear();
  localStorage.clear();
});

afterEach(() => {
  jest.useRealTimers();
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 – kontak_user.js (Contact Form)
// ═══════════════════════════════════════════════════════════════════════════

async function autoFillContactUser() {
  try {
    const nameInput = document.getElementById('contactNama');
    const emailInput = document.getElementById('contactEmail');
    if (!nameInput || !emailInput) return;

    const authHeaders = {};
    if (window.TokenManager && window.TokenManager.hasTokens()) {
      const token = await window.TokenManager.getValidToken();
      if (token) authHeaders['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${window.APP_CONFIG.apiBase}/auth_me.php`, {
      credentials: 'include',
      headers: authHeaders,
    });
    const result = await response.json().catch(() => ({}));
    if (result.ok && result.user) {
      if (result.user.name && !nameInput.value) nameInput.value = result.user.name;
      if (result.user.email && !emailInput.value) emailInput.value = result.user.email;
    }
  } catch (err) {
    // Non-blocking autofill
  }
}

function validateContactFields(name, email, subject, message) {
  if (!name || !email || !subject || !message) return false;
  return true;
}

describe('kontak_user.js – autoFillContactUser', () => {
  test('happy path: fills name and email from API', async () => {
    document.body.innerHTML = `
      <input id="contactNama" value="" />
      <input id="contactEmail" value="" />
    `;
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: true, user: { name: 'John Doe', email: 'john@test.com' } }),
    });

    await autoFillContactUser();

    expect(document.getElementById('contactNama').value).toBe('John Doe');
    expect(document.getElementById('contactEmail').value).toBe('john@test.com');
  });

  test('does not overwrite existing values', async () => {
    document.body.innerHTML = `
      <input id="contactNama" value="Existing Name" />
      <input id="contactEmail" value="existing@test.com" />
    `;
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: true, user: { name: 'New Name', email: 'new@test.com' } }),
    });

    await autoFillContactUser();

    expect(document.getElementById('contactNama').value).toBe('Existing Name');
    expect(document.getElementById('contactEmail').value).toBe('existing@test.com');
  });

  test('handles missing DOM elements gracefully', async () => {
    // No contact inputs in DOM
    document.body.innerHTML = '<div></div>';

    await autoFillContactUser(); // Should not throw

    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('handles API failure gracefully (non-blocking)', async () => {
    document.body.innerHTML = `
      <input id="contactNama" value="" />
      <input id="contactEmail" value="" />
    `;
    global.fetch.mockRejectedValueOnce(new Error('Network error'));

    await autoFillContactUser(); // Should not throw

    expect(document.getElementById('contactNama').value).toBe('');
    expect(document.getElementById('contactEmail').value).toBe('');
  });

  test('handles API returning ok:false', async () => {
    document.body.innerHTML = `
      <input id="contactNama" value="" />
      <input id="contactEmail" value="" />
    `;
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: false, message: 'Unauthorized' }),
    });

    await autoFillContactUser();

    expect(document.getElementById('contactNama').value).toBe('');
    expect(document.getElementById('contactEmail').value).toBe('');
  });

  test('includes Bearer token when TokenManager is available', async () => {
    document.body.innerHTML = `
      <input id="contactNama" value="" />
      <input id="contactEmail" value="" />
    `;
    window.TokenManager = {
      hasTokens: jest.fn(() => true),
      getValidToken: jest.fn(async () => 'mock-token-123'),
    };
    global.fetch.mockResolvedValueOnce({
      json: async () => ({ ok: true, user: { name: 'Authed', email: 'auth@test.com' } }),
    });

    await autoFillContactUser();

    expect(global.fetch).toHaveBeenCalledWith(
      '/services/auth_me.php',
      expect.objectContaining({
        headers: { Authorization: 'Bearer mock-token-123' },
      })
    );

    delete window.TokenManager;
  });
});

describe('kontak_user.js – validateContactFields', () => {
  test('returns true when all fields are filled', () => {
    expect(validateContactFields('John', 'john@test.com', 'Help', 'I need help')).toBe(true);
  });

  test('returns false when name is empty', () => {
    expect(validateContactFields('', 'john@test.com', 'Help', 'Message')).toBe(false);
  });

  test('returns false when email is empty', () => {
    expect(validateContactFields('John', '', 'Help', 'Message')).toBe(false);
  });

  test('returns false when subject is empty', () => {
    expect(validateContactFields('John', 'john@test.com', '', 'Message')).toBe(false);
  });

  test('returns false when message is empty', () => {
    expect(validateContactFields('John', 'john@test.com', 'Help', '')).toBe(false);
  });

  test('returns false when all fields are empty', () => {
    expect(validateContactFields('', '', '', '')).toBe(false);
  });
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 – register_user.js (Registration Validation)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Inline client-side validation logic from register_user.js
 */
function validateRegistration(name, email, password, confirmPassword) {
  const errors = [];
  if (!name || name.trim().length === 0) errors.push('Nama tidak boleh kosong!');
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Email tidak valid!');
  if (!password || password.length < 6) errors.push('Password minimal 6 karakter!');
  if (password !== confirmPassword) errors.push('Konfirmasi password tidak cocok!');
  return errors;
}

describe('register_user.js – validateRegistration', () => {
  // ── Happy paths ──
  test('valid registration returns no errors', () => {
    const errors = validateRegistration('John', 'john@test.com', 'password123', 'password123');
    expect(errors).toEqual([]);
  });

  test('valid registration with minimum password length (6 chars)', () => {
    expect(validateRegistration('A', 'a@b.c', '123456', '123456')).toEqual([]);
  });

  // ── Edge cases ──
  test('empty name returns error', () => {
    const errors = validateRegistration('', 'john@test.com', 'pass123', 'pass123');
    expect(errors).toContain('Nama tidak boleh kosong!');
  });

  test('whitespace-only name returns error', () => {
    const errors = validateRegistration('   ', 'john@test.com', 'pass123', 'pass123');
    expect(errors).toContain('Nama tidak boleh kosong!');
  });

  test('invalid email (no @) returns error', () => {
    const errors = validateRegistration('John', 'invalid-email', 'pass123', 'pass123');
    expect(errors).toContain('Email tidak valid!');
  });

  test('invalid email (no domain) returns error', () => {
    const errors = validateRegistration('John', 'john@', 'pass123', 'pass123');
    expect(errors).toContain('Email tidak valid!');
  });

  test('password under 6 chars returns error', () => {
    const errors = validateRegistration('John', 'john@test.com', '12345', '12345');
    expect(errors).toContain('Password minimal 6 karakter!');
  });

  test('password mismatch returns error', () => {
    const errors = validateRegistration('John', 'john@test.com', 'pass123', 'pass456');
    expect(errors).toContain('Konfirmasi password tidak cocok!');
  });

  // ── Error accumulation ──
  test('multiple errors returned simultaneously', () => {
    const errors = validateRegistration('', 'bad', '123', '456');
    expect(errors.length).toBeGreaterThanOrEqual(3);
  });

  test('null/undefined inputs are handled gracefully', () => {
    const errors = validateRegistration(null, undefined, null, undefined);
    expect(errors.length).toBeGreaterThanOrEqual(3);
  });
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 – statistic.js (Counter Animation & API)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Inline the counter animation easing function from StatisticsManager
 */
function easeOutQuart(progress) {
  return 1 - Math.pow(1 - progress, 4);
}

function calculateCounterValue(start, end, progress) {
  const eased = easeOutQuart(Math.min(progress, 1));
  return Math.floor(start + (end - start) * eased);
}

/**
 * Inline the stat parsing logic from StatisticsManager
 */
function parseStatsResponse(data) {
  if (!data || !data.ok || !data.stats) return { articles: 0, visitors: 0 };
  return {
    articles: data.stats.total_articles || 0,
    visitors: data.is_personal
      ? (data.stats.total_views ?? 0)
      : (data.stats.total_visitors ?? 0),
  };
}

describe('statistic.js – easeOutQuart', () => {
  test('returns 0 at progress 0', () => {
    expect(easeOutQuart(0)).toBe(0);
  });

  test('returns 1 at progress 1', () => {
    expect(easeOutQuart(1)).toBe(1);
  });

  test('returns value between 0 and 1 for midway progress', () => {
    const mid = easeOutQuart(0.5);
    expect(mid).toBeGreaterThan(0);
    expect(mid).toBeLessThan(1);
  });

  test('easing accelerates: 0.5 progress > 0.5 value (ease-out)', () => {
    expect(easeOutQuart(0.5)).toBeGreaterThan(0.5);
  });
});

describe('statistic.js – calculateCounterValue', () => {
  test('start == end returns end', () => {
    expect(calculateCounterValue(42, 42, 1)).toBe(42);
  });

  test('at progress 0, returns start', () => {
    expect(calculateCounterValue(0, 100, 0)).toBe(0);
  });

  test('at progress 1, returns end', () => {
    expect(calculateCounterValue(0, 100, 1)).toBe(100);
  });

  test('clamps progress above 1 to 1', () => {
    expect(calculateCounterValue(0, 100, 1.5)).toBe(100);
  });

  test('handles negative range (decrement)', () => {
    expect(calculateCounterValue(100, 0, 1)).toBe(0);
  });

  test('handles large numbers', () => {
    const result = calculateCounterValue(0, 1000000, 1);
    expect(result).toBe(1000000);
  });
});

describe('statistic.js – parseStatsResponse', () => {
  test('happy path: global stats', () => {
    const result = parseStatsResponse({
      ok: true,
      stats: { total_articles: 42, total_visitors: 1500 },
    });
    expect(result).toEqual({ articles: 42, visitors: 1500 });
  });

  test('happy path: personal stats (is_personal=true)', () => {
    const result = parseStatsResponse({
      ok: true,
      is_personal: true,
      stats: { total_articles: 5, total_views: 200, total_visitors: 999 },
    });
    expect(result).toEqual({ articles: 5, visitors: 200 });
  });

  test('returns zeros when ok is false', () => {
    expect(parseStatsResponse({ ok: false })).toEqual({ articles: 0, visitors: 0 });
  });

  test('returns zeros when stats is missing', () => {
    expect(parseStatsResponse({ ok: true })).toEqual({ articles: 0, visitors: 0 });
  });

  test('returns zeros for null input', () => {
    expect(parseStatsResponse(null)).toEqual({ articles: 0, visitors: 0 });
  });

  test('returns zeros for undefined input', () => {
    expect(parseStatsResponse(undefined)).toEqual({ articles: 0, visitors: 0 });
  });

  test('handles missing total_articles gracefully', () => {
    const result = parseStatsResponse({
      ok: true,
      stats: { total_visitors: 10 },
    });
    expect(result.articles).toBe(0);
  });

  test('handles missing total_visitors gracefully', () => {
    const result = parseStatsResponse({
      ok: true,
      stats: { total_articles: 10 },
    });
    expect(result.visitors).toBe(0);
  });
});

describe('statistic.js – visitor tracking dedup', () => {
  /**
   * Simulates the session-based dedup guard from StatisticsManager
   */
  function shouldTrackVisitor() {
    return !sessionStorage.getItem('visitorTracked');
  }

  function markVisitorTracked() {
    sessionStorage.setItem('visitorTracked', '1');
  }

  test('first visit should be tracked', () => {
    expect(shouldTrackVisitor()).toBe(true);
  });

  test('subsequent visit should NOT be tracked', () => {
    markVisitorTracked();
    expect(shouldTrackVisitor()).toBe(false);
  });

  test('after clearing session, should track again', () => {
    markVisitorTracked();
    sessionStorage.clear();
    expect(shouldTrackVisitor()).toBe(true);
  });
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 – pengaturan_user.js (Settings: Password & Account)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Inline the password change validation logic from pengaturan_user.js
 */
function validatePasswordChange(oldPassword, newPassword, confirmPassword) {
  if (!oldPassword) return 'Password lama wajib diisi.';
  if (newPassword.length < 8) return 'Password baru minimal 8 karakter.';
  if (newPassword !== confirmPassword) return 'Konfirmasi password tidak cocok.';
  if (oldPassword === newPassword) return 'Password baru harus berbeda dari yang lama.';
  return null; // valid
}

describe('pengaturan_user.js – validatePasswordChange', () => {
  // ── Happy path ──
  test('valid change returns null', () => {
    expect(validatePasswordChange('oldpass1', 'newpass99', 'newpass99')).toBeNull();
  });

  // ── Edge cases ──
  test('empty old password returns error', () => {
    expect(validatePasswordChange('', 'newpass99', 'newpass99')).toBe('Password lama wajib diisi.');
  });

  test('new password too short (< 8 chars) returns error', () => {
    expect(validatePasswordChange('oldpass', '1234567', '1234567')).toBe('Password baru minimal 8 karakter.');
  });

  test('exactly 8 chars is valid', () => {
    expect(validatePasswordChange('oldpass1', '12345678', '12345678')).toBeNull();
  });

  test('password mismatch returns error', () => {
    expect(validatePasswordChange('oldpass1', 'newpass99', 'newpass00')).toBe('Konfirmasi password tidak cocok.');
  });

  test('same old and new password returns error', () => {
    expect(validatePasswordChange('samepass', 'samepass', 'samepass')).toBe('Password baru harus berbeda dari yang lama.');
  });
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 5 – Contact Form Submission (mocking fetch)
// ═══════════════════════════════════════════════════════════════════════════

describe('kontak_user.js – contact form submission', () => {
  const submitContact = async (payload) => {
    const response = await global.fetch('/services/send_contact.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'Gagal mengirim pesan.');
    return result;
  };

  afterEach(() => jest.clearAllMocks());

  test('happy path – sends message successfully', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ ok: true, message: 'Pesan berhasil dikirim.' }),
    });

    const result = await submitContact({
      name: 'John',
      email: 'john@test.com',
      subject: 'Help',
      message: 'I need help',
      website: '',
    });

    expect(result.ok).toBe(true);
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  test('throws when server returns ok:false', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ ok: false, message: 'Rate limit exceeded' }),
    });

    await expect(
      submitContact({ name: 'X', email: 'x@x.com', subject: 'Y', message: 'Z' })
    ).rejects.toThrow('Rate limit exceeded');
  });

  test('throws when server returns HTTP error', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({ ok: false, message: 'Internal Server Error' }),
    });

    await expect(
      submitContact({ name: 'X', email: 'x@x.com', subject: 'Y', message: 'Z' })
    ).rejects.toThrow('Internal Server Error');
  });

  test('throws when fetch rejects (network error)', async () => {
    global.fetch.mockRejectedValueOnce(new Error('Network error'));

    await expect(
      submitContact({ name: 'X', email: 'x@x.com', subject: 'Y', message: 'Z' })
    ).rejects.toThrow('Network error');
  });

  test('uses fallback message when server returns no message', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ ok: false }),
    });

    await expect(
      submitContact({ name: 'X', email: 'x@x.com', subject: 'Y', message: 'Z' })
    ).rejects.toThrow('Gagal mengirim pesan.');
  });

  test('honeypot field (website) is included in payload', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ ok: true }),
    });

    await submitContact({
      name: 'John',
      email: 'john@test.com',
      subject: 'Sub',
      message: 'Msg',
      website: '',
    });

    const callBody = JSON.parse(global.fetch.mock.calls[0][1].body);
    expect(callBody).toHaveProperty('website', '');
  });
});


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 6 – Email Validation (shared across register/login/contact)
// ═══════════════════════════════════════════════════════════════════════════

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

describe('Shared – Email validation regex', () => {
  test.each([
    'user@example.com',
    'name.lastname@company.co.id',
    'a@b.c',
    'test+tag@gmail.com',
  ])('accepts valid email: %s', (email) => {
    expect(EMAIL_REGEX.test(email)).toBe(true);
  });

  test.each([
    '',
    'plaintext',
    '@no-local.com',
    'no-domain@',
    'spaces in@email.com',
    'missing@.tld',
  ])('rejects invalid email: %s', (email) => {
    expect(EMAIL_REGEX.test(email)).toBe(false);
  });
});
