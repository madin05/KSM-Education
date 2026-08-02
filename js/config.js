// js/config.js
(function() {
  const getAppRoot = () => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    const knownFolders = ['user', 'admin', 'services', 'assets', 'js', 'styles'];
    // If the first part of the path isn't a known top-level folder, it's likely the app root
    if (parts.length > 0 && !knownFolders.includes(parts[0])) {
      return '/' + parts[0];
    }
    return '';
  };

  // ===========================================================================
  // CLEAN URL SUPPORT
  // ---------------------------------------------------------------------------
  // Public pages are now served without ".php" (see .htaccess / deploy/nginx/
  // ksmedu.conf). The rewrite is INTERNAL, so PHP still sees the real script
  // name -- but window.location.pathname does NOT. Several scripts decide which
  // feature to boot by testing pathname.includes("dashboard_user.php"), so this
  // map lets them ask the question in a way that works for BOTH the clean URL
  // and the legacy ".php" URL (which stays supported).
  //
  // Keys are the public URL segment, values the real script filename. Only
  // pages whose filename differs from the URL segment need an entry; pages such
  // as /admin/journals -> journals.php are derived automatically.
  // ===========================================================================
  const PAGE_MAP = {
    user: {
      dashboard:      'dashboard_user.php',
      journals:       'journals_user.php',
      opinions:       'opinions_user.php',
      explore_jurnal: 'explore_jurnal_user.php',
      explore_opini:  'explore_opini_user.php',
      tentang:        'tentang_user.php',
      kontak:         'kontak_user.php',
      login:          'login_user.php',
      register:       'register_user.php',
      profile:        'profil_user.php',
      profil:         'profil_user.php',   // alias, same target
      my_journals:    'my_journals_user.php',
      token_history:  'token_history_user.php',
      pengaturan:     'pengaturan_user.php'
    },
    admin: {
      dashboard:      'dashboard_admin.php',
      login:          'login_admin.php',
      explore_jurnal: 'explore_jurnal_admin.php',
      explore_opini:  'explore_opini_admin.php'
    }
  };

  // Reverse lookup: real filename -> canonical clean segment (first one wins,
  // so profil_user.php maps back to "profile" and not to the "profil" alias).
  const FILE_MAP = { user: {}, admin: {} };
  Object.keys(PAGE_MAP).forEach(function (area) {
    Object.keys(PAGE_MAP[area]).forEach(function (segment) {
      const file = PAGE_MAP[area][segment];
      if (!FILE_MAP[area][file]) FILE_MAP[area][file] = segment;
    });
  });

  // Current path expressed with the REAL script filename, whatever URL style
  // the visitor used. "/user/dashboard" and "/user/dashboard_user.php" both
  // resolve to "/user/dashboard_user.php".
  const resolvePath = () => {
    let path = window.location.pathname;
    if (path.endsWith('/')) return path + 'index.php';
    if (path.endsWith('.php') || path.endsWith('.html')) return path;

    const parts = path.split('/').filter(Boolean);
    const segment = parts[parts.length - 1];
    const area = parts[parts.length - 2];         // "user" | "admin" | undefined
    if (area && PAGE_MAP[area] && PAGE_MAP[area][segment]) {
      return path.slice(0, path.length - segment.length) + PAGE_MAP[area][segment];
    }
    // Same-name page (e.g. /admin/journals -> journals.php)
    if (area === 'user' || area === 'admin') return path + '.php';
    return path;
  };

  window.APP_CONFIG = {
    ROOT: getAppRoot(),
    get root() { return this.ROOT; }, // Alias for convenience
    get SERVICES() { return this.ROOT + '/services'; },
    get apiBase() { return this.SERVICES; }, // Alias for backward compatibility

    // Path/basename of the script actually handling this request.
    get resolvedPath() { return resolvePath(); },
    get page() { return resolvePath().split('/').pop(); },

    /**
     * Page test that is agnostic of the URL style.
     *   APP_CONFIG.isPage('dashboard_user.php')  // "/user/dashboard" -> true
     *   APP_CONFIG.isPage('journals.php', 'opinions.php')
     * Accepts either the real filename or the clean segment.
     */
    isPage: function () {
      const current = this.page.toLowerCase();
      for (let i = 0; i < arguments.length; i++) {
        let name = String(arguments[i]).toLowerCase();
        if (!name) continue;
        if (current === name) return true;
        // clean segment passed in -> compare against every mapped filename
        if (name.indexOf('.') === -1) {
          if (current === name + '.php') return true;
          const areas = Object.keys(PAGE_MAP);
          for (let a = 0; a < areas.length; a++) {
            const file = PAGE_MAP[areas[a]][name];
            if (file && current === file.toLowerCase()) return true;
          }
        }
      }
      return false;
    },

    /**
     * Clean, root-relative URL for a page.
     *   APP_CONFIG.pageUrl('user', 'dashboard_user.php') -> "/user/dashboard"
     *   APP_CONFIG.pageUrl('admin', 'journals.php')      -> "/admin/journals"
     */
    pageUrl: function (area, file) {
      const segment = (FILE_MAP[area] && FILE_MAP[area][file]) || file.replace(/\.php$/, '');
      return this.ROOT + '/' + area + '/' + segment;
    }
  };

  // ---------------------------------------------------------------------------
  // Global shorthands used by the page scripts.
  //   ksmPagePath() -> pathname with the REAL script name, regardless of whether
  //                    the visitor came in via "/user/dashboard" or via
  //                    "/user/dashboard_user.php".
  //   ksmIsPage()   -> URL-style agnostic page test.
  // Existing code that tested location.pathname.includes("<file>.php") keeps
  // working unchanged by swapping location.pathname for ksmPagePath().
  // ---------------------------------------------------------------------------
  window.ksmPagePath = function () { return resolvePath(); };
  window.ksmIsPage = function () {
    return window.APP_CONFIG.isPage.apply(window.APP_CONFIG, arguments);
  };

  console.log('App Config initialized:', window.APP_CONFIG);

})();
