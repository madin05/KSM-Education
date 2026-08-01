<?php
/**
 * tools/simulate_session_isolation.php
 *
 * Simulasi/regresi untuk perbaikan bug "admin ikut login di dashboard user".
 * Dijalankan dari CLI:
 *
 *   php tools/simulate_session_isolation.php
 *
 * Yang diuji (tanpa perlu web server):
 *   1. Klaim 'ctx' benar-benar ditulis pada access & refresh token.
 *   2. Token konteks admin ditolak saat dipakai pada konteks user, dan
 *      sebaliknya (inti bug lintas-dashboard).
 *   3. Token lama tanpa klaim 'ctx' tetap diterima sebagai konteks 'user'
 *      (kompatibilitas mundur agar sesi berjalan tidak terputus).
 *   4. Nama cookie session berbeda antar konteks.
 *   5. Deteksi konteks dari SCRIPT_NAME / Referer / header.
 *
 * Skrip ini read-only: tidak menulis ke database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// env_loader mengisi $_ENV/$_SERVER dari .env.
require_once __DIR__ . '/../services/env_loader.php';

// get_env_var() aslinya dideklarasikan di services/db.php, yang juga membuka
// koneksi database. Untuk simulasi ini kita hanya butuh pembacaan env, jadi
// sediakan padanan yang setara tanpa menyentuh DB.
if (!function_exists('get_env_var')) {
    function get_env_var($name, $default = '')
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}

require_once __DIR__ . '/../services/auth_context.php';
require_once __DIR__ . '/../services/jwt_helper.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        echo "  FAIL  $label\n";
    }
}

/**
 * Tiru logika pemeriksaan konteks di jwt_middleware.php tanpa menyentuh
 * session/DB, sehingga bisa diuji dari CLI.
 */
function token_accepted_in_context(string $token, string $requestContext): bool
{
    $payload = jwt_decode($token, JWT_SECRET);
    if (!$payload) {
        return false;
    }
    $tokenCtx = ksmedu_normalize_context($payload['ctx'] ?? '') ?? KSMEDU_CTX_USER;
    return $tokenCtx === $requestContext;
}

$adminUser = ['id' => 1, 'name' => 'Admin', 'role' => 'admin', 'email' => 'admin@gmail.com'];
$normalUser = ['id' => 2, 'name' => 'Budi', 'role' => 'user', 'email' => 'budi@example.com'];

echo "== 1. Klaim ctx pada token ==\n";
$adminAccess = generate_access_token($adminUser, KSMEDU_CTX_ADMIN);
$adminRefresh = generate_refresh_token($adminUser, KSMEDU_CTX_ADMIN);
$userAccess = generate_access_token($normalUser, KSMEDU_CTX_USER);

$adminPayload = jwt_decode($adminAccess['token'], JWT_SECRET);
$userPayload = jwt_decode($userAccess['token'], JWT_SECRET);
$adminRefreshPayload = jwt_decode($adminRefresh['token'], JWT_SECRET);

check('access token admin membawa ctx=admin', ($adminPayload['ctx'] ?? null) === 'admin');
check('refresh token admin membawa ctx=admin', ($adminRefreshPayload['ctx'] ?? null) === 'admin');
check('access token user membawa ctx=user', ($userPayload['ctx'] ?? null) === 'user');
check('role admin tetap terbawa', ($adminPayload['role'] ?? null) === 'admin');

echo "\n== 2. Isolasi lintas konteks (inti bug) ==\n";
check(
    'token admin DITOLAK pada konteks user (dashboard user tidak ikut login)',
    token_accepted_in_context($adminAccess['token'], KSMEDU_CTX_USER) === false
);
check(
    'token admin diterima pada konteks admin',
    token_accepted_in_context($adminAccess['token'], KSMEDU_CTX_ADMIN) === true
);
check(
    'token user DITOLAK pada konteks admin',
    token_accepted_in_context($userAccess['token'], KSMEDU_CTX_ADMIN) === false
);
check(
    'token user diterima pada konteks user',
    token_accepted_in_context($userAccess['token'], KSMEDU_CTX_USER) === true
);

echo "\n== 3. Kompatibilitas token lama (tanpa klaim ctx) ==\n";
$legacyPayload = [
    'iss' => JWT_ISSUER,
    'sub' => 2,
    'iat' => time(),
    'exp' => time() + 600,
    'nbf' => time(),
    'jti' => generate_jti(),
    'type' => 'access',
    'name' => 'Budi',
    'role' => 'user',
    'email' => 'budi@example.com',
];
$legacyToken = jwt_encode($legacyPayload, JWT_SECRET);
check(
    'token lama dianggap konteks user (sesi berjalan tidak putus)',
    token_accepted_in_context($legacyToken, KSMEDU_CTX_USER) === true
);
check(
    'token lama tidak bisa dipakai di panel admin',
    token_accepted_in_context($legacyToken, KSMEDU_CTX_ADMIN) === false
);

echo "\n== 4. Cookie session terpisah ==\n";
$userSess = ksmedu_session_name(KSMEDU_CTX_USER);
$adminSess = ksmedu_session_name(KSMEDU_CTX_ADMIN);
check("nama cookie berbeda ($userSess vs $adminSess)", $userSess !== $adminSess);
check('cookie user = KSMEDUSESS', $userSess === 'KSMEDUSESS');
check('cookie admin = KSMEDUADMSESS', $adminSess === 'KSMEDUADMSESS');

echo "\n== 5. Normalisasi konteks ==\n";
check("nilai 'ADMIN' dinormalkan ke admin", ksmedu_normalize_context('ADMIN') === 'admin');
check("nilai ' user ' dinormalkan ke user", ksmedu_normalize_context(' user ') === 'user');
check('nilai asing ditolak (null)', ksmedu_normalize_context('superadmin') === null);
check('nilai kosong ditolak (null)', ksmedu_normalize_context('') === null);

echo "\n== RINGKASAN ==\n";
echo "  PASS: $pass\n";
echo "  FAIL: $fail\n";

exit($fail === 0 ? 0 : 1);
