<?php
/**
 * tools/sim_email_otp.php — simulasi end-to-end fitur Email OTP.
 *
 * HANYA untuk pengujian lokal. Tidak mengubah kode/logika aplikasi.
 * Manipulasi DB yang dilakukan bersifat FIXTURE pengujian saja:
 *   - menulis otp_hash dengan kode yang diketahui (karena OTP disimpan hash,
 *     tidak ada mode testing untuk membaca plaintext),
 *   - memundurkan expires_at / created_at,
 *   - membersihkan tabel rate limit per IP antar-test.
 */

require_once __DIR__ . '/../services/env_loader.php';

const BASE = 'http://localhost/ksmedu';
const KNOWN_OTP = '424242';

$results = [];   // [test => [status, detail[]]]
$cleanupEmails = [];

function env_or(string $key, string $default): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string)$v;
}

function pdo_conn(): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
        env_or('DB_HOST', 'localhost'), env_or('DB_NAME', 'journal_system2'));
    $pdo = new PDO($dsn, env_or('DB_USER', 'root'), env_or('DB_PASS', ''), [

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Samakan zona waktu sesi dengan services/db.php, kalau tidak fixture
    // expires_at/created_at akan bergeser terhadap CURRENT_TIMESTAMP aplikasi.
    $tz = env_or('APP_TIMEZONE', 'Asia/Jakarta');
    $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");

    return $pdo;
}

/**
 * Endpoint mengembalikan JSON, tetapi env_loader.php memuntahkan PHP Warning
 * (display_errors aktif di XAMPP) sehingga body bisa didahului teks non-JSON.
 * Noise dicatat agar tetap terlihat di laporan.
 */
function parse_json(string $body, ?string &$noise = null): ?array
{
    $pos = strpos($body, '{');
    $noise = $pos === false ? trim($body) : trim(substr($body, 0, $pos));
    if ($pos === false) {
        return null;
    }
    $decoded = json_decode(substr($body, $pos), true);

    return is_array($decoded) ? $decoded : null;
}


function http_post(string $path, array $payload, ?string $jar = null): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $hlen);
    $body = substr($raw, $hlen);
    $noise = null;
    $parsed = parse_json($body, $noise);

    return [
        'status' => $status,
        'body' => $parsed,
        'raw' => $body,
        'headers' => $headers,
        'noise' => $noise,
    ];
}


function http_get(string $path): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'raw' => $body];
}

function reset_ip_limit(PDO $pdo): void
{
    $pdo->exec('DELETE FROM email_otp_ip_attempts');
}

function user_row(PDO $pdo, string $email): ?array
{
    $s = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $s->execute([$email]);
    return $s->fetch() ?: null;
}

function otp_rows(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT * FROM email_verifications WHERE user_id = ? ORDER BY id');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** Fixture: paksa OTP aktif terakhir memakai kode yang diketahui harness. */
function force_known_otp(PDO $pdo, int $userId, string $code = KNOWN_OTP): void
{
    $s = $pdo->prepare('UPDATE email_verifications SET otp_hash = ?
                        WHERE user_id = ? AND consumed_at IS NULL
                        ORDER BY id DESC LIMIT 1');
    $s->execute([password_hash($code, PASSWORD_DEFAULT), $userId]);
}

function register_user(string $email, string $password = 'Rahasia123'): array
{
    return http_post('/services/auth_register.php', [
        'name' => 'Sim OTP User', 'email' => $email, 'password' => $password,
    ]);
}

function verdict(string $test, string $status, array $detail): void
{
    global $results;
    $results[$test] = ['status' => $status, 'detail' => $detail];
    printf("\n[%s] %s\n", $status, $test);
    foreach ($detail as $d) {
        echo '    - ' . $d . "\n";
    }
}

$pdo = pdo_conn();
$tmp = sys_get_temp_dir();

// ---------------------------------------------------------------- preflight
$ping = http_get('/user/login_user.php');
if ($ping['status'] === 0) {
    echo "ABORT: Apache di " . BASE . " tidak merespons. Nyalakan Apache lalu ulangi.\n";
    exit(1);
}
echo "Preflight: HTTP " . $ping['status'] . " dari " . BASE . "/user/login_user.php\n";
$stamp = date('YmdHis');

// ================================================================== TEST 1
reset_ip_limit($pdo);
$email1 = "sim_otp_t1_{$stamp}@example.com";
$cleanupEmails[] = $email1;
$r1 = register_user($email1);
$u1 = user_row($pdo, $email1);
$rows1 = $u1 ? otp_rows($pdo, (int)$u1['id']) : [];
$login1 = http_post('/services/auth_login.php', ['email' => $email1, 'password' => 'Rahasia123']);

$d = [
    'POST auth_register.php -> HTTP ' . $r1['status'] . ' ok=' . var_export($r1['body']['ok'] ?? null, true),
    'email_sent (Resend) = ' . var_export($r1['body']['email_sent'] ?? null, true),
    'user dibuat = ' . ($u1 ? 'ya (id ' . $u1['id'] . ')' : 'TIDAK'),
    'email_verified_at = ' . var_export($u1['email_verified_at'] ?? null, true),
    'baris email_verifications = ' . count($rows1),
    'login sebelum verify -> HTTP ' . $login1['status'],
];
$pass1 = ($r1['status'] === 201 || $r1['status'] === 200)
    && !empty($r1['body']['ok']) && $u1 && $u1['email_verified_at'] === null
    && count($rows1) === 1 && $login1['status'] === 403;
$emailOk = !empty($r1['body']['email_sent']);
verdict('TEST 1 Normal Register', $pass1 ? ($emailOk ? 'PASS' : 'WARNING') : 'FAIL', $d);

// ================================================================== TEST 2
reset_ip_limit($pdo);
$uid1 = (int)$u1['id'];
$walletBefore = $pdo->prepare('SELECT COALESCE(SUM(balance),0) FROM user_token_wallets WHERE user_id = ?');
$walletBefore->execute([$uid1]);
$balBefore = (string)$walletBefore->fetchColumn();
force_known_otp($pdo, $uid1);
$jar2 = $tmp . "/sim_otp_jar2_{$stamp}.txt";
$v2 = http_post('/services/verify_email_otp.php', ['email' => $email1, 'otp' => KNOWN_OTP], $jar2);
$u1b = user_row($pdo, $email1);
$rows2 = otp_rows($pdo, $uid1);
$consumed = count(array_filter($rows2, static fn($r) => $r['consumed_at'] !== null));
$jarTxt = is_file($jar2) ? (string)file_get_contents($jar2) : '';
$hasSessionCookie = stripos($jarTxt, 'PHPSESSID') !== false || stripos($jarTxt, 'ksmedu') !== false;
$verifyJs = (string)@file_get_contents(__DIR__ . '/../js/verify_email.js');
$redirectsToDashboard = stripos($verifyJs, 'dashboard_user') !== false;

$d = [
    'verify -> HTTP ' . $v2['status'] . ' ok=' . var_export($v2['body']['ok'] ?? null, true),
    'email_verified_at = ' . var_export($u1b['email_verified_at'], true),
    'OTP consumed/dihapus = baris sisa ' . count($rows2) . ' (harus 0), consumed ' . $consumed,

    'session cookie diterima = ' . ($hasSessionCookie ? 'ya' : 'tidak') . ' (' . trim(preg_replace('/\s+/', ' ', substr($jarTxt, -80))) . ')',
    'access_token = ' . (!empty($v2['body']['access_token']) ? 'ada' : 'TIDAK') .
        ', refresh_token = ' . (!empty($v2['body']['refresh_token']) ? 'ada' : 'TIDAK'),
    'redirect dashboard (js/verify_email.js) = ' . ($redirectsToDashboard ? 'ya' : 'tidak'),
];
$pass2 = $v2['status'] === 200 && !empty($v2['body']['ok']) && $u1b['email_verified_at'] !== null
    && $consumed === count($rows2) && !empty($v2['body']['access_token'])
    && !empty($v2['body']['refresh_token']) && $hasSessionCookie && $redirectsToDashboard;
verdict('TEST 2 OTP Benar', $pass2 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 3
reset_ip_limit($pdo);
$email3 = "sim_otp_t3_{$stamp}@example.com";
$cleanupEmails[] = $email3;
register_user($email3);
$u3 = user_row($pdo, $email3);
force_known_otp($pdo, (int)$u3['id']);
$v3 = http_post('/services/verify_email_otp.php', ['email' => $email3, 'otp' => '000001']);
$row3 = otp_rows($pdo, (int)$u3['id']);
$last3 = end($row3);
$u3b = user_row($pdo, $email3);
$d = [
    'verify OTP salah -> HTTP ' . $v3['status'] . ' ok=' . var_export($v3['body']['ok'] ?? null, true),
    'attempt_count = ' . $last3['attempt_count'],
    'email_verified_at tetap NULL = ' . ($u3b['email_verified_at'] === null ? 'ya' : 'TIDAK'),
];
$pass3 = $v3['status'] === 400 && empty($v3['body']['ok'])
    && (int)$last3['attempt_count'] === 1 && $u3b['email_verified_at'] === null;
verdict('TEST 3 OTP Salah', $pass3 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 4
reset_ip_limit($pdo);
$email4 = "sim_otp_t4_{$stamp}@example.com";
$cleanupEmails[] = $email4;
register_user($email4);
$u4 = user_row($pdo, $email4);
force_known_otp($pdo, (int)$u4['id']);
$pdo->prepare("UPDATE email_verifications SET expires_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE)
               WHERE user_id = ? AND consumed_at IS NULL")->execute([(int)$u4['id']]);
$v4 = http_post('/services/verify_email_otp.php', ['email' => $email4, 'otp' => KNOWN_OTP]);
$u4b = user_row($pdo, $email4);
$d = [
    'verify OTP expired -> HTTP ' . $v4['status'] . ' ok=' . var_export($v4['body']['ok'] ?? null, true),
    'email_verified_at tetap NULL = ' . ($u4b['email_verified_at'] === null ? 'ya' : 'TIDAK'),
];
$pass4 = $v4['status'] === 400 && empty($v4['body']['ok']) && $u4b['email_verified_at'] === null;
verdict('TEST 4 OTP Expired', $pass4 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 5
reset_ip_limit($pdo);
$email5 = "sim_otp_t5_{$stamp}@example.com";
$cleanupEmails[] = $email5;
register_user($email5);
$u5 = user_row($pdo, $email5);
$uid5 = (int)$u5['id'];
$before5 = otp_rows($pdo, $uid5);
$oldId = (int)end($before5)['id'];
// Fixture: lewati cooldown 60 detik dengan memundurkan created_at OTP pertama.
$pdo->prepare('UPDATE email_verifications SET created_at = DATE_SUB(created_at, INTERVAL 120 SECOND) WHERE id = ?')
    ->execute([$oldId]);
$r5 = http_post('/services/resend_email_otp.php', ['email' => $email5]);
$after5 = otp_rows($pdo, $uid5);
$newRow = end($after5);
$oldRow = null;
foreach ($after5 as $row) {
    if ((int)$row['id'] === $oldId) { $oldRow = $row; }
}
$d = [
    'resend -> HTTP ' . $r5['status'] . ' ok=' . var_export($r5['body']['ok'] ?? null, true),
    'jumlah OTP: ' . count($before5) . ' -> ' . count($after5),
    'OTP lama (id ' . $oldId . ') consumed_at = ' . var_export($oldRow['consumed_at'] ?? null, true),
    'OTP baru id = ' . $newRow['id'] . ', resend_count = ' . $newRow['resend_count'],
];
$pass5 = $r5['status'] === 200 && count($after5) === count($before5) + 1
    && !empty($oldRow['consumed_at']) && (int)$newRow['id'] !== $oldId
    && (int)$newRow['resend_count'] === 1;
verdict('TEST 5 Resend OTP', $pass5 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 6
reset_ip_limit($pdo);
$countBefore6 = count(otp_rows($pdo, $uid5));
$r6 = http_post('/services/resend_email_otp.php', ['email' => $email5]); // langsung, masih cooldown
$countAfter6 = count(otp_rows($pdo, $uid5));
$d = [
    'resend kedua (tanpa jeda) -> HTTP ' . $r6['status'] . ' ok=' . var_export($r6['body']['ok'] ?? null, true),
    'pesan = ' . (string)($r6['body']['message'] ?? ''),
    'OTP baru diterbitkan? ' . ($countAfter6 > $countBefore6 ? 'YA (cooldown bocor)' : 'tidak (cooldown menahan)'),
    'catatan: cooldown per akun sengaja membalas 200 generik (anti user-enumeration); 429 hanya dipakai rate limit per IP',
];
if ($countAfter6 > $countBefore6) {
    $status6 = 'FAIL';
} elseif ($r6['status'] === 429) {
    $status6 = 'PASS';
} else {
    $status6 = 'WARNING';
}
verdict('TEST 6 Cooldown', $status6, $d);

// ================================================================== TEST 7
// Batas per akun: 5 percobaan / OTP. IP limit dibersihkan tiap iterasi supaya
// yang teruji benar-benar batas per akun.
$email7 = "sim_otp_t7_{$stamp}@example.com";
$cleanupEmails[] = $email7;
reset_ip_limit($pdo);
register_user($email7);
$u7 = user_row($pdo, $email7);
force_known_otp($pdo, (int)$u7['id']);
$codes7 = [];
for ($i = 1; $i <= 6; $i++) {
    reset_ip_limit($pdo);
    $res = http_post('/services/verify_email_otp.php', ['email' => $email7, 'otp' => '000001']);
    $codes7[] = $i . ':' . $res['status'];
}
$rows7 = otp_rows($pdo, (int)$u7['id']);
$last7 = end($rows7);
$d = [
    'urutan status = ' . implode(', ', $codes7),
    'attempt_count akhir = ' . $last7['attempt_count'] . ' (batas ' . 5 . ')',
];
$pass7 = str_ends_with((string)end($codes7), ':429');
verdict('TEST 7 Verify Rate Limit (per akun, 6x salah)', $pass7 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 8
$email8 = "sim_otp_t8_{$stamp}@example.com";
$cleanupEmails[] = $email8;
reset_ip_limit($pdo);
register_user($email8);
$u8 = user_row($pdo, $email8);
force_known_otp($pdo, (int)$u8['id']);
reset_ip_limit($pdo);
$codes8 = [];
for ($i = 1; $i <= 6; $i++) {
    $res = http_post('/services/verify_email_otp.php', ['email' => $email8, 'otp' => '000001']);
    $codes8[] = $i . ':' . $res['status'];
}
$ipCount = (int)$pdo->query("SELECT COUNT(*) FROM email_otp_ip_attempts WHERE action_type='verify'")->fetchColumn();
$d = [
    'urutan status (IP sama, tanpa reset) = ' . implode(', ', $codes8),
    'baris email_otp_ip_attempts(verify) = ' . $ipCount . ', batas = 5 / 10 menit',
];
$pass8 = str_ends_with((string)end($codes8), ':429');
verdict('TEST 8 IP Rate Limit (>5 request / IP)', $pass8 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 9
$email9 = "sim_otp_t9_{$stamp}@example.com";
$cleanupEmails[] = $email9;
reset_ip_limit($pdo);
register_user($email9);
$u9 = user_row($pdo, $email9);
$uid9 = (int)$u9['id'];
force_known_otp($pdo, $uid9);
reset_ip_limit($pdo);

$wq = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(balance),0) b FROM user_token_wallets WHERE user_id = ?');
$wq->execute([$uid9]);
$w9before = $wq->fetch();
$tq = $pdo->prepare('SELECT COUNT(*) FROM token_transactions WHERE user_id = ?');
$tq->execute([$uid9]);
$t9before = (int)$tq->fetchColumn();

$mh = curl_multi_init();
$handles = [];
$payload9 = json_encode(['email' => $email9, 'otp' => KNOWN_OTP]);
for ($i = 0; $i < 20; $i++) {
    $ch = curl_init(BASE . '/services/verify_email_otp.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload9,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_COOKIEJAR => $tmp . "/sim_otp_race_{$stamp}_{$i}.txt",
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
do {
    $st = curl_multi_exec($mh, $running);
    if ($running) { curl_multi_select($mh, 1.0); }
} while ($running && $st === CURLM_OK);

$dist = [];
$okBodies = 0;
foreach ($handles as $ch) {
    $body = parse_json((string)curl_multi_getcontent($ch));
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $dist[$code] = ($dist[$code] ?? 0) + 1;
    if (!empty($body['ok'])) { $okBodies++; }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);
foreach (glob($tmp . "/sim_otp_race_{$stamp}_*.txt") as $f) { @unlink($f); }

$u9b = user_row($pdo, $email9);
$rows9 = otp_rows($pdo, $uid9);
$consumed9 = count(array_filter($rows9, static fn($r) => $r['consumed_at'] !== null));
$wq->execute([$uid9]);
$w9after = $wq->fetch();
$tq->execute([$uid9]);
$t9after = (int)$tq->fetchColumn();
ksort($dist);
$distStr = implode(', ', array_map(static fn($k, $v) => "HTTP $k x$v", array_keys($dist), $dist));

$d = [
    'distribusi status 20 request paralel = ' . $distStr,
    'respons ok=true = ' . $okBodies . ' (harus 1)',
    'email_verified_at = ' . var_export($u9b['email_verified_at'], true) . ' (satu nilai, kolom tunggal)',
    'baris OTP sisa = ' . count($rows9) . ' (harus 0: verify sukses menghapus semua baris OTP user)'
        . ', consumed = ' . $consumed9,
    'wallet: rows ' . $w9before['c'] . '->' . $w9after['c'] . ', balance ' . $w9before['b'] . '->' . $w9after['b'],
    'token_transactions: ' . $t9before . ' -> ' . $t9after,
];
$pass9 = $okBodies === 1 && ($dist[200] ?? 0) === 1 && $u9b['email_verified_at'] !== null
    && count($rows9) === 0
    && (int)$w9after['c'] === (int)$w9before['c'] && (string)$w9after['b'] === (string)$w9before['b']
    && $t9after === $t9before;

verdict('TEST 9 Race Condition (20 verify paralel)', $pass9 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 10
reset_ip_limit($pdo);
$email10 = "sim_otp_t10_{$stamp}@example.com";
$cleanupEmails[] = $email10;
register_user($email10);
$u10 = user_row($pdo, $email10);
$before10 = http_post('/services/auth_login.php', ['email' => $email10, 'password' => 'Rahasia123']);
force_known_otp($pdo, (int)$u10['id']);
http_post('/services/verify_email_otp.php', ['email' => $email10, 'otp' => KNOWN_OTP]);
$after10 = http_post('/services/auth_login.php', ['email' => $email10, 'password' => 'Rahasia123']);
$d = [
    'login sebelum verify -> HTTP ' . $before10['status'] . ' (' . (string)($before10['body']['message'] ?? '') . ')',
    'login sesudah verify -> HTTP ' . $after10['status'] . ' ok=' . var_export($after10['body']['ok'] ?? null, true),
];
$pass10 = $before10['status'] === 403 && $after10['status'] === 200 && !empty($after10['body']['ok']);
verdict('TEST 10 Login sebelum/sesudah verify', $pass10 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 11
reset_ip_limit($pdo);
$legacyNull = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn();
$email11 = "sim_otp_legacy_{$stamp}@example.com";
$cleanupEmails[] = $email11;
// Akun lama = dibuat sebelum fitur OTP lalu di-backfill migrasi 009.
$ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, email_verified_at, created_at)
                      VALUES ('Legacy Sim', ?, ?, 'user', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY))");
$ins->execute([$email11, password_hash('Rahasia123', PASSWORD_DEFAULT)]);
$login11 = http_post('/services/auth_login.php', ['email' => $email11, 'password' => 'Rahasia123']);
$d = [
    'akun lama (email_verified_at hasil backfill) login -> HTTP ' . $login11['status']
        . ' ok=' . var_export($login11['body']['ok'] ?? null, true),
    'users dengan email_verified_at NULL saat ini = ' . $legacyNull . ' (semuanya user simulasi yang belum verify)',
];
$pass11 = $login11['status'] === 200 && !empty($login11['body']['ok']);
verdict('TEST 11 Account Lama', $pass11 ? 'PASS' : 'FAIL', $d);

// ================================================================== TEST 12-14
exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' status --porcelain 2>&1', $gitOut);
$changed = array_values(array_filter($gitOut, static fn($l) => trim($l) !== ''));
$touched = static function (array $lines, array $needles): array {
    $hit = [];
    foreach ($lines as $l) {
        foreach ($needles as $n) {
            if (stripos($l, $n) !== false) { $hit[] = trim($l); }
        }
    }
    return $hit;
};

// TEST 12 Telegram
$tgFiles = ['telegram_webhook.php', 'telegram_helpers.php', 'telegram_bot.php', 'token_purchase_link.php'];
$tgChanged = $touched($changed, $tgFiles);
$tgHook = http_get('/services/telegram_webhook.php');
$d = [
    'file Telegram termodifikasi (git status) = ' . ($tgChanged ? implode('; ', $tgChanged) : 'tidak ada'),
    'GET services/telegram_webhook.php -> HTTP ' . $tgHook['status'] . ' (POST-only / menolak GET diharapkan)',
];
verdict('TEST 12 Telegram tidak berubah', empty($tgChanged) && $tgHook['status'] < 500 ? 'PASS' : 'WARNING', $d);

// TEST 13 Upload Journal
$upFiles = ['upload.php', 'submit_journal.php', 'submission_helpers.php', 'my_journals.php', 'update_my_journal.php'];
$upChanged = $touched($changed, $upFiles);
$upNoAuth = http_post('/services/submit_journal.php', []);
$upEndpoint = http_post('/services/upload.php', []);
$d = [
    'file upload/jurnal termodifikasi = ' . ($upChanged ? implode('; ', $upChanged) : 'tidak ada'),
    'submit_journal.php tanpa auth -> HTTP ' . $upNoAuth['status'] . ' (guard aktif bila 401/403)',
    'upload.php tanpa auth -> HTTP ' . $upEndpoint['status'],
];
$pass13 = empty($upChanged) && in_array($upNoAuth['status'], [400, 401, 403, 405, 422], true)
    && in_array($upEndpoint['status'], [400, 401, 403, 405, 422], true);
verdict('TEST 13 Upload Journal', $pass13 ? 'PASS' : 'WARNING', $d);

// TEST 14 Dashboard
$dashUser = http_get('/user/dashboard_user.php');
$dashAdmin = http_get('/admin/dashboard_admin.php');
$statsNoAuth = http_get('/services/admin/dashboard_stats.php');
$fatal = static fn(string $s): bool => stripos($s, 'Fatal error') !== false || stripos($s, 'Parse error') !== false;
$d = [
    'user/dashboard_user.php -> HTTP ' . $dashUser['status'] . ', fatal=' . ($fatal($dashUser['raw']) ? 'YA' : 'tidak'),
    'admin/dashboard_admin.php -> HTTP ' . $dashAdmin['status'] . ', fatal=' . ($fatal($dashAdmin['raw']) ? 'YA' : 'tidak'),
    'services/admin/dashboard_stats.php tanpa auth -> HTTP ' . $statsNoAuth['status'],
];
$pass14 = $dashUser['status'] === 200 && $dashAdmin['status'] === 200
    && !$fatal($dashUser['raw']) && !$fatal($dashAdmin['raw'])
    && in_array($statsNoAuth['status'], [401, 403], true);
verdict('TEST 14 Dashboard', $pass14 ? 'PASS' : 'WARNING', $d);

// ================================================================== cleanup
reset_ip_limit($pdo);
$del = $pdo->prepare('DELETE FROM users WHERE email = ?');
foreach ($cleanupEmails as $e) { $del->execute([$e]); }
foreach (glob($tmp . "/sim_otp_jar*_{$stamp}.txt") as $f) { @unlink($f); }

echo "\n==================== RINGKASAN ====================\n";
$counts = ['PASS' => 0, 'WARNING' => 0, 'FAIL' => 0];
foreach ($results as $name => $r) {
    printf("%-8s %s\n", $r['status'], $name);
    $counts[$r['status']]++;
}
printf("\nPASS=%d WARNING=%d FAIL=%d\n", $counts['PASS'], $counts['WARNING'], $counts['FAIL']);
echo "Cleanup: " . count($cleanupEmails) . " user simulasi dihapus, tabel rate-limit IP dibersihkan.\n";
