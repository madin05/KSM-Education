
(function (global) {
  'use strict';

  var CTX_ADMIN = 'admin';
  var CTX_USER = 'user';

  function detectContext() {
    try {
      return /(^|\/)admin\//.test(global.location.pathname) ? CTX_ADMIN : CTX_USER;
    } catch (err) {
      return CTX_USER;
    }
  }

  var CONTEXT = detectContext();

  // Key harus identik dengan TokenManager di js/api.js.
  function tokenKey(base) {
    return CONTEXT === CTX_ADMIN ? base + '_admin' : base;
  }

  var TOKEN_KEYS = {
    access: tokenKey('jwt_access_token'),
    refresh: tokenKey('jwt_refresh_token'),
    expiry: tokenKey('jwt_token_expiry'),
  };

  // Identitas / flag warisan, dipetakan ke konteks pemiliknya supaya
  // pembersihan tidak lintas area.
  var LOCAL_IDENTITY_KEYS = CONTEXT === CTX_ADMIN
    ? ['admin_user', 'adminLoggedIn', 'adminLoginTime']
    : ['currentUser'];

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
    CONTEXT: CONTEXT,
    TOKEN_KEYS: TOKEN_KEYS,

    /**
     * Hapus jejak sesi sebelumnya PADA KONTEKS INI (token JWT, identitas,
     * flag UI). Wajib dipanggil sebelum menulis kredensial akun baru supaya
     * sisa kredensial akun lama di area yang sama tidak ikut terpakai.
     */
    clearAll: function () {
      removeLocal(TOKEN_KEYS.access);
      removeLocal(TOKEN_KEYS.refresh);
      removeLocal(TOKEN_KEYS.expiry);
      LOCAL_IDENTITY_KEYS.forEach(removeLocal);
      // sessionStorage bersifat per-tab, jadi aman dibersihkan seluruhnya.
      SESSION_KEYS.forEach(removeSession);

      // Bila api.js ikut dimuat, biarkan ia membersihkan state internalnya
      // (TokenManager memakai key konteks yang sama).
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
