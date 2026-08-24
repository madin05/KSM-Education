// ===== FORM KONTAK =====
async function autoFillContactUser() {
  try {
    const nameInput = document.getElementById("contactNama");
    const emailInput = document.getElementById("contactEmail");
    if (!nameInput || !emailInput) return;

    const authHeaders = {};
    if (window.TokenManager && window.TokenManager.hasTokens()) {
      const token = await window.TokenManager.getValidToken();
      if (token) authHeaders["Authorization"] = `Bearer ${token}`;
    }

    const response = await fetch(`${window.APP_CONFIG.apiBase}/auth_me.php`, {
      credentials: "include",
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

function setupContactForm() {
  const form = document.getElementById("contactForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = document.getElementById("contactNama").value.trim();
    const email = document.getElementById("contactEmail").value.trim();
    const subject = document.getElementById("contactSubjek").value.trim();
    const message = document.getElementById("contactPesan").value.trim();
    const website = form.querySelector('[name="website"]')?.value || "";

    if (!name || !email || !subject || !message) {
      showToast("Semua field wajib diisi.", "warning", "VALIDASI GAGAL");
      return;
    }

    const submitBtn = form.querySelector(".btn-save-profile");
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Mengirim...';
    feather.replace();

    try {
      const headers = { "Content-Type": "application/json" };
      if (window.TokenManager && window.TokenManager.hasTokens()) {
        const token = await window.TokenManager.getValidToken();
        if (token) headers["Authorization"] = `Bearer ${token}`;
      }

      const response = await fetch(`${window.APP_CONFIG.apiBase}/send_contact.php`, {
        method: "POST",
        credentials: "include",
        headers,
        body: JSON.stringify({ name, email, subject, message, website }),
      });

      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.ok) throw new Error(result.message || "Gagal mengirim pesan.");

      showToast("Pesan Anda berhasil terkirim. Kami akan segera membalas.", "success", "TERKIRIM");
      form.reset();
      autoFillContactUser();
    } catch (error) {
      showToast(error.message || "Pesan belum dapat dikirim. Coba lagi nanti.", "error", "GAGAL");
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      feather.replace();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  autoFillContactUser();
  setupContactForm();
});