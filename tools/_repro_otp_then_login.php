<?php
/**
 * tools/_repro_otp_then_login.php — reproduksi bug laporan produksi:
 *
 *   register -> verifikasi OTP (auto login) -> buka halaman profil (auth_me.php)
 *   -> login ulang dengan email yang sama.
 *
 * HANYA alat diagnosa lokal. Tidak mengubah logika aplikasi. Manipulasi DB
 * terbatas pada fixture pengujian (menulis otp_hash yang diketahui, reset
 * tabel rate limit) dan penghapusan user uji pada akhir skrip.
 *
 * Jalankan: php tools/_repro_otp_then_login.php
 */

require_once __DIR__ . '/../services/env_loader.php';

const BASE      = 'http://localhost/ksmedu';
const KNOWN_OTP = '424242';
const PASSWORD  = 'Rahasia123';

function env_or(string $key, string $default): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string)$v;
}

function pdo_conn(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        env_or('DB_HOST', 'localhost'),
        env_or('DB_NAME', 'journal_system2')
    );
    $pdo = new PDO($dsn, env_or('DB_USER', 'root'), env_or('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $tz = env_or('APP_TIMEZONE', 'Asia/Jakarta');
    $pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone($tz)))->format('P') . "'");

    return $pdo;
}

/** Body endpoint bisa didahului PHP warning saat display_errors aktif. */
function parse_json(string $body): ?array
{
    $pos = strpos($body, '{');
    if ($pos === false) {
        return null;
    }
    $decoded = json_decode(substr($body, $pos), true);

    return is_array($decoded) ? $decoded : null;
}

function http_call(string $method, string $path, ?array $payload = null, array $headers = [], ?string $jar = null): array
{
    $ch = curl_init(BASE . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        $headers[] = 'Content-Type: application/json';
    }
    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    if ($jar !== null) {
        $opts[CURLOPT_COOKIEJAR]  = $jar;
        $opts[CURLOPT_COOKIEFILE] = $jar;
    }
    curl_setopt_array($ch, $opts);
    $body   = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => parse_json($body), 'raw' => $body];
}

function show(string $label, array $res): void
{
    printf("%-42s HTTP %d\n", $label, $res['status']);
    if ($res['body'] === null) {
        echo '    raw: ' . trim(preg_replace('/\s+/', ' ', substr($res['raw'], 0, 300))) . "\n";
        return;
    }
    echo '    ok=' . var_export($res['body']['ok'] ?? null, true);
    if (isset($res['body']['message'])) {
        echo ' message=' . $res['body']['message'];
    }
    if (isset($res['body']['needs_verification'])) {
        echo ' needs_verification=' . var_export($res['body']['needs_verification'], true);
    }
    if (isset($res['body']['user'])) {
        echo ' user=' . json_encode($res['body']['user']);
    }
    if (isset($res['body']['auth_method'])) {
        echo ' auth_method=' . $res['body']['auth_method'];
    }
    echo "\n";
}

$pdo = pdo_conn();

$ping = http_call('GET', '/user/login_user.php');
if ($ping['status'] === 0) {
    echo "ABORT: Apache di " . BASE . " tidak merespons.\n";
    exit(1);
}

$email = 'repro_otp_' . date('YmdHis') . '@example.com';
echo "Email uji: {$email}\n\n";

// ---------------------------------------------------------------- 1. register
$pdo->exec('DELETE FROM email_otp_ip_attempts');
$reg = http_call('POST', '/services/auth_register.php', [
    'name' => 'Repro OTP', 'email' => $email, 'password' => PASSWORD,
]);
show('1. auth_register.php', $reg);

$row = $pdo->prepare('SELECT id, email, email_verified_at, account_status FROM users WHERE email = ? LIMIT 1');
$row->execute([$email]);
$user = $row->fetch();
if (!$user) {
    echo "ABORT: user tidak dibuat oleh registrasi.\n";
    exit(1);
}
$userId = (int)$user['id'];
echo "    users.id={$userId} email=" . var_export($user['email'], true)
    . " account_status=" . var_export($user['account_status'], true)
    . " email_verified_at=" . var_export($user['email_verified_at'], true) . "\n\n";

// -------------------------------------------------------------- 2. verify OTP
$pdo->prepare(
    'UPDATE email_verifications SET otp_hash = ?
     WHERE user_id = ? AND consumed_at IS NULL
     ORDER BY id DESC LIMIT 1'
)->execute([password_hash(KNOWN_OTP, PASSWORD_DEFAULT), $userId]);

$jar = sys_get_temp_dir() . '/repro_otp_jar_' . $userId . '.txt';
@unlink($jar);
$verify = http_call('POST', '/services/verify_email_otp.php', ['email' => $email, 'otp' => KNOWN_OTP], [], $jar);
show('2. verify_email_otp.php', $verify);

$accessToken = $verify['body']['access_token'] ?? null;
echo '    access_token=' . ($accessToken ? 'ada' : 'TIDAK')
    . ' refresh_token=' . (!empty($verify['body']['refresh_token']) ? 'ada' : 'TIDAK') . "\n";

$row->execute([$email]);
$afterVerify = $row->fetch();
echo '    email_verified_at setelah verify=' . var_export($afterVerify['email_verified_at'] ?? null, true) . "\n\n";

// ------------------------------- 3. halaman profil: auth_me.php (Bearer + cookie)
$me1 = http_call('GET', '/services/auth_me.php', null, $accessToken ? ['Authorization: Bearer ' . $accessToken] : []);
show('3a. auth_me.php (Bearer token)', $me1);

$me2 = http_call('GET', '/services/auth_me.php', null, [], $jar);
show('3b. auth_me.php (session cookie)', $me2);
echo "\n";

// -------------------------------------------------------------- 4. login ulang
$login = http_call('POST', '/services/auth_login.php', ['email' => $email, 'password' => PASSWORD]);
show('4. auth_login.php (email sama)', $login);

// variasi kapitalisasi email, seperti user mengetik ulang
$loginUpper = http_call('POST', '/services/auth_login.php', [
    'email' => strtoupper(substr($email, 0, 1)) . substr($email, 1),
    'password' => PASSWORD,
]);
show('4b. auth_login.php (huruf besar awal)', $loginUpper);

// ------------------------------------------------------------------- cleanup
echo "\nCleanup...\n";
try {
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM user_token_wallets WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    $pdo->exec('DELETE FROM email_otp_ip_attempts');
    @unlink($jar);
    echo "Selesai.\n";
} catch (Throwable $e) {
    echo 'Cleanup gagal: ' . $e->getMessage() . "\n";
}
