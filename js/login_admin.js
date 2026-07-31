// js/login_admin.js
// Handler form login administrator (admin/login_admin.php).
// Halaman ini sengaja tidak memuat api.js, jadi penulisan token dilakukan
// langsung dengan key yang sama seperti TokenManager di js/api.js.
(function () {
  'use strict';

  var TOKEN_KEYS = {
    access: 'jwt_access_token',
    refresh: 'jwt_refresh_token',
    expiry: 'jwt_token_expiry',
  };

  var form = document.getElementById('adminLoginForm');
  var emailInput = document.getElementById('adminEmail');
  var passwordInput = document.getElementById('adminPassword');
  var submitBtn = document.getElementById('btnAdminLogin');
  var alertBox = document.getElementById('loginAlert');
  var toggleBtn = document.getElementById('togglePassword');

  if (!form) return;

  function showError(message) {
    if (!alertBox) {
      window.alert(message);
      return;
    }
    alertBox.textContent = message;
    alertBox.classList.add('is-visible');
  }

  function clearError() {
    if (!alertBox) return;
    alertBox.textContent = '';
    alertBox.classList.remove('is-visible');
  }

  function setLoading(isLoading) {
    if (!submitBtn) return;
    submitBtn.disabled = isLoading;
    submitBtn.textContent = isLoading ? 'MEMPROSES...' : 'MASUK';
  }

  function storeTokens(data) {
    try {
      if (window.TokenManager && typeof window.TokenManager.setTokens === 'function') {
        window.TokenManager.setTokens(data.access_token, data.refresh_token, data.expires_in);
      } else {
        localStorage.setItem(TOKEN_KEYS.access, data.access_token);
        if (data.refresh_token) {
          localStorage.setItem(TOKEN_KEYS.refresh, data.refresh_token);
        }
        if (data.expires_in) {
          localStorage.setItem(
            TOKEN_KEYS.expiry,
            String(Date.now() + Number(data.expires_in) * 1000)
          );
        }
      }
      if (data.user) {
        localStorage.setItem('admin_user', JSON.stringify(data.user));
        localStorage.setItem('currentUser', JSON.stringify(data.user));
      }
    } catch (err) {
      // localStorage bisa diblokir (mode privat). Session PHP tetap valid,
      // jadi login masih bisa dilanjutkan.
      console.warn('Gagal menyimpan token di localStorage:', err);
    }
  }

  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function () {
      var isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      toggleBtn.setAttribute(
        'aria-label',
        isHidden ? 'Sembunyikan password' : 'Tampilkan password'
      );
      var icon = toggleBtn.querySelector('svg, i');
      if (icon) {
        icon.setAttribute('data-feather', isHidden ? 'eye-off' : 'eye');
      }
      if (window.feather) window.feather.replace();
    });
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    clearError();

    var email = (emailInput.value || '').trim();
    var password = passwordInput.value || '';

    if (!email || !password) {
      showError('Email dan password wajib diisi.');
      return;
    }

    var apiBase =
      (window.APP_CONFIG && window.APP_CONFIG.apiBase) || '../services';

    setLoading(true);
    try {
      var res = await fetch(apiBase + '/auth_admin_login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ email: email, password: password }),
      });

      var data = null;
      try {
        data = await res.json();
      } catch (parseErr) {
        throw new Error('Respons server tidak valid.');
      }

      if (!res.ok || !data || !data.ok) {
        showError((data && data.message) || 'Login admin gagal.');
        setLoading(false);
        passwordInput.value = '';
        passwordInput.focus();
        return;
      }

      storeTokens(data);

      var next = window.ADMIN_LOGIN_NEXT || 'dashboard_admin.php';
      window.location.replace(next);
    } catch (err) {
      console.error('Admin login error:', err);
      showError('Tidak dapat menghubungi server. Coba lagi.');
      setLoading(false);
    }
  });
})();
