// ===== PROFIL SAYA =====
// Mengambil data user dari auth_me.php (endpoint yang sama dipakai
// updateNavbarAuth() di script.js), lalu menghitung statistik ringan
// (jumlah artikel & total views) dari list_journals.php/list_opinions.php
// dengan filter nama author yang sama seperti di dashboard_user.js.
//
function getAvatarColorSafe(name) {
  if (typeof getAvatarColor === "function") return getAvatarColor(name);
  return "#3b82f6";
}

async function loadProfileData() {
  try {
    const authHeaders = {};
    if (window.TokenManager && window.TokenManager.hasTokens()) {
      const token = await window.TokenManager.getValidToken();
      if (token) authHeaders["Authorization"] = `Bearer ${token}`;
    }

    const response = await fetch(`${window.APP_CONFIG.apiBase}/auth_me.php`, {
      credentials: "include",
      headers: authHeaders,
    });
    const result = await response.json();

    if (!result.ok || !result.user) {
      window.location.href = "./login_user.php";
      return;
    }

    const user = result.user;
    const initial = (user.name || "U").charAt(0).toUpperCase();
    const color = getAvatarColorSafe(user.name);

    document.getElementById("profileAvatar").textContent = initial;
    document.getElementById("profileAvatar").style.background = color;
    document.getElementById("profileName").textContent = user.name || "Tanpa Nama";
    document.getElementById("profileEmail").textContent = user.email || "-";
    document.getElementById("inputNama").value = user.name || "";
    document.getElementById("inputEmail").value = user.email || "";
    document.getElementById("inputBio").value = user.bio || "";
    const avatarInput = document.getElementById("inputAvatarUrl");
    if (avatarInput) avatarInput.value = user.avatar_url || "";

    if (user.avatar_url) {
      document.getElementById("profileAvatar").textContent = "";
      document.getElementById("profileAvatar").style.backgroundImage = `url("${user.avatar_url.replace(/"/g, "%22")}")`;
      document.getElementById("profileAvatar").style.backgroundSize = "cover";
      document.getElementById("profileAvatar").style.backgroundPosition = "center";
    }

    if (user.created_at) {
      const joinDate = new Date(user.created_at).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
      });
      document.querySelector("#profileJoinDate span").textContent = `Bergabung sejak ${joinDate}`;
    }

    await loadProfileStats(user.name);
    feather.replace();
  } catch (error) {
    console.error("Gagal memuat profil:", error);
    showToast("Gagal memuat data profil.", "error", "ERROR");
  }
}

async function loadProfileStats(userName) {
  try {
    const timestamp = Date.now();
    const [journalsRes, opinionsRes] = await Promise.all([
      fetch(`${window.APP_CONFIG.apiBase}/list_journals.php?limit=100&offset=0&t=${timestamp}`, {
        cache: "no-store",
      }),
      fetch(`${window.APP_CONFIG.apiBase}/list_opinions.php?limit=100&offset=0&t=${timestamp}`, {
        cache: "no-store",
      }).catch(() => ({ json: async () => ({ ok: false, results: [] }) })),
    ]);

    const journalsData = await journalsRes.json();
    const opinionsData = await opinionsRes.json();

    const all = [
      ...(journalsData.ok ? journalsData.results : []),
      ...(opinionsData.ok ? opinionsData.results : []),
    ];

    const nameUpper = (userName || "").toUpperCase();
    const mine = all.filter((item) => {
      const authors = item.authors
        ? typeof item.authors === "string"
          ? JSON.parse(item.authors)
          : item.authors
        : item.author_name
          ? [item.author_name]
          : [];
      const author = Array.isArray(authors) ? authors[0] : authors;
      return (author || "").toUpperCase().includes(nameUpper);
    });

    const totalViews = mine.reduce((sum, item) => sum + (item.views || 0), 0);

    document.getElementById("profileArticleCount").textContent = mine.length;
    document.getElementById("profileViewCount").textContent = totalViews;
  } catch (error) {
    console.warn("Gagal memuat statistik profil:", error);
  }

  const tokenEl = document.getElementById("profileTokenCount");
  if (tokenEl && window.KsmTokenWallet) {
    tokenEl.textContent = window.KsmTokenWallet.getBalance();
    window.addEventListener("ksm-token-wallet:updated", () => {
      tokenEl.textContent = window.KsmTokenWallet.getBalance();
    });
  }
}

function setupProfileForm() {
  const form = document.getElementById("profileForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = document.getElementById("inputNama").value.trim();
    const bio = document.getElementById("inputBio").value.trim();
    const avatarUrl = document.getElementById("inputAvatarUrl")?.value.trim() || null;

    if (!name) {
      showToast("Nama tidak boleh kosong.", "warning", "VALIDASI GAGAL");
      return;
    }

    const submitBtn = form.querySelector(".btn-save-profile");
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Menyimpan...';
    feather.replace();

    try {
      const authHeaders = { "Content-Type": "application/json" };
      if (window.TokenManager && window.TokenManager.hasTokens()) {
        const token = await window.TokenManager.getValidToken();
        if (token) authHeaders["Authorization"] = `Bearer ${token}`;
      }

      const response = await fetch(`${window.APP_CONFIG.apiBase}/update_profile.php`, {
        method: "POST",
        credentials: "include",
        headers: authHeaders,
        body: JSON.stringify({ name, bio, avatar_url: avatarUrl }),
      });

      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.message || "Gagal menyimpan perubahan.");
      }

      document.getElementById("profileName").textContent = name;
      if (result.user?.avatar_url) {
        document.getElementById("profileAvatar").textContent = "";
        document.getElementById("profileAvatar").style.backgroundImage = `url("${result.user.avatar_url.replace(/"/g, "%22")}")`;
        document.getElementById("profileAvatar").style.backgroundSize = "cover";
        document.getElementById("profileAvatar").style.backgroundPosition = "center";
      }
      sessionStorage.setItem("userName", name);
      showToast("Perubahan profil berhasil disimpan.", "success", "TERSIMPAN");
    } catch (error) {
      console.error("Update profile error:", error);
      showToast(`Gagal menyimpan profil: ${error.message}`, "error", "GAGAL MENYIMPAN");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      feather.replace();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  loadProfileData();
  setupProfileForm();
});