<?php
/**
 * tools/sim_e2e.php — Simulasi end-to-end lokal (USER + ADMIN)
 * Jalankan: C:\xampp\php\php.exe tools/sim_e2e.php
 *
 * Skrip ini HANYA membuat akun/data bertanda "SIM" dan membersihkannya di akhir.
 */

require_once __DIR__ . '/../services/db.php';

const BASE = 'http://localhost/ksmedu';

$RESULTS = [];
$BUGS = [];

function hr(string $t): void { echo "\n==== $t ====\n"; }

function req(string $method, string $path, array $opt = []): array
{
    $url = BASE . '/' . ltrim($path, '/');
    $ch = curl_init();
    $headers = ['Accept: application/json'];
    if (!empty($opt['token']))  $headers[] = 'Authorization: Bearer ' . $opt['token'];
    if (!empty($opt['ctx']))    $headers[] = 'X-KSM-Context: ' . $opt['ctx'];
    if (!empty($opt['referer'])) curl_setopt($ch, CURLOPT_REFERER, $opt['referer']);

    if (isset($opt['json'])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['json']));
    } elseif (isset($opt['multipart'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['multipart']);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEJAR => $opt['jar'] ?? null,
        CURLOPT_COOKIEFILE => $opt['jar'] ?? null,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'body' => (string)$body, 'json' => json_decode((string)$body, true), 'err' => $err];
}

/** Catat hasil satu pengujian. */
function check(string $name, bool $pass, string $detail = '', string $severity = 'BUG'): bool
{
    global $RESULTS, $BUGS;
    $RESULTS[] = [$name, $pass];
    printf("  [%s] %-58s %s\n", $pass ? ' OK ' : 'FAIL', $name, $detail);
    if (!$pass) $BUGS[] = [$severity, $name, $detail];
    return $pass;
}

function shortBody(array $r, int $n = 160): string
{
    $b = preg_replace('/\s+/', ' ', $r['body']);
    return 'HTTP ' . $r['code'] . ' ' . substr($b, 0, $n);
}

// ============================================================
// 0. PERSIAPAN AKUN SIMULASI
// ============================================================
hr('0. PERSIAPAN');

$stamp     = time();
$userEmail = "sim.user.$stamp@example.test";
$user2Email= "sim.user2.$stamp@example.test";
$admEmail  = "sim.admin.$stamp@example.test";
$pass      = 'SimPass!2026';

$pdo->prepare("INSERT INTO users (email,password_hash,name,role,account_status) VALUES (?,?,?,'admin','active')")
    ->execute([$admEmail, password_hash($pass, PASSWORD_DEFAULT), 'SIM Admin']);
$admId = (int)$pdo->lastInsertId();
echo "  admin sim dibuat id=$admId\n";

$jarU  = tempnam(sys_get_temp_dir(), 'simu');
$jarU2 = tempnam(sys_get_temp_dir(), 'simv');
$jarA  = tempnam(sys_get_temp_dir(), 'sima');

// ============================================================
// 1. HALAMAN (SMOKE TEST HTTP)
// ============================================================
hr('1. SMOKE TEST HALAMAN');

$pages = [
    'index.php',
    'user/login_user.php', 'user/register_user.php', 'user/forgot_password.php',
    'user/reset_password.php', 'user/dashboard_user.php', 'user/explore_jurnal_user.php',
    'user/explore_opini_user.php', 'user/my_journals_user.php', 'user/profil_user.php',
    'user/pengaturan_user.php', 'user/kontak_user.php', 'user/tentang_user.php',
    'user/token_history_user.php', 'user/journals_user.php', 'user/opinions_user.php',
    'admin/login_admin.php', 'admin/dashboard_admin.php', 'admin/journals.php',
    'admin/opinions.php', 'admin/review_journals.php', 'admin/token_requests.php',
    'admin/comments.php', 'admin/contact_messages.php', 'admin/visitor_analytics.php',
    'admin/explore_jurnal_admin.php', 'admin/explore_opini_admin.php',
];
foreach ($pages as $p) {
    $r = req('GET', $p);
    $ok = in_array($r['code'], [200, 302], true);
    $extra = '';
    if ($ok && $r['code'] === 200 && preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/i', $r['body'], $m)) {
        $ok = false; $extra = 'PHP notice/error di output: ' . $m[1];
    }
    check("page $p", $ok, $extra ?: 'HTTP ' . $r['code']);
}

// ============================================================
// 2. AUTENTIKASI USER
// ============================================================
hr('2. AUTENTIKASI USER');

$r = req('POST', 'services/auth_register.php', ['json' => ['name' => 'SIM User', 'email' => $userEmail, 'password' => $pass], 'jar' => $jarU, 'ctx' => 'user']);
check('register user baru', $r['code'] === 201 || ($r['json']['ok'] ?? false), shortBody($r));

$r = req('POST', 'services/auth_register.php', ['json' => ['name' => 'SIM Dup', 'email' => $userEmail, 'password' => $pass], 'ctx' => 'user']);
check('register email duplikat ditolak', $r['code'] === 409 || $r['code'] === 400, shortBody($r));

$r = req('POST', 'services/auth_register.php', ['json' => ['name' => 'SIM Weak', 'email' => "sim.weak.$stamp@example.test", 'password' => '123'], 'ctx' => 'user']);
check('register password lemah ditolak', $r['code'] >= 400, shortBody($r));

$r = req('POST', 'services/auth_login.php', ['json' => ['email' => $userEmail, 'password' => 'SalahBanget!'], 'ctx' => 'user']);
check('login password salah ditolak', $r['code'] === 401, shortBody($r));

$r = req('POST', 'services/auth_login.php', ['json' => ['email' => $userEmail, 'password' => $pass], 'jar' => $jarU, 'ctx' => 'user']);
$uTok = $r['json']['access_token'] ?? $r['json']['data']['access_token'] ?? null;
$uRef = $r['json']['refresh_token'] ?? $r['json']['data']['refresh_token'] ?? null;
check('login user berhasil + access_token', $r['code'] === 200 && $uTok, shortBody($r));

$r = req('POST', 'services/auth_register.php', ['json' => ['name' => 'SIM User2', 'email' => $user2Email, 'password' => $pass], 'jar' => $jarU2, 'ctx' => 'user']);
$r = req('POST', 'services/auth_login.php', ['json' => ['email' => $user2Email, 'password' => $pass], 'jar' => $jarU2, 'ctx' => 'user']);
$u2Tok = $r['json']['access_token'] ?? null;
check('login user kedua (untuk uji IDOR)', (bool)$u2Tok, shortBody($r));

$r = req('GET', 'services/auth_me.php', ['token' => $uTok, 'ctx' => 'user']);
$uId = (int)($r['json']['user']['id'] ?? $r['json']['data']['id'] ?? 0);
check('auth_me mengembalikan identitas', $r['code'] === 200 && $uId > 0, shortBody($r));

$r = req('GET', 'services/auth_me.php', ['ctx' => 'user']);
check('auth_me tanpa token ditolak 401', $r['code'] === 401, shortBody($r));

$r = req('GET', 'services/auth_me.php', ['token' => ($uTok ? $uTok . 'x' : 'x'), 'ctx' => 'user']);
check('auth_me token dipalsukan ditolak', $r['code'] === 401, shortBody($r));

// ============================================================
// 3. TOKEN WALLET & UPLOAD BERBAYAR
// ============================================================
hr('3. TOKEN WALLET & SUBMIT JURNAL');

$r = req('GET', 'services/token_wallet.php', ['token' => $uTok, 'ctx' => 'user']);
$bal0 = $r['json']['wallet']['balance'] ?? $r['json']['balance'] ?? null;
check('token_wallet dapat dibaca', $r['code'] === 200, shortBody($r));
check('saldo awal user baru = 0', (int)$bal0 === 0, 'balance=' . var_export($bal0, true));

// upload PDF
$pdfPath = sys_get_temp_dir() . "/sim_$stamp.pdf";
file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
$r = req('POST', 'services/upload.php', ['multipart' => ['file' => new CURLFile($pdfPath, 'application/pdf', 'sim.pdf')], 'token' => $uTok, 'ctx' => 'user']);
$upId = $r['json']['id'] ?? $r['json']['upload']['id'] ?? null;
check('upload PDF berhasil', $r['code'] === 200 || $r['code'] === 201, shortBody($r));

// upload jenis file berbahaya
$badPath = sys_get_temp_dir() . "/sim_$stamp.php";
file_put_contents($badPath, "<?php echo 'x';");
$r = req('POST', 'services/upload.php', ['multipart' => ['file' => new CURLFile($badPath, 'application/x-php', 'shell.php')], 'token' => $uTok, 'ctx' => 'user']);
check('upload .php ditolak', $r['code'] >= 400, shortBody($r));

$r = req('POST', 'services/upload.php', ['multipart' => ['file' => new CURLFile($pdfPath, 'application/pdf', 'sim.pdf')]]);
check('upload tanpa auth ditolak', $r['code'] === 401, shortBody($r));

$journalPayload = [
    'title' => 'SIM Jurnal ' . $stamp, 'abstract' => str_repeat('abstrak simulasi. ', 5),
    'volume' => 'Vol 1', 'authors' => ['SIM Author'], 'tags' => ['sim'], 'pengurus' => ['SIM'],
    'email' => $userEmail, 'contact' => '08123456789', 'file_upload_id' => $upId,
];
$r = req('POST', 'services/submit_journal.php', ['json' => $journalPayload, 'token' => $uTok, 'ctx' => 'user']);
check('submit jurnal tanpa saldo token ditolak (402)', $r['code'] === 402, shortBody($r));

$r = req('POST', 'services/token_purchase_link.php', ['json' => ['amount' => 3], 'token' => $uTok, 'ctx' => 'user']);
check('buat permintaan pembelian token', $r['code'] < 400, shortBody($r));
$reqRow = $pdo->query("SELECT id,status FROM token_purchase_requests ORDER BY id DESC LIMIT 1")->fetch();

// top-up manual lewat wallet agar submit bisa diuji
$pdo->prepare("INSERT INTO user_token_wallets (user_id,balance) VALUES (?,5) ON DUPLICATE KEY UPDATE balance=5")->execute([$uId]);

$r = req('POST', 'services/submit_journal.php', ['json' => $journalPayload, 'token' => $uTok, 'ctx' => 'user']);
$jId = $r['json']['submission']['id'] ?? null;
check('submit jurnal dengan saldo berhasil (201)', $r['code'] === 201 && $jId, shortBody($r));

$r = req('GET', 'services/token_wallet.php', ['token' => $uTok, 'ctx' => 'user']);
$bal1 = (int)($r['json']['wallet']['balance'] ?? $r['json']['balance'] ?? -1);
check('saldo terpotong 1 setelah submit', $bal1 === 4, "balance=$bal1 (harapan 4)");

$r = req('POST', 'services/submit_journal.php', ['json' => ['title' => '', 'abstract' => '', 'volume' => ''], 'token' => $uTok, 'ctx' => 'user']);
check('submit jurnal field kosong ditolak', $r['code'] >= 400 && $r['code'] < 500, shortBody($r));

// ============================================================
// 4. JURNAL SAYA (CRUD) + IDOR
// ============================================================
hr('4. MY JOURNALS + IDOR');

$r = req('GET', 'services/my_journals.php', ['token' => $uTok, 'ctx' => 'user']);
$mine = $r['json']['results'] ?? $r['json']['journals'] ?? $r['json']['data'] ?? [];

check('my_journals menampilkan submission sendiri', $r['code'] === 200 && count($mine) >= 1, shortBody($r));

$r = req('GET', 'services/my_journals.php?status=ngawur', ['token' => $uTok, 'ctx' => 'user']);
check('my_journals status tidak valid ditolak', $r['code'] >= 400, shortBody($r));

$r = req('POST', 'services/update_my_journal.php', ['json' => ['id' => $jId, 'title' => 'SIM Jurnal Diedit', 'abstract' => 'abstrak diedit simulasi', 'volume' => 'Vol 2', 'authors' => ['SIM Author'], 'tags' => ['sim'], 'pengurus' => ['SIM'], 'email' => $userEmail, 'contact' => '08123456789'], 'token' => $uTok, 'ctx' => 'user']);
check('update jurnal milik sendiri', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/update_my_journal.php', ['json' => ['id' => $jId, 'title' => 'DIBAJAK', 'abstract' => 'x', 'volume' => 'v'], 'token' => $u2Tok, 'ctx' => 'user']);
check('IDOR: user lain TIDAK bisa edit jurnal ini', $r['code'] >= 400, shortBody($r), 'CRITICAL');

$r = req('POST', 'services/delete_my_journal.php', ['json' => ['id' => $jId], 'token' => $u2Tok, 'ctx' => 'user']);
check('IDOR: user lain TIDAK bisa hapus jurnal ini', $r['code'] >= 400, shortBody($r), 'CRITICAL');

// ============================================================
// 5. PROFIL, PREFERENSI, PASSWORD
// ============================================================
hr('5. PROFIL / PREFERENSI / PASSWORD');

$r = req('POST', 'services/update_profile.php', ['json' => ['name' => 'SIM User Updated', 'bio' => 'bio simulasi'], 'token' => $uTok, 'ctx' => 'user']);
check('update profil', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/update_profile.php', ['json' => ['name' => ''], 'token' => $uTok, 'ctx' => 'user']);
check('update profil nama kosong ditolak', $r['code'] >= 400, shortBody($r));

// Endpoint preferences memakai PUT (sesuai js/pengaturan_user.js), bukan POST.
$r = req('PUT', 'services/preferences.php', ['json' => ['preferences' => ['theme' => 'dark']], 'token' => $uTok, 'ctx' => 'user']);
check('simpan preferensi', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/preferences.php', ['json' => ['preferences' => ['theme' => 'dark']], 'token' => $uTok, 'ctx' => 'user']);
check('preferences menolak method selain GET/PUT', $r['code'] === 405, shortBody($r));


$r = req('GET', 'services/preferences.php', ['token' => $uTok, 'ctx' => 'user']);
check('baca preferensi', $r['code'] === 200 && strpos($r['body'], 'dark') !== false, shortBody($r));

$r = req('POST', 'services/change_password.php', ['json' => ['old_password' => 'SalahLama!', 'new_password' => 'SimPass!2027'], 'token' => $uTok, 'ctx' => 'user']);
check('ganti password dgn password lama salah ditolak', $r['code'] >= 400, shortBody($r));

$r = req('POST', 'services/change_password.php', ['json' => ['old_password' => $pass, 'new_password' => 'SimPass!2027'], 'token' => $uTok, 'ctx' => 'user']);
$pwChanged = $r['code'] < 400;
check('ganti password berhasil', $pwChanged, shortBody($r));

if ($pwChanged) {
    $r = req('GET', 'services/auth_me.php', ['token' => $uTok, 'ctx' => 'user']);
    check('token lama tidak berlaku setelah ganti password', $r['code'] === 401, shortBody($r), 'HIGH');

    $r = req('POST', 'services/auth_login.php', ['json' => ['email' => $userEmail, 'password' => 'SimPass!2027'], 'jar' => $jarU, 'ctx' => 'user']);
    $uTok = $r['json']['access_token'] ?? $uTok;
    check('login dgn password baru', $r['code'] === 200, shortBody($r));
}

// ============================================================
// 6. LUPA PASSWORD
// ============================================================
hr('6. RESET PASSWORD');

$r = req('POST', 'services/forgot_password.php', ['json' => ['email' => $userEmail], 'ctx' => 'user']);
check('forgot_password diterima', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/forgot_password.php', ['json' => ['email' => 'tidak.ada.' . $stamp . '@x.test'], 'ctx' => 'user']);
check('forgot_password email asing tidak membocorkan info', $r['code'] < 400 && stripos($r['body'], 'not found') === false && stripos($r['body'], 'tidak ditemukan') === false, shortBody($r), 'MEDIUM');

$r = req('POST', 'services/reset_password.php', ['json' => ['token' => 'token-palsu-123', 'password' => 'SimPass!2028'], 'ctx' => 'user']);
check('reset_password token palsu ditolak', $r['code'] >= 400, shortBody($r));

// ============================================================
// 7. PUBLIK: JURNAL, OPINI, KOMENTAR, KONTAK, STATISTIK
// ============================================================
hr('7. KONTEN PUBLIK & INTERAKSI');

foreach ([
    'services/list_journals.php', 'services/list_opinions.php',
    'services/get_stats.php', 'services/track_visitor.php',
] as $ep) {
    $r = req('GET', $ep, ['ctx' => 'user']);
    check("GET $ep", $r['code'] === 200 || $r['code'] === 405, shortBody($r));
}

$pubJ = $pdo->query("SELECT id FROM journals WHERE status='published' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($pubJ) {
    $r = req('GET', "services/get_journal.php?id=$pubJ", ['ctx' => 'user']);
    check('get_journal jurnal published', $r['code'] === 200, shortBody($r));
}
$r = req('GET', 'services/get_journal.php?id=99999999', ['ctx' => 'user']);
check('get_journal id tidak ada -> 404', $r['code'] === 404, shortBody($r));

$r = req('GET', "services/get_journal.php?id=$jId", ['ctx' => 'user']);
check('jurnal pending TIDAK bocor ke publik', $r['code'] >= 400, shortBody($r), 'HIGH');

$r = req('POST', 'services/comments/add.php', ['json' => ['journal_id' => $pubJ ?: 1, 'content' => 'Komentar simulasi ' . $stamp], 'token' => $uTok, 'ctx' => 'user']);
check('tambah komentar', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/comments/add.php', ['json' => ['journal_id' => $pubJ ?: 1, 'content' => '<script>alert(1)</script>'], 'token' => $uTok, 'ctx' => 'user']);
$xssStored = $pdo->query("SELECT COUNT(*) FROM comments WHERE content LIKE '%<script>%'")->fetchColumn();
check('komentar XSS di-sanitasi/di-escape', (int)$xssStored === 0, "baris dgn <script> di DB: $xssStored", 'MEDIUM');

$r = req('GET', 'services/comments/list_all.php', ['ctx' => 'user']);
check('comments/list_all butuh admin', $r['code'] === 401 || $r['code'] === 403, shortBody($r), 'HIGH');

$r = req('POST', 'services/send_contact.php', ['json' => ['name' => 'SIM Kontak', 'email' => $userEmail, 'subject' => 'Uji simulasi', 'message' => 'Pesan simulasi ' . $stamp], 'ctx' => 'user']);
check('kirim pesan kontak', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/send_contact.php', ['json' => ['name' => 'Bot', 'email' => 'bot@x.test', 'subject' => 's', 'message' => 'm', 'website' => 'http://spam'], 'ctx' => 'user']);
check('honeypot kontak aktif', $r['code'] < 400, shortBody($r));

$r = req('POST', 'services/send_contact.php', ['json' => ['name' => '', 'email' => 'bukan-email', 'subject' => '', 'message' => ''], 'ctx' => 'user']);
check('kontak validasi field wajib', $r['code'] >= 400, shortBody($r));

// ============================================================
// 8. OTORISASI: USER MENCOBA ENDPOINT ADMIN
// ============================================================
hr('8. OTORISASI USER -> ENDPOINT ADMIN');

$adminEndpoints = [
    ['GET',  'services/admin_review_queue.php'],
    ['POST', 'services/admin_review_journal.php'],
    ['GET',  'services/admin/dashboard_stats.php'],
    ['GET',  'services/admin/token_requests.php'],
    ['POST', 'services/admin/token_request_action.php'],
    ['GET',  'services/admin/token_transactions.php'],
    ['GET',  'services/admin/visitor_analytics.php'],
    ['GET',  'services/admin/contact_messages.php'],
    ['POST', 'services/create_opinion.php'],
    ['POST', 'services/delete_opinion.php'],
    ['POST', 'services/update_opinion.php'],
    ['POST', 'services/delete_journal.php'],
    ['POST', 'services/edit_journal.php'],
    ['POST', 'services/update_journal.php'],
    ['POST', 'services/add_token.php'],
];
foreach ($adminEndpoints as [$m, $ep]) {
    $r = req($m, $ep, ['token' => $uTok, 'ctx' => 'user', 'json' => ['id' => 1]]);
    check("user token ditolak di $ep", $r['code'] === 401 || $r['code'] === 403, shortBody($r, 90), 'CRITICAL');
}

// ============================================================
// 9. LOGIN ADMIN & PANEL
// ============================================================
hr('9. ADMIN PANEL');

$r = req('POST', 'services/auth_admin_login.php', ['json' => ['email' => $admEmail, 'password' => 'SalahBanget!'], 'ctx' => 'admin']);
check('login admin password salah ditolak', $r['code'] === 401, shortBody($r));

$r = req('POST', 'services/auth_admin_login.php', ['json' => ['email' => $userEmail, 'password' => 'SimPass!2027'], 'ctx' => 'admin']);
check('user biasa TIDAK bisa login via endpoint admin', $r['code'] === 401 || $r['code'] === 403, shortBody($r), 'CRITICAL');

$r = req('POST', 'services/auth_admin_login.php', ['json' => ['email' => $admEmail, 'password' => $pass], 'jar' => $jarA, 'ctx' => 'admin']);
$aTok = $r['json']['access_token'] ?? $r['json']['data']['access_token'] ?? null;
check('login admin berhasil', $r['code'] === 200 && $aTok, shortBody($r));

$r = req('GET', 'services/admin_review_queue.php?status=pending', ['token' => $aTok, 'ctx' => 'admin', 'referer' => BASE . '/admin/review_journals.php']);
check('antrean review dapat dibaca admin', $r['code'] === 200, shortBody($r));
check('jurnal SIM muncul di antrean review', strpos($r['body'], (string)$jId) !== false, shortBody($r, 90));

$r = req('GET', 'services/admin_review_queue.php?type=opinion&status=pending', ['token' => $aTok, 'ctx' => 'admin']);
check('antrean review opini', $r['code'] === 200, shortBody($r));

$r = req('POST', 'services/admin_review_journal.php', ['json' => ['id' => $jId, 'action' => 'approve'], 'token' => $aTok, 'ctx' => 'admin']);
check('admin approve jurnal', $r['code'] < 400, shortBody($r));
$st = $pdo->query("SELECT status FROM journals WHERE id=" . (int)$jId)->fetchColumn();
check('status jurnal menjadi published', $st === 'published', "status=$st");

$r = req('POST', 'services/admin_review_journal.php', ['json' => ['id' => $jId, 'action' => 'reject'], 'token' => $aTok, 'ctx' => 'admin']);
check('approve ganda / reject setelah publish ditangani', $r['code'] >= 400 || ($r['json']['ok'] ?? false), shortBody($r), 'MEDIUM');

$r = req('POST', 'services/admin_review_journal.php', ['json' => ['id' => $jId, 'action' => 'ngawur'], 'token' => $aTok, 'ctx' => 'admin']);
check('aksi review tidak valid ditolak', $r['code'] >= 400, shortBody($r));

$r = req('GET', 'services/admin/dashboard_stats.php', ['token' => $aTok, 'ctx' => 'admin']);
check('dashboard stats admin', $r['code'] === 200, shortBody($r));

$r = req('GET', 'services/admin/token_requests.php', ['token' => $aTok, 'ctx' => 'admin']);
check('daftar permintaan token', $r['code'] === 200, shortBody($r));

if ($reqRow && $reqRow['status'] === 'pending') {
    $r = req('POST', 'services/admin/token_request_action.php', ['json' => ['id' => (int)$reqRow['id'], 'action' => 'approve'], 'token' => $aTok, 'ctx' => 'admin']);
    check('admin approve permintaan token', $r['code'] < 400, shortBody($r));
    $r2 = req('POST', 'services/admin/token_request_action.php', ['json' => ['id' => (int)$reqRow['id'], 'action' => 'approve'], 'token' => $aTok, 'ctx' => 'admin']);
    $cnt = $pdo->query("SELECT COUNT(*) FROM token_transactions WHERE reference_type='token_purchase_request' AND reference_id=" . (int)$reqRow['id'])->fetchColumn();
    check('approve token idempoten (tidak dobel kredit)', (int)$cnt <= 1, "jumlah ledger=$cnt", 'CRITICAL');
}

$r = req('GET', 'services/admin/token_transactions.php', ['token' => $aTok, 'ctx' => 'admin']);
check('riwayat transaksi token admin', $r['code'] === 200, shortBody($r));

$r = req('GET', 'services/admin/visitor_analytics.php', ['token' => $aTok, 'ctx' => 'admin']);
check('analitik pengunjung', $r['code'] === 200, shortBody($r));

$r = req('GET', 'services/admin/contact_messages.php', ['token' => $aTok, 'ctx' => 'admin']);
check('inbox kontak admin', $r['code'] === 200, shortBody($r));
check('pesan kontak SIM masuk inbox', strpos($r['body'], 'Uji simulasi') !== false, shortBody($r, 90), 'HIGH');

$r = req('GET', 'services/comments/list_all.php', ['token' => $aTok, 'ctx' => 'admin']);
check('daftar semua komentar (admin)', $r['code'] === 200, shortBody($r));

// ============================================================
// 10. ISOLASI KONTEKS SESI
// ============================================================
hr('10. ISOLASI KONTEKS ADMIN vs USER');

$r = req('GET', 'services/auth_me.php', ['token' => $aTok, 'ctx' => 'user']);
check('token admin tidak valid pada konteks user', $r['code'] === 401 || $r['code'] === 403, shortBody($r), 'HIGH');

$r = req('GET', 'services/admin/dashboard_stats.php', ['token' => $uTok, 'ctx' => 'admin']);
check('token user tidak valid pada konteks admin', $r['code'] === 401 || $r['code'] === 403, shortBody($r), 'CRITICAL');

$r = req('GET', 'services/auth_me.php', ['jar' => $jarA, 'ctx' => 'user']);
check('cookie sesi admin tidak melogin-kan area user', $r['code'] === 401, shortBody($r), 'CRITICAL');

// ============================================================
// 11. REFRESH & LOGOUT
// ============================================================
hr('11. REFRESH & LOGOUT');

if ($uRef) {
    $r = req('POST', 'services/auth_refresh.php', ['json' => ['refresh_token' => $uRef], 'ctx' => 'user']);
    check('refresh token lama pasca ganti password ditolak', $r['code'] >= 400, shortBody($r), 'HIGH');
}

$r = req('POST', 'services/auth_login.php', ['json' => ['email' => $user2Email, 'password' => $pass], 'jar' => $jarU2, 'ctx' => 'user']);
$u2Tok = $r['json']['access_token'] ?? $u2Tok;
$u2Ref = $r['json']['refresh_token'] ?? null;
if ($u2Ref) {
    $r = req('POST', 'services/auth_refresh.php', ['json' => ['refresh_token' => $u2Ref], 'ctx' => 'user']);
    $newTok = $r['json']['access_token'] ?? null;
    check('refresh token valid menghasilkan access token baru', $r['code'] === 200 && $newTok, shortBody($r));
}

$r = req('POST', 'services/auth_logout.php', ['token' => $u2Tok, 'jar' => $jarU2, 'ctx' => 'user']);
check('logout user', $r['code'] < 400, shortBody($r));
$r = req('GET', 'services/auth_me.php', ['token' => $u2Tok, 'jar' => $jarU2, 'ctx' => 'user']);
check('access token diblacklist setelah logout', $r['code'] === 401, shortBody($r), 'HIGH');

// ============================================================
// 12. SYNC & MISC
// ============================================================
hr('12. SYNC & LAIN-LAIN');

$r = req('GET', 'services/sync_pull.php', ['token' => $uTok, 'ctx' => 'user']);
check('sync_pull', $r['code'] === 200 || $r['code'] === 405, shortBody($r));
$r = req('POST', 'services/sync_push.php', ['json' => ['items' => []], 'token' => $uTok, 'ctx' => 'user']);
check('sync_push', $r['code'] < 500, shortBody($r));

$r = req('GET', 'services/serve_pdf.php?id=' . (int)$upId, ['token' => $uTok, 'ctx' => 'user']);
check('serve_pdf tidak 500', $r['code'] !== 500, shortBody($r, 90));

$r = req('GET', 'services/serve_pdf.php?id=../../.env', ['ctx' => 'user']);
check('serve_pdf tahan path traversal', $r['code'] >= 400 && strpos($r['body'], 'DB_PASS') === false, shortBody($r, 90), 'CRITICAL');

$r = req('GET', '.env');
check('.env tidak dapat diakses via HTTP', $r['code'] >= 400 || strpos($r['body'], 'DB_PASS') === false, 'HTTP ' . $r['code'], 'CRITICAL');

$r = req('GET', 'services/db.php');
check('services/db.php tidak membocorkan kredensial', strpos($r['body'], 'DB_PASS') === false && stripos($r['body'], 'password') === false, shortBody($r, 90), 'HIGH');

$r = req('GET', 'services/list_journals.php?limit=99999&offset=-5', ['ctx' => 'user']);
check('list_journals tahan limit/offset ekstrem', $r['code'] === 200, shortBody($r, 90));

$r = req('GET', "services/get_journal.php?id=1' OR '1'='1", ['ctx' => 'user']);
check('get_journal tahan SQL injection sederhana', $r['code'] !== 500, shortBody($r, 90), 'CRITICAL');

// ============================================================
// BERSIH-BERSIH
// ============================================================
hr('CLEANUP');
try {
    $pdo->exec("DELETE FROM comments WHERE content LIKE '%simulasi $stamp%' OR content LIKE '%<script>alert(1)%'");
    $pdo->exec("DELETE FROM contact_messages WHERE subject = 'Uji simulasi'");
    $ids = $pdo->query("SELECT id FROM users WHERE email LIKE 'sim.%@example.test'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $pdo->exec("DELETE FROM token_transactions WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM token_purchase_requests WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM user_token_wallets WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM user_preferences WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM journals WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM password_reset_tokens WHERE user_id=" . (int)$id);
        $pdo->exec("DELETE FROM users WHERE id=" . (int)$id);
    }
    echo "  data simulasi dibersihkan (" . count($ids) . " akun)\n";
} catch (Throwable $e) {
    echo "  cleanup warning: " . $e->getMessage() . "\n";
}
@unlink($pdfPath); @unlink($badPath); @unlink($jarU); @unlink($jarU2); @unlink($jarA);

// ============================================================
// RINGKASAN
// ============================================================
hr('RINGKASAN');
$total = count($RESULTS);
$fail = count($BUGS);
echo "  Total pengujian : $total\n";
echo "  Lulus           : " . ($total - $fail) . "\n";
echo "  Gagal           : $fail\n";

if ($fail) {
    echo "\n---- TEMUAN ----\n";
    foreach ($BUGS as $i => [$sev, $name, $detail]) {
        printf("%2d. [%s] %s\n     -> %s\n", $i + 1, $sev, $name, $detail);
    }
}
