import { CONFIG } from "../config.js";

/**
 * Login Handler - Handle user authentication
 */
class LoginHandler {
  constructor() {
    this.alertBox = document.getElementById("alertBox");
    this.loginForm = document.getElementById("loginForm");
    this.loginButton = document.querySelector(".btn-login");
    this.passwordInput = document.getElementById("loginPassword");
    this.togglePassword = document.getElementById("togglePassword");

    this.init();
  }

  init() {
    // Initialize feather icons
    if (typeof feather !== "undefined") {
      feather.replace();
    }

    // Check if already logged in
    this.checkExistingSession();

    // Setup event listeners
    this.setupPasswordToggle();
    this.setupFormSubmit();
    this.setupSocialLogin();
    this.setupForgotPassword();
    this.autoFillEmail();

    console.log("LoginHandler initialized");
  }

  /**
   * Setup password visibility toggle
   */
  setupPasswordToggle() {
    if (!this.togglePassword || !this.passwordInput) return;

    this.togglePassword.addEventListener("click", () => {
      const type =
        this.passwordInput.getAttribute("type") === "password"
          ? "text"
          : "password";
      this.passwordInput.setAttribute("type", type);

      const icon = type === "password" ? "eye" : "eye-off";
      this.togglePassword.innerHTML = `<i data-feather="${icon}"></i>`;

      if (typeof feather !== "undefined") {
        feather.replace();
      }
    });
  }

  /**
   * Setup form submission
   */
  setupFormSubmit() {
    if (!this.loginForm) return;

    this.loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      await this.handleLogin();
    });
  }

  /**
   * Handle login process
   */
  async handleLogin() {
    const email = document.getElementById("loginEmail").value.trim();
    const password = document.getElementById("loginPassword").value;
    const rememberMe = document.getElementById("rememberMe").checked;

    // Validate inputs
    if (!this.validateInputs(email, password)) {
      return;
    }

    // Show loading state
    this.setLoadingState(true);

    try {
      // Call auth API
      const result = await this.authenticateUser(email, password);

      if (result.ok && result.user) {
        this.handleLoginSuccess(email, rememberMe, result);
      } else {
        this.showAlert(result.message || "Email atau password salah!", "error");
      }
    } catch (error) {
      console.error("Login error:", error);
      this.showAlert("Terjadi kesalahan server. Coba lagi nanti.", "error");
    } finally {
      this.setLoadingState(false);
    }
  }

  /**
   * Validate login inputs
   */
  validateInputs(email, password) {
    if (!email || !password) {
      this.showAlert("Email dan password harus diisi!", "error");
      return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      this.showAlert("Format email tidak valid!", "error");
      return false;
    }

    return true;
  }

  /**
   * Authenticate user via API
   */
  async authenticateUser(email, password) {
    const response = await fetch(`${CONFIG.API_BASE}/auth_login.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error ${response.status}`);
    }

    return await response.json();
  }

  /**
   * Handle successful login
   */
  handleLoginSuccess(email, rememberMe, result) {
    this.showAlert("Login berhasil! Mengalihkan...", "success");

    // Save remember me preferences
    if (rememberMe && result.token) {
      localStorage.setItem("authToken", result.token);
      localStorage.setItem("userEmail", email);
      localStorage.setItem("rememberMe", "true");
    } else {
      localStorage.removeItem("authToken");
      localStorage.removeItem("userEmail");
      localStorage.removeItem("rememberMe");
    }

    // Save session
    sessionStorage.setItem("userLoggedIn", "true");
    sessionStorage.setItem("userEmail", email);
    sessionStorage.setItem("userType", result.user.role || "user");
    sessionStorage.setItem("userName", result.user.name || email);

    // Redirect to dashboard
    setTimeout(() => {
      window.location.href = "./dashboard_user.html";
    }, 1500);
  }

  /**
   * Setup social login handlers
   */
  setupSocialLogin() {
    const googleLogin = document.getElementById("googleLogin");
    const facebookLogin = document.getElementById("facebookLogin");

    if (googleLogin) {
      googleLogin.addEventListener("click", () => {
        this.showAlert("Fitur login Google sedang dalam pengembangan", "error");
      });
    }

    if (facebookLogin) {
      facebookLogin.addEventListener("click", () => {
        this.showAlert(
          "Fitur login Facebook sedang dalam pengembangan",
          "error",
        );
      });
    }
  }

  /**
   * Setup forgot password handler
   */
  setupForgotPassword() {
    const forgotPassword = document.querySelector(".forgot-password");

    if (forgotPassword) {
      forgotPassword.addEventListener("click", (e) => {
        e.preventDefault();
        this.showAlert(
          "Link reset password akan dikirim ke email Anda",
          "success",
        );
      });
    }
  }

  /**
   * Check if user is already logged in
   */
  async checkExistingSession() {
    // Check session first
    if (sessionStorage.getItem("userLoggedIn") === "true") {
      window.location.href = "./dashboard_user.html";
      return;
    }

    // Check auth token (remember me)
    const authToken = localStorage.getItem("authToken");
    if (authToken) {
      try {
        const response = await fetch(`${CONFIG.API_BASE}/auth_me.php`, {
          headers: { Authorization: `Bearer ${authToken}` },
        });
        const result = await response.json();

        if (result.ok && result.user) {
          // Token valid, auto redirect
          sessionStorage.setItem("userLoggedIn", "true");
          sessionStorage.setItem("userEmail", result.user.email);
          sessionStorage.setItem("userType", result.user.role);
          window.location.href = "./dashboard_user.html";
        }
      } catch (err) {
        console.error("Token validation error:", err);
        // Clear invalid token
        localStorage.removeItem("authToken");
      }
    }
  }

  /**
   * Auto-fill email if remember me was checked
   */
  autoFillEmail() {
    if (localStorage.getItem("rememberMe") === "true") {
      const savedEmail = localStorage.getItem("userEmail");
      const emailInput = document.getElementById("loginEmail");
      const rememberCheckbox = document.getElementById("rememberMe");

      if (savedEmail && emailInput) {
        emailInput.value = savedEmail;
      }

      if (rememberCheckbox) {
        rememberCheckbox.checked = true;
      }
    }
  }

  /**
   * Set loading state
   */
  setLoadingState(isLoading) {
    if (!this.loginButton) return;

    if (isLoading) {
      this.loginButton.classList.add("loading-state");
      this.loginButton.textContent = "Loading...";
      this.loginButton.disabled = true;
    } else {
      this.loginButton.classList.remove("loading-state");
      this.loginButton.textContent = "LOGIN";
      this.loginButton.disabled = false;
    }
  }

  /**
   * Show alert message
   */
  showAlert(message, type = "error") {
    if (!this.alertBox) return;

    this.alertBox.textContent = message;
    this.alertBox.className = `alert alert-${type}`;
    this.alertBox.style.display = "block";

    setTimeout(() => {
      this.alertBox.style.display = "none";
    }, 5000);
  }
}

// Auto-initialize
document.addEventListener("DOMContentLoaded", () => {
  new LoginHandler();
});

console.log("login-handler.js loaded");
