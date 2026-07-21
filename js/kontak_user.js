// ===== FORM KONTAK =====
// TODO backend: endpoint send_contact.php belum tentu ada. Kalau
// fetch ke endpoint itu gagal (404/network error), form otomatis
// fallback ke mailto: supaya pesan pengguna tidak hilang begitu saja.

function setupContactForm() {
  const form = document.getElementById("contactForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = document.getElementById("contactNama").value.trim();
    const email = document.getElementById("contactEmail").value.trim();
    const subject = document.getElementById("contactSubjek").value.trim();
    const message = document.getElementById("contactPesan").value.trim();

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
      const response = await fetch(`${window.APP_CONFIG.apiBase}/send_contact.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, subject, message }),
      });

      if (!response.ok) throw new Error("Endpoint tidak tersedia");

      const result = await response.json();
      if (!result.ok) throw new Error(result.message || "Gagal mengirim pesan.");

      showToast("Pesan Anda berhasil terkirim. Kami akan segera membalas.", "success", "TERKIRIM");
      form.reset();
    } catch (error) {
      console.warn("send_contact.php belum tersedia, fallback ke mailto:", error);

      const mailtoBody = `Nama: ${name}%0AEmail: ${email}%0A%0A${encodeURIComponent(message)}`;
      const mailtoUrl = `mailto:admin@ksmeducation.id?subject=${encodeURIComponent(subject)}&body=${mailtoBody}`;

      showToast(
        "Server pesan belum aktif — membuka aplikasi email Anda sebagai alternatif.",
        "info",
        "Info",
      );
      window.location.href = mailtoUrl;
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      feather.replace();
    }
  });
}

document.addEventListener("DOMContentLoaded", setupContactForm);