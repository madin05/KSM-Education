// js/auth_storage.js
// Penyimpanan kredensial sisi klien yang dipakai bersama oleh halaman login /
// register (user maupun admin).
//
// LATAR MASALAH:
// Halaman login user & admin sengaja TIDAK memuat js/api.js (TokenManager),
// sehingga penulisan token di sana sebelumnya dilewati begitu saja. Akibatnya
// token JWT sesi sebelumnya (misalnya milik admin) tetap tersimpan di
// localStorage dan ikut dikirim oleh halaman yang memuat api.js — dashboard
// user pun menampilkan data admin.
//
// Modul ini memastikan setiap login/registrasi SELALU:
//   1. menghapus seluruh artefak sesi lama (token, identitas, flag UI), lalu
//   2. menulis kredensial milik akun yang baru saja masuk.
//
// Sengaja tanpa dependensi supaya bisa dimuat di halaman auth yang minimal.
(function (global) {
  'use strict';

  // Key harus identik dengan TokenManager di js/api.js.
  var TOKEN_KEYS = {
    access: 'jwt_access_token',
    refresh: 'jwt_refresh_token',
    expiry: 'jwt_token_expiry',
  };

  // Identitas / flag warisan yang pernah dipakai berbagai modul lama.
  var LOCAL_IDENTITY_KEYS = [
    'admin_user',
    'currentUser',
    'adminLoggedIn',
    'adminLoginTime',
  ];

  var SESSION_KEYS = [
    'userLoggedIn',
    'userEmail',
    'userName',
    'userType',
  ];

  function removeLocal(key) {
    try {
      localStorage.removeItem(key);
    } catch (err) {
      /* storage bisa diblokir (mode privat) — abaikan */
    }
  }

  function removeSession(key) {
    try {
      sessionStorage.removeItem(key);
    } catch (err) {
      /* idem */
    }
  }

  function setLocal(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (err) {
      return false;
    }
    return true;
  }

  function setSessionItem(key, value) {
    try {
      sessionStorage.setItem(key, value);
    } catch (err) {
      return false;
    }
    return true;
  }

  var AuthStorage = {
    TOKEN_KEYS: TOKEN_KEYS,

    /**
     * Hapus SEMUA jejak sesi sebelumnya (token JWT, identitas, flag UI).
     * Wajib dipanggil sebelum menulis kredensial akun baru supaya sesi admin
     * dan sesi user tidak pernah tercampur.
     */
    clearAll: function () {
      removeLocal(TOKEN_KEYS.access);
      removeLocal(TOKEN_KEYS.refresh);
      removeLocal(TOKEN_KEYS.expiry);
      LOCAL_IDENTITY_KEYS.forEach(removeLocal);
      SESSION_KEYS.forEach(removeSession);

      // Bila api.js ikut dimuat, biarkan ia membersihkan state internalnya.
      if (global.TokenManager && typeof global.TokenManager.clearTokens === 'function') {
        try {
          global.TokenManager.clearTokens();
        } catch (err) {
          /* diabaikan */
        }
      }
    },

    /**
     * Simpan token JWT dari respons login/registrasi.
     * @param {{access_token?:string, refresh_token?:string, expires_in?:number}} data
     * @returns {boolean} true bila token berhasil ditulis
     */
    setTokens: function (data) {
      if (!data || !data.access_token) return false;

      if (global.TokenManager && typeof global.TokenManager.setTokens === 'function') {
        try {
          global.TokenManager.setTokens(
            data.access_token,
            data.refresh_token,
            data.expires_in
          );
          return true;
        } catch (err) {
          // jatuh ke penulisan manual di bawah
        }
      }

      var ok = setLocal(TOKEN_KEYS.access, data.access_token);
      if (data.refresh_token) {
        setLocal(TOKEN_KEYS.refresh, data.refresh_token);
      }
      if (data.expires_in) {
        setLocal(
          TOKEN_KEYS.expiry,
          String(Date.now() + Number(data.expires_in) * 1000)
        );
      }
      return ok;
    },

    /**
     * Tulis flag sesi yang dibaca modul UI (script.js, dashboard_user.js, dst).
     * @param {{email?:string, name?:string, role?:string}} user
     */
    setSession: function (user) {
      var info = user || {};
      setSessionItem('userLoggedIn', 'true');
      if (info.email) setSessionItem('userEmail', info.email);
      if (info.name) setSessionItem('userName', info.name);
      setSessionItem('userType', info.role || 'user');
    },
  };

  global.AuthStorage = AuthStorage;
})(window);
