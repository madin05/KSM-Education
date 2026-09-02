<?php
// tools/_sim_clean_urls.php
// Simulasi routing clean URL (Apache/XAMPP). Sekali pakai untuk verifikasi;
// tidak dipakai runtime aplikasi.
//
// Jalankan: php tools/_sim_clean_urls.php  [base]
// Default base: http://localhost/ksmedu

$base = rtrim($argv[1] ?? 'http://localhost/ksmedu', '/');

/**
 * HTTP probe memakai stream wrapper (ekstensi cURL tidak wajib aktif).
 * @return array{status:int,ctype:string,loc:string,len:int,body:string,headers:string}
 */
function probe(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'follow_location' => 0,
            'max_redirects'   => 0, // jangan ikuti redirect: kita mau lihat 302 aslinya
            'ignore_errors'   => true,
            'timeout'         => 15,
            'header'          => "User-Agent: ksmedu-clean-url-sim\r\nAccept: */*\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $raw  = $http_response_header ?? [];
    if ($body === false && !$raw) {
        return ['status' => 0, 'ctype' => '', 'loc' => '', 'len' => 0, 'body' => '', 'headers' => ''];
    }
    $headers = implode("\n", $raw);

    // Ambil status dari status-line TERAKHIR (aman bila ada 1xx/continue).
    $status = 0;
    foreach ($raw as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    $ctype = '';
    $loc   = '';
    foreach ($raw as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $ctype = trim(substr($h, 13));
        } elseif (stripos($h, 'Location:') === 0) {
            $loc = trim(substr($h, 9));
        }
    }
    $body = (string)$body;
    return ['status' => $status, 'ctype' => $ctype, 'loc' => $loc, 'len' => strlen($body), 'body' => $body, 'headers' => $headers];
}

$pass = 0;
$fail = 0;

/**
 * @param callable(array):bool $assert
 */
function t(string $label, string $path, callable $assert): void
{
    global $base, $pass, $fail;
    $r = probe($base . $path);
    $ok = $assert($r);
    printf(
        "%-5s %-42s HTTP %d %s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $path,
        $r['status'],
        $r['ctype'] ? '(' . strtok($r['ctype'], ';') . ')' : '',
        $r['loc'] ? ' -> ' . $r['loc'] : ''
    );
    $ok ? $pass++ : $fail++;
}

// Halaman dianggap OK bila 200 (dirender) atau 302 ke halaman login
// (auth guard aktif = fitur keamanan tetap jalan). Yang TIDAK boleh: 404/500.
$page = function (array $r): bool {
    return ($r['status'] === 200 || $r['status'] === 302)
        && stripos($r['body'], 'Fatal error') === false
        && stripos($r['body'], 'Parse error') === false;
};
$html = function (array $r) use ($page): bool {
    return $page($r) && ($r['status'] === 302 || stripos($r['ctype'], 'text/html') !== false);
};

echo "=== 1. Clean URL halaman publik (tanpa .php) ===\n";
foreach ([
    '/user/dashboard', '/user/profile', '/user/profil', '/user/my_journals',
    '/user/journals', '/user/opinions', '/user/tentang', '/user/kontak',
    '/user/login', '/user/register', '/user/token_history', '/user/pengaturan',
    '/user/explore_jurnal', '/user/explore_opini',
    '/admin/dashboard', '/admin/login', '/admin/journals', '/admin/opinions',
    '/admin/review_journals', '/admin/comments', '/admin/contact_messages',
    '/admin/token_requests', '/admin/visitor_analytics',
    '/admin/explore_jurnal', '/admin/explore_opini',
] as $p) {
    t('clean', $p, $html);
}

echo "\n=== 2. Backward compatibility (.php lama tetap 200/302, BUKAN redirect ke clean URL) ===\n";
foreach ([
    '/user/dashboard_user.php', '/user/profil_user.php', '/user/my_journals_user.php',
    '/user/login_user.php', '/admin/dashboard_admin.php', '/admin/journals.php',
    '/admin/login_admin.php', '/admin/review_journals.php',
] as $p) {
    t('legacy', $p, $page);
}

echo "\n=== 3. API /services/ tidak boleh berubah ===\n";
// Endpoint POST-only membalas 405/400/401 -> tetap "tidak 404", artinya URL utuh.
$api = function (array $r): bool {
    return $r['status'] !== 404 && $r['status'] < 500;
};
foreach ([
    '/services/auth_login.php', '/services/upload.php', '/services/token_service.php',
    '/services/auth_me.php', '/services/admin/token_requests.php',
    '/services/comments/list_all.php', '/services/serve_pdf.php',
] as $p) {
    t('api', $p, $api);
}
// Clean URL versi API TIDAK boleh ada (kontrak tunggal).
t('api-noalias', '/services/auth_login', function (array $r) { return $r['status'] === 404; });

echo "\n=== 4. Static assets tidak di-rewrite ===\n";
t('css', '/styles/base/body.css', function (array $r) { return $r['status'] === 200 && stripos($r['ctype'], 'css') !== false; });
t('js',  '/js/config.js',    function (array $r) { return $r['status'] === 200 && (stripos($r['ctype'], 'javascript') !== false || stripos($r['ctype'], 'text/plain') !== false); });
t('img', '/assets/main_logo.png', function (array $r) { return $r['status'] === 200 && stripos($r['ctype'], 'image/') !== false; });
t('ico', '/assets/favicon.ico',   function (array $r) { return $r['status'] === 200; });

echo "\n=== 5. Uploads / PDF akses langsung ===\n";
$pdf = null;
foreach (glob(__DIR__ . '/../uploads/*/*.pdf') ?: [] as $f) { $pdf = $f; break; }
if ($pdf === null) {
    foreach (glob(__DIR__ . '/../uploads/*.pdf') ?: [] as $f) { $pdf = $f; break; }
}
if ($pdf !== null) {
    $rel = str_replace('\\', '/', substr(realpath($pdf), strlen(realpath(__DIR__ . '/..'))));
    t('pdf', $rel, function (array $r) { return $r['status'] === 200 && stripos($r['ctype'], 'pdf') !== false; });
} else {
    echo "SKIP  (tidak ada file PDF di uploads/ untuk diuji)\n";
}
t('upload-php-block', '/uploads/probe_not_exist.php', function (array $r) { return in_array($r['status'], [403, 404], true); });

echo "\n=== 6. Query string dipertahankan ===\n";
t('qs', '/user/dashboard?page=2', $html);
t('qs', '/admin/visitor_analytics?days=30', $html);
// Bukti $_GET sampai ke PHP: endpoint menjawab JSON hasil lookup id tsb.
// 404 + "Journal not found" = query string terbaca (bukan 404 dari web server).
$r    = probe($base . '/services/get_journal.php?id=999999');
$okQs = stripos($r['ctype'], 'json') !== false
    && stripos($r['body'], 'not found') !== false;
printf(
    "%-5s %-42s HTTP %d (body: %s)\n",
    $okQs ? 'PASS' : 'FAIL',
    '/services/get_journal.php?id=999999',
    $r['status'],
    substr(preg_replace('/\s+/', ' ', $r['body']), 0, 60)
);
$okQs ? $pass++ : $fail++;

echo "\n=== 7. URL tidak valid -> 404 (bukan loop / 500) ===\n";
foreach (['/user/tidak_ada', '/admin/tidak_ada', '/user/dashboard/extra', '/ngawur'] as $p) {
    t('404', $p, function (array $r) { return $r['status'] === 404; });
}

echo "\n=== 8. Directory listing & file sensitif ===\n";
t('noindex-uploads', '/uploads/', function (array $r) { return in_array($r['status'], [403, 404], true); });
t('noindex-styles',  '/styles/',  function (array $r) { return in_array($r['status'], [403, 404], true); });
t('deny-env',        '/.env',     function (array $r) { return in_array($r['status'], [403, 404], true); });
// Catatan: skrip di /tools/ memang masih dapat dieksekusi via HTTP (kondisi
// pre-existing, di luar scope clean URL). Yang diuji di sini hanyalah bahwa
// listing direktorinya tertutup. Tidak diprobe per-file karena skrip simulasi
// akan memanggil dirinya sendiri (rekursi).
t('noindex-tools',   '/tools/',  function (array $r) { return in_array($r['status'], [403, 404], true); });
t('deny-sql',        '/database/journal_system2.sql', function (array $r) { return in_array($r['status'], [403, 404], true); });

echo "\n=== 9. Root & security headers ===\n";
t('root', '/', function (array $r) { return $r['status'] === 302 || $r['status'] === 200; });
$need = ['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy'];
$hdrs = probe($base . '/user/dashboard')['headers'];
foreach ($need as $h) {
    $ok = stripos($hdrs, $h . ':') !== false;
    printf("%-5s header %s\n", $ok ? 'PASS' : 'FAIL', $h);
    $ok ? $pass++ : $fail++;
}

echo "\n----------------------------------------\n";
echo "PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
