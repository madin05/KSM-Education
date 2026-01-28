// Wrapper storage dengan sync queue
window.AppStorage = (function(){
  const PREFIX = 'app_'; // optional prefix
  const SYNC_QUEUE_KEY = PREFIX + 'sync_queue';

  function _key(k){ return PREFIX + k; }
  function getLocal(key){ const v = localStorage.getItem(_key(key)); return v ? JSON.parse(v) : null; }
  function setLocal(key, value){ localStorage.setItem(_key(key), JSON.stringify(value)); }

  function pushSync(change){
    const q = getLocal('sync_queue') || [];
    q.push(change);
    setLocal('sync_queue', q);
  }

  async function flushSync() {
    const q = getLocal('sync_queue') || [];
    if (!q.length) return { ok:true, applied:[] };
    try {
      const res = await window.syncPush(q);
      if (res && res.ok) {
        // clear queue on success
        setLocal('sync_queue', []);
      }
      return res;
    } catch(err){ return { ok:false, message: err.message }; }
  }

  return {
    get: async function(key, opts={remote:false}) {
      if (opts.remote) {
        // example: remote read for 'journals' => use listJournals
        if (key === 'journals') return await window.listJournals(opts.limit || 50, opts.offset || 0);
      }
      return getLocal(key);
    },

    set: async function(key, value, opts={sync:false, client_id:null}) {
      setLocal(key, value);
      if (opts.sync) {
        // push to sync queue
        const change = { type: opts.type || 'unknown', action: opts.action || 'update', payload:value, client_id: opts.client_id || ('c_' + Date.now()) };
        pushSync(change);
        if (navigator.onLine) { await flushSync(); }
      }
      return { ok:true };
    },

    queue: function(){ return getLocal('sync_queue') || []; },
    flushSync,
    migrateLocalToServer: async function(mappingKeys) {
      // mappingKeys: array of {key:'draft_journal', type:'journal', action:'create'}
      const results = [];
      for (const m of mappingKeys) {
        const items = getLocal(m.key) || [];
        for (const item of items) {
          try {
            if (m.type === 'journal') {
              // If file uploads are local URLs (object URLs) you should upload files first
              const resp = await window.createJournal(item);
              results.push({local:item, resp});
            } else {
              // push to sync queue
              pushSync({type:m.type, action:m.action || 'create', payload:item, client_id: item.client_temp_id || null});
            }
          } catch(e) {
            results.push({local:item, error:e.message});
          }
        }
      }
      // flush the queue
      const flush = await flushSync();
      return { migrated: results, flush };
    }
  };

  // migrate.js — jalankan ini di console atau panggil pada login
async function migrateLocalToServer() {
  // daftar key yang mau dimigrasi — ganti sesuai project Anda
  const keysToMigrate = ['draft_journal', 'draft_opinions']; 

  const results = { migrated: [], skipped: [], failed: [] };

  for (const key of keysToMigrate) {
    const raw = localStorage.getItem(key);
    if (!raw) continue;
    let items;
    try { items = JSON.parse(raw); } catch(e) { results.failed.push({key, error:'invalid json'}); continue; }
    if (!Array.isArray(items)) items = [items];

    for (const item of items) {
      try {
        // if item already has server_id, skip
        if (item.server_id) { results.skipped.push({key, item}); continue; }

        // 1) upload files first if item has file objects or local object URLs
        // assume item.file is a File object reference or { localFilePath: ... } — adapt to your data
        let fileUrl = item.fileUrl || null;
        if (item.file && item.file instanceof File) {
          const upl = await uploadFileToServer(item.file, pct => {
            console.log('upload', key, pct + '%');
          });
          if (!upl.ok) throw new Error('file upload failed: ' + (upl.message || ''));
          fileUrl = upl.url;
        }

        // 2) same for cover
        let coverUrl = item.coverUrl || null;
        if (item.cover && item.cover instanceof File) {
          const upl2 = await uploadFileToServer(item.cover);
          if (!upl2.ok) throw new Error('cover upload failed');
          coverUrl = upl2.url;
        }

        // 3) build metadata body for server
        const metadata = {
          title: item.title || item.name || 'Untitled',
          abstract: item.abstract || item.description || '',
          fileUrl,
          coverUrl,
          authors: item.authors || [],
          tags: item.tags || [],
          client_temp_id: item.client_temp_id || ('local_' + Date.now()),
          client_updated_at: item.client_updated_at || (new Date()).toISOString()
        };

        const created = await createJournal(metadata); // menggunakan api.js
        if (created.ok) {
          // simpan mapping di localStorage supaya tidak dimigrasi lagi
          item.server_id = created.id;
          item.migrated_at = new Date().toISOString();
          results.migrated.push({key, item, server_id: created.id});
        } else {
          results.failed.push({key, item, error: created.message || 'create failed'});
        }
      } catch (err) {
        results.failed.push({key, item, error: err.message});
      }
    }

    // update the localStorage — simpan item yang sudah dimigrasi (atau hapus, tergantung kebijakan)
    try {
      localStorage.setItem(key, JSON.stringify(items));
    } catch(e) {
      console.warn('Failed update localStorage for', key, e.message);
    }
  } // end for keys

  // optional: flush sync queue if you use AppStorage.flushSync
  if (window.AppStorage && typeof window.AppStorage.flushSync === 'function') {
    await window.AppStorage.flushSync();
  }

  console.log('migration results', results);
  return results;
}

// jalankan migrateLocalToServer(); dari console atau dari event login
})();
