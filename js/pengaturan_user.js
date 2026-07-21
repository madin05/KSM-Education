// ===== PENGATURAN =====
// TODO backend: endpoint change_password.php dan delete_account.php
// belum tentu ada — form ini akan menampilkan error yang jelas kalau
// endpoint belum tersedia, bukan gagal diam-diam.
// Preferensi notifikasi masih disimpan di localStorage (simulasi
// frontend) sampai backend punya kolom/tabel untuk ini.

const NOTIF_PREFS_KEY = "ksm_notif_prefs";

function loadNotifPrefs() {
  try {
    const raw = localStorage.getItem(NOTIF_PREFS_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

function applyNotifPrefsToUI() {
  const prefs = loadNotifPrefs();
  if (!prefs) return;
  const newArticle = document.getElementById("toggleNewArticle");
  const uploadStatus = document.getElementById("toggleUploadStatus");
  const promo = document.getElementById("togglePromo");
  if (newArticle && typeof prefs.newArticle === "boolean") newArticle.checked = prefs.newArticle;
  if (uploadStatus && typeof prefs.uploadStatus === "boolean") uploadStatus.checked = prefs.uploadStatus;
  if (promo && typeof prefs.promo === "boolean") promo.checked = prefs.promo;
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
      showToast(
        "Endpoint change_password.php belum tersedia di backend, atau terjadi error: " +
          error.message,
        "error",
        "GAGAL",
      );
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

  applyNotifPrefsToUI();

  btn.addEventListener("click", () => {
    const prefs = {
      newArticle: document.getElementById("toggleNewArticle")?.checked ?? true,
      uploadStatus: document.getElementById("toggleUploadStatus")?.checked ?? true,
      promo: document.getElementById("togglePromo")?.checked ?? false,
    };

    try {
      localStorage.setItem(NOTIF_PREFS_KEY, JSON.stringify(prefs));
      showToast("Preferensi notifikasi disimpan di perangkat ini.", "success", "TERSIMPAN");
    } catch (e) {
      showToast("Gagal menyimpan preferensi.", "error", "ERROR");
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
      const authHeaders = {};
      if (window.TokenManager && window.TokenManager.hasTokens()) {
        const token = await window.TokenManager.getValidToken();
        if (token) authHeaders["Authorization"] = `Bearer ${token}`;
      }

      const response = await fetch(`${window.APP_CONFIG.apiBase}/delete_account.php`, {
        method: "POST",
        credentials: "include",
        headers: authHeaders,
      });
      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Gagal menghapus akun.");
      }

      sessionStorage.clear();
      localStorage.clear();
      showToast("Akun Anda telah dihapus.", "success", "SELESAI");
      setTimeout(() => {
        window.location.href = "./login_user.php";
      }, 1200);
    } catch (error) {
      console.error("Delete account error:", error);
      showToast(
        "Endpoint delete_account.php belum tersedia di backend, atau terjadi error: " +
          error.message,
        "error",
        "GAGAL",
      );
    }
  });
}

// ===== DARK MODE =====
// Preferensi disimpan di localStorage key "ksm_theme" ("dark" / "light").
// Nilai ini juga dibaca oleh script anti-flash di header.php (dijalankan
// paling awal di <head>, sebelum CSS lain), jadi toggle di sini hanya
// perlu update localStorage + atribut data-theme secara langsung —
// tidak perlu reload halaman.
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

  toggle.addEventListener("change", () => {
    const theme = toggle.checked ? "dark" : "light";
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
      console.warn("Gagal menyimpan preferensi tema:", e);
    }
    applyTheme(theme);
    showToast(
      theme === "dark" ? "Mode gelap diaktifkan." : "Mode gelap dinonaktifkan.",
      "success",
      "TAMPILAN",
    );
  });
}

document.addEventListener("DOMContentLoaded", () => {
  setupPasswordForm();
  setupNotifPrefs();
  setupDeleteAccount();
  setupDarkModeToggle();
});