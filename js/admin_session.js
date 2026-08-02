// js/admin_session.js
// Pengganti js/login.js untuk halaman /admin.
//
// Autentikasi admin sekarang dilakukan server-side:
//   admin/login_admin.php -> services/auth_admin_login.php -> session PHP
//   admin/components/auth_guard.php memverifikasi tiap request halaman.
//
// Artinya, saat file ini dieksekusi, sesi admin PASTI valid (kalau tidak,
// guard sudah me-redirect ke halaman login sebelum HTML dikirim).
// Modul lama (dual_upload_handler.js, script.js) masih memanggil
// window.loginManager, jadi di sini disediakan shim yang kompatibel.
(function () {
  'use strict';

  var payload = window.ADMIN_SESSION || {};

  // script.js membaca flag ini untuk menampilkan UI mode admin.
  try {
    sessionStorage.setItem('userLoggedIn', 'true');
    sessionStorage.setItem('userType', 'admin');
    if (payload.email) {
      sessionStorage.setItem('userEmail', payload.email);
    }
  } catch (err) {
    // Mode privat browser bisa memblokir storage; sesi PHP tetap berlaku.
    console.warn('Tidak dapat menulis sessionStorage:', err);
  }

  // Bersihkan sisa flag era login modal supaya tidak dipakai sebagai
  // sumber kebenaran lagi.
  try {
    localStorage.removeItem('adminLoggedIn');
    localStorage.removeItem('adminLoginTime');
  } catch (err) {
    /* diabaikan */
  }

  window.loginManager = {
    isLoggedIn: true,
    user: payload,
    isAdmin: function () {
      return true;
    },
    // Tidak ada lagi modal login di halaman admin: arahkan ke halaman login.
    openLoginModal: function () {
      window.location.href = 'login';
    },
    closeLoginModal: function () {},
    syncLoginStatus: function () {},
    // Logout harus MENCABUT token, bukan cuma menghapus session PHP.
    // Sebelumnya fungsi ini langsung redirect (GET) tanpa mengirim
    // Authorization/refresh_token, sehingga JWT admin di localStorage tetap
    // sah sampai kedaluwarsa dan masih bisa dipakai memanggil endpoint
    // services/admin/*. Sekarang token di-blacklist lebih dulu.
    logout: function () {
      var redirect = '../admin/login_admin.php';
      var apiBase =
        (window.APP_CONFIG && window.APP_CONFIG.apiBase) || '../services';
      var headers = { 'Content-Type': 'application/json' };
      var body = {};

      try {
        var store = window.AuthStorage || null;
        var access = store && store.getAccessToken ? store.getAccessToken() : null;
        var refresh = store && store.getRefreshToken ? store.getRefreshToken() : null;
        if (!access) access = localStorage.getItem('jwt_access_token_admin');
        if (!refresh) refresh = localStorage.getItem('jwt_refresh_token_admin');
        if (access) headers['Authorization'] = 'Bearer ' + access;
        if (refresh) body.refresh_token = refresh;
      } catch (err) {
        /* storage diblokir: lanjut dengan logout berbasis session saja */
      }

      function finish() {
        try {
          if (window.AuthStorage && window.AuthStorage.clearAll) {
            window.AuthStorage.clearAll();
          }
          localStorage.removeItem('jwt_access_token_admin');
          localStorage.removeItem('jwt_refresh_token_admin');
          localStorage.removeItem('jwt_token_expiry_admin');
          localStorage.removeItem('admin_user');
          localStorage.removeItem('currentUser');
          sessionStorage.removeItem('userLoggedIn');
          sessionStorage.removeItem('userType');
          sessionStorage.removeItem('userEmail');
        } catch (err) {
          /* diabaikan */
        }
        window.location.replace(redirect);
      }

      fetch(apiBase + '/auth_logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify(body),
      })
        .then(finish)
        .catch(function (err) {
          console.error('Admin logout error:', err);
          // Fallback: biarkan PHP menghancurkan session lalu redirect.
          window.location.href =
            apiBase +
            '/auth_logout.php?redirect=' +
            encodeURIComponent(redirect);
        });
    },
  };


  window.dispatchEvent(
    new CustomEvent('adminLoginStatusChanged', { detail: { isLoggedIn: true } })
  );
})();
