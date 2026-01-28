import { CONFIG } from "../config.js";

/**
 * Admin Login Manager - Handle admin authentication & session
 */
export class AdminLoginManager {
  constructor() {
    this.loginModal = document.getElementById("loginModal");
    this.loginForm = document.getElementById("loginForm");
    this.loginBtn = document.querySelector(".btn-register");
    this.closeModalBtn = document.getElementById("closeLoginModal");
    this.togglePasswordBtn = document.getElementById("togglePassword");
    this.uploadSection = document.querySelector(".upload-section");

    this.isLoggedIn = false;
    this.sessionTimeout = 60; // minutes

    // Check if essential elements exist
    if (!this.loginBtn) {
      console.warn("Login button not found");
      return;
    }

    if (!this.loginModal || !this.loginForm) {
      console.warn("Login modal or form not found");
      return;
    }

    this.init();
  }

  init() {
    this.checkLoginStatus();
    this.setupEventListeners();
    this.autoFillRememberedEmail();
    this.updateUI();

    console.log("AdminLoginManager initialized");
  }

  /**
   * Setup all event listeners
   */
  setupEventListeners() {
    // Login/Logout button
    this.loginBtn.addEventListener("click", (e) => {
      e.preventDefault();
      if (this.isLoggedIn) {
        this.logout();
      } else {
        this.openLoginModal();
      }
    });

    // Close modal button
    if (this.closeModalBtn) {
      this.closeModalBtn.addEventListener("click", () => {
        this.closeLoginModal();
      });
    }

    // Modal overlay click
    const overlay = this.loginModal?.querySelector(".modal-overlay");
    if (overlay) {
      overlay.addEventListener("click", () => {
        this.closeLoginModal();
      });
    }

    // Password toggle
    if (this.togglePasswordBtn) {
      this.togglePasswordBtn.addEventListener("click", () => {
        this.togglePasswordVisibility();
      });
    }

    // Form submit
    this.loginForm.addEventListener("submit", (e) => {
      e.preventDefault();
      this.handleLogin();
    });
  }

  /**
   * Open login modal
   */
  openLoginModal() {
    if (!this.loginModal) {
      console.error("Login modal not found");
      return;
    }

    this.loginModal.classList.add("active");
    document.body.style.overflow = "hidden";

    // Refresh feather icons
    setTimeout(() => {
      if (typeof feather !== "undefined") {
        feather.replace();
      }
    }, 100);
  }

  /**
   * Close login modal
   */
  closeLoginModal() {
    if (!this.loginModal) return;

    this.loginModal.classList.remove("active");
    document.body.style.overflow = "auto";
  }

  /**
   * Toggle password visibility
   */
  togglePasswordVisibility() {
    const passwordInput = document.getElementById("loginPassword");
    const eyeIcon = document.getElementById("eyeIcon");

    if (!passwordInput || !eyeIcon) return;

    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";

    // Update icon
    eyeIcon.innerHTML = isPassword
      ? `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
               </svg>`
      : `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
               </svg>`;
  }

  /**
   * Handle login submission
   */
  async handleLogin() {
    const email = document.getElementById("loginEmail").value.trim();
    const password = document.getElementById("loginPassword").value;
    const rememberMe = document.getElementById("rememberMe").checked;

    // Validate
    if (!email || !password) {
      this.showNotification("Email dan password harus diisi!", "error");
      return;
    }

    try {
      // Show loading
      this.setLoadingState(true);

      // Call auth API
      const result = await this.authenticateAdmin(email, password);

      if (result.ok && result.user) {
        // Success
        this.handleLoginSuccess(email, rememberMe, result);
      } else {
        // Failed
        this.showNotification(
          result.message || "Email atau password salah!",
          "error",
        );
      }
    } catch (error) {
      console.error("Login error:", error);
      this.showNotification("Terjadi kesalahan. Coba lagi nanti.", "error");
    } finally {
      this.setLoadingState(false);
    }
  }

  /**
   * Authenticate admin via API
   */
  async authenticateAdmin(email, password) {
    const response = await fetch(`${CONFIG.API_BASE}/auth_admin.php`, {
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
    this.isLoggedIn = true;

    // Save to sessionStorage
    sessionStorage.setItem("userLoggedIn", "true");
    sessionStorage.setItem("userType", "admin");
    sessionStorage.setItem("userEmail", email);
    sessionStorage.setItem("userName", result.user.name || "Admin");

    // Save to localStorage (persistent)
    localStorage.setItem("adminLoggedIn", "true");
    localStorage.setItem("adminLoginTime", new Date().toISOString());
    localStorage.setItem("adminEmail", email);

    // Remember me
    if (rememberMe) {
      localStorage.setItem("rememberedEmail", email);
    } else {
      localStorage.removeItem("rememberedEmail");
    }

    // Update UI
    this.updateUI();
    this.closeLoginModal();
    this.loginForm.reset();

    // Show success message
    this.showNotification("Login berhasil! Selamat datang, Admin.", "success");

    // Dispatch event
    window.dispatchEvent(
      new CustomEvent("adminLoginStatusChanged", {
        detail: { isLoggedIn: true },
      }),
    );
  }

  /**
   * Logout admin
   */
  logout() {
    if (!confirm("Yakin mau logout?")) {
      return;
    }

    this.isLoggedIn = false;

    // Clear localStorage
    localStorage.removeItem("adminLoggedIn");
    localStorage.removeItem("adminLoginTime");
    localStorage.removeItem("adminEmail");

    // Clear sessionStorage
    sessionStorage.removeItem("userLoggedIn");
    sessionStorage.removeItem("userType");
    sessionStorage.removeItem("userEmail");
    sessionStorage.removeItem("userName");

    // Update UI
    this.updateUI();

    // Show message
    this.showNotification("Logout berhasil", "success");

    // Dispatch event
    window.dispatchEvent(
      new CustomEvent("adminLoginStatusChanged", {
        detail: { isLoggedIn: false },
      }),
    );
  }

  /**
   * Check login status (with timeout)
   */
  checkLoginStatus() {
    const loggedIn = localStorage.getItem("adminLoggedIn");
    const loginTime = localStorage.getItem("adminLoginTime");

    if (loggedIn === "true" && loginTime) {
      const loginDate = new Date(loginTime);
      const now = new Date();
      const diffMinutes = (now - loginDate) / 1000 / 60;

      // Check timeout
      if (diffMinutes > this.sessionTimeout) {
        console.log("Session expired");
        this.clearSession();
        this.isLoggedIn = false;
        return;
      }
    }

    // Set login status
    if (loggedIn === "true") {
      this.isLoggedIn = true;

      // Sync to sessionStorage (on page reload)
      if (sessionStorage.getItem("userLoggedIn") !== "true") {
        sessionStorage.setItem("userLoggedIn", "true");
        sessionStorage.setItem("userType", "admin");
        sessionStorage.setItem(
          "userEmail",
          localStorage.getItem("adminEmail") || "admin@ksmeducation.com",
        );
      }
    } else {
      this.isLoggedIn = false;
    }
  }

  /**
   * Clear session data
   */
  clearSession() {
    localStorage.removeItem("adminLoggedIn");
    localStorage.removeItem("adminLoginTime");
    localStorage.removeItem("adminEmail");
    sessionStorage.removeItem("userLoggedIn");
    sessionStorage.removeItem("userType");
    sessionStorage.removeItem("userEmail");
    sessionStorage.removeItem("userName");
  }

  /**
   * Update UI based on login status
   */
  updateUI() {
    this.updateLoginButton();
    this.updateUploadSection();
  }

  /**
   * Update login button text/style
   */
  updateLoginButton() {
    if (!this.loginBtn) return;

    if (this.isLoggedIn) {
      this.loginBtn.innerHTML = `<i data-feather="log-out"></i> LOGOUT`;
      this.loginBtn.classList.add("admin-logged-in");
    } else {
      this.loginBtn.textContent = "LOGIN";
      this.loginBtn.classList.remove("admin-logged-in");
    }

    if (typeof feather !== "undefined") {
      feather.replace();
    }
  }

  /**
   * Update upload section visibility
   */
  updateUploadSection() {
    if (!this.uploadSection) return;

    if (this.isLoggedIn) {
      this.uploadSection.classList.remove("locked");
    } else {
      this.uploadSection.classList.add("locked");
    }
  }

  /**
   * Auto-fill remembered email
   */
  autoFillRememberedEmail() {
    const rememberedEmail = localStorage.getItem("rememberedEmail");
    if (!rememberedEmail) return;

    const emailInput = document.getElementById("loginEmail");
    const rememberCheckbox = document.getElementById("rememberMe");

    if (emailInput) {
      emailInput.value = rememberedEmail;
    }

    if (rememberCheckbox) {
      rememberCheckbox.checked = true;
    }
  }

  /**
   * Set loading state
   */
  setLoadingState(isLoading) {
    const submitBtn = this.loginForm?.querySelector('button[type="submit"]');
    if (!submitBtn) return;

    if (isLoading) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Loading...";
    } else {
      submitBtn.disabled = false;
      submitBtn.textContent = "LOGIN";
    }
  }

  /**
   * Show notification (replace alert)
   */
  showNotification(message, type = "info") {
    // TODO: Replace with better notification system (toast, modal, etc)
    // For now, use alert
    alert(message);
  }

  /**
   * Check if current user is admin
   */
  isAdmin() {
    return this.isLoggedIn;
  }

  /**
   * Sync login status (for cross-page sync)
   */
  syncLoginStatus() {
    this.checkLoginStatus();
    this.updateUI();
  }
}

// Auto-initialize
let adminLoginManager;
document.addEventListener("DOMContentLoaded", () => {
  adminLoginManager = new AdminLoginManager();
  window.adminLoginManager = adminLoginManager;
});

console.log("admin-login-manager.js loaded");
