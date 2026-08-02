(function () {
  const alertBox = document.getElementById("alertBox");

  function show(message, type) {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = "block";
  }

  async function post(endpoint, payload) {
    const response = await fetch(`${window.APP_CONFIG.SERVICES}/${endpoint}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || "Permintaan gagal.");
    return result;
  }

  const forgotForm = document.getElementById("forgotPasswordForm");
  if (forgotForm) {
    forgotForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const result = await post("forgot_password.php", {
          email: document.getElementById("resetEmail").value.trim(),
        });
        show(result.message, "success");
        forgotForm.reset();
      } catch (error) {
        show(error.message, "error");
      }
    });
  }

  const resetForm = document.getElementById("resetPasswordForm");
  if (resetForm) {
    const token = new URLSearchParams(window.location.search).get("token") || "";
    if (!token) {
      show("Token reset tidak tersedia.", "error");
      resetForm.querySelector("button").disabled = true;
    }
    resetForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const result = await post("reset_password.php", {
          token,
          password: document.getElementById("newPassword").value,
          password_confirmation: document.getElementById("confirmPassword").value,
        });
        show(result.message, "success");
        resetForm.reset();
        setTimeout(() => { window.location.href = "login"; }, 1500);
      } catch (error) {
        show(error.message, "error");
      }
    });
  }
})();