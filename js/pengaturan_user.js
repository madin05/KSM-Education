// ===== PENGATURAN =====
function applyNotifPrefsToUI(prefs) {
  if (!prefs) return;
  const newArticle = document.getElementById("toggleNewArticle");
  const uploadStatus = document.getElementById("toggleUploadStatus");
  const promo = document.getElementById("togglePromo");
  if (newArticle && typeof prefs.notification_new_article === "boolean") newArticle.checked = prefs.notification_new_article;
  if (uploadStatus && typeof prefs.notification_upload_status === "boolean") uploadStatus.checked = prefs.notification_upload_status;
  if (promo && typeof prefs.notification_promo === "boolean") promo.checked = prefs.notification_promo;
}

async function getSettingsAuthHeaders(includeJson = false) {
  const headers = includeJson ? { "Content-Type": "application/json" } : {};
  if (window.TokenManager && window.TokenManager.hasTokens()) {
    const token = await window.TokenManager.getValidToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

async function loadNotifPrefsFromServer() {
  try {
    const response = await fetch(`${window.APP_CONFIG.apiBase}/preferences.php`, {
      credentials: "include",
      headers: await getSettingsAuthHeaders(),
    });
    const result = await response.json();
    if (result.ok) {
      applyNotifPrefsToUI(result.preferences);
      if (result.preferences?.theme === "dark" || result.preferences?.theme === "light") {
        applyTheme(result.preferences.theme);
        const toggle = document.getElementById("toggleDarkMode");
        if (toggle) toggle.checked = result.preferences.theme === "dark";
        try {
          localStorage.setItem(THEME_KEY, result.preferences.theme);
        } catch (e) {
          /* localStorage hanya cache; database tetap source of truth */
        }
      }
    }
  } catch (e) {
    console.warn("Gagal memuat preferensi dari server:", e);
  }
}

function setupPasswordForm() {
  const form = document.getElementById("passwordForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const oldPassword = document.getElementById("inputPasswordLama").value;
    const newPassword = document.getElementById("inputPasswordBaru").value;
    const confirmPassword = document.getElementById("inputPasswordKonfirmasi").value;

    if (newPassword.length < 8) {
      showToast("Password baru minimal 8 karakter.", "warning", "VALIDASI GAGAL");
      return;
    }

    if (newPassword !== confirmPassword) {
      showToast("Konfirmasi password tidak cocok.", "warning", "VALIDASI GAGAL");
      return;
    }

    const submitBtn = form.querySelector(".btn-save-profile");
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Memproses...';
    feather.replace();

    try {
      const authHeaders = { "Content-Type": "application/json" };
      if (window.TokenManager && window.TokenManager.hasTokens()) {
        const token = await window.TokenManager.getValidToken();
        if (token) authHeaders["Authorization"] = `Bearer ${token}`;
      }

      const response = await fetch(`${window.APP_CONFIG.apiBase}/change_password.php`, {
        method: "POST",
        credentials: "include",
        headers: authHeaders,
        body: JSON.stringify({
          old_password: oldPassword,
          new_password: newPassword,
        }),
      });

      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Gagal mengubah password.");
      }

      showToast("Password berhasil diubah.", "success", "BERHASIL");
      form.reset();
    } catch (error) {
      console.error("Change password error:", error);
      showToast(`Gagal mengubah password: ${error.message}`, "error", "GAGAL");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      feather.replace();
    }
  });
}

function setupNotifPrefs() {
  const btn = document.getElementById("btnSaveNotif");
  if (!btn) return;

  loadNotifPrefsFromServer();

  btn.addEventListener("click", async () => {
    const prefs = {
      notification_new_article: document.getElementById("toggleNewArticle")?.checked ?? true,
      notification_upload_status: document.getElementById("toggleUploadStatus")?.checked ?? true,
      notification_promo: document.getElementById("togglePromo")?.checked ?? false,
    };

    try {
      const response = await fetch(`${window.APP_CONFIG.apiBase}/preferences.php`, {
        method: "PUT",
        credentials: "include",
        headers: await getSettingsAuthHeaders(true),
        body: JSON.stringify({ preferences: prefs }),
      });
      const result = await response.json();
      if (!result.ok) throw new Error(result.message || "Gagal menyimpan preferensi.");
      applyNotifPrefsToUI(result.preferences);
      showToast("Preferensi notifikasi tersimpan.", "success", "TERSIMPAN");
    } catch (e) {
      showToast(`Gagal menyimpan preferensi: ${e.message}`, "error", "ERROR");
    }
  });
}

function setupDeleteAccount() {
  const btn = document.getElementById("btnDeleteAccount");
  if (!btn) return;

  btn.addEventListener("click", async () => {
    const confirmed = await showAlert.confirm(
      "Tindakan ini permanen dan tidak dapat dibatalkan. Yakin ingin menghapus akun Anda?",
      "Hapus Akun Saya",
    );
    if (!confirmed) return;

    try {
      const authHeaders = await getSettingsAuthHeaders(true);
      const password = window.prompt("Masukkan password lama untuk mengonfirmasi penghapusan akun:");
      if (!password) return;

      const response = await fetch(`${window.APP_CONFIG.apiBase}/delete_account.php`, {
        method: "POST",
        credentials: "include",
        headers: authHeaders,
        body: JSON.stringify({ old_password: password }),
      });
      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Gagal menghapus akun.");
      }

      sessionStorage.clear();
      if (window.TokenManager) window.TokenManager.clearTokens();
      showToast("Akun Anda telah dihapus.", "success", "SELESAI");
      setTimeout(() => {
        window.location.href = "./login";
      }, 1200);
    } catch (error) {
      console.error("Delete account error:", error);
      showToast(`Gagal menghapus akun: ${error.message}`, "error", "GAGAL");
    }
  });
}

// ===== DARK MODE =====
// Database adalah source of truth untuk tema. localStorage key "ksm_theme"
// hanya cache anti-flash yang dibaca header sebelum request API selesai.
const THEME_KEY = "ksm_theme";

function applyTheme(theme) {
  if (theme === "dark") {
    document.documentElement.setAttribute("data-theme", "dark");
  } else {
    document.documentElement.removeAttribute("data-theme");
  }
}

function setupDarkModeToggle() {
  const toggle = document.getElementById("toggleDarkMode");
  if (!toggle) return;

  let saved = "light";
  try {
    saved = localStorage.getItem(THEME_KEY) || "light";
  } catch (e) {
    /* localStorage tidak tersedia — default light */
  }

  toggle.checked = saved === "dark";

  toggle.addEventListener("change", async () => {
    const theme = toggle.checked ? "dark" : "light";
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
      console.warn("Gagal menyimpan preferensi tema:", e);
    }
    applyTheme(theme);
    try {
      const response = await fetch(`${window.APP_CONFIG.apiBase}/preferences.php`, {
        method: "PUT",
        credentials: "include",
        headers: await getSettingsAuthHeaders(true),
        body: JSON.stringify({ preferences: { theme } }),
      });
      const result = await response.json();
      if (!result.ok) throw new Error(result.message || "Gagal menyimpan tema.");
      showToast(theme === "dark" ? "Mode gelap diaktifkan." : "Mode gelap dinonaktifkan.", "success", "TAMPILAN");
    } catch (e) {
      showToast(`Tema diterapkan sebagai cache, tetapi gagal disimpan: ${e.message}`, "warning", "TAMPILAN");
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  setupPasswordForm();
  setupNotifPrefs();
  setupDeleteAccount();
  setupDarkModeToggle();
});