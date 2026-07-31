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
      window.location.href = 'login_admin.php';
    },
    closeLoginModal: function () {},
    syncLoginStatus: function () {},
    logout: function () {
      window.location.href =
        '../services/auth_logout.php?redirect=' +
        encodeURIComponent('../admin/login_admin.php');
    },
  };

  window.dispatchEvent(
    new CustomEvent('adminLoginStatusChanged', { detail: { isLoggedIn: true } })
  );
})();
