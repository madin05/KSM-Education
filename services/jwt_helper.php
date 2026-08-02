<?php
/**
 * JWT Helper — Pure PHP JWT Implementation (No External Dependencies)
 * 
 * Implements RFC 7519 JSON Web Token with HMAC-SHA256 signing.
 * Provides access token (30 min) + refresh token (7 days) pattern.
 * 
 * @package KSM Education
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/auth_context.php';


// ===== JWT CONFIGURATION =====
$jwt_secret_val = get_env_var('JWT_SECRET', '');
$jwt_secret_is_placeholder = preg_match('/change[_-]?this|ch4ng3[_-]?th1s|replace[_-]?with|your[_-]?secret/i', $jwt_secret_val) === 1;
if (strlen($jwt_secret_val) < 32 || $jwt_secret_is_placeholder) {
    error_log('CRITICAL: JWT_SECRET is not configured properly in .env file!');
    if (php_sapi_name() !== 'cli') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'JWT configuration error.']);
        exit;
    }
}
define('JWT_SECRET', $jwt_secret_val);
define('JWT_ACCESS_EXPIRY', (int) get_env_var('JWT_ACCESS_EXPIRY', '1800'));      // 30 minutes
define('JWT_REFRESH_EXPIRY', (int) get_env_var('JWT_REFRESH_EXPIRY', '604800'));  // 7 days
define('JWT_ISSUER', 'ksm-education');
define('JWT_ALGORITHM', 'HS256');

// Rentang waktu (detik) setelah sebuah refresh token dirotasi, di mana token
// lama masih boleh dipakai sekali lagi tanpa dianggap serangan. Ini menutup
// race condition normal: beberapa tab/permintaan paralel bisa mengirim refresh
// token yang sama dalam hitungan milidetik sebelum salah satunya selesai
// menyimpan token pengganti.
define('JWT_ROTATION_GRACE', (int) get_env_var('JWT_ROTATION_GRACE', '30'));


// ===== BASE64URL ENCODING (RFC 4648) =====

/**
 * Base64URL encode (URL-safe, no padding)
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64URL decode
 */
function base64url_decode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

// ===== JWT CORE FUNCTIONS =====

/**
 * Encode (sign) a JWT token
 * 
 * @param array  $payload The claims (data) to include in the token
 * @param string $secret  The secret key for HMAC signing
 * @return string The signed JWT token string
 */
function jwt_encode(array $payload, string $secret): string {
    // Header
    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM
    ];

    // Encode header & payload
    $headerEncoded = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadEncoded = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

    // Create signature
    $signatureInput = $headerEncoded . '.' . $payloadEncoded;
    $signature = hash_hmac('sha256', $signatureInput, $secret, true);
    $signatureEncoded = base64url_encode($signature);

    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}

/**
 * Decode and validate a JWT token
 * 
 * @param string $token  The JWT token string
 * @param string $secret The secret key for HMAC verification
 * @return array|false   The decoded payload, or false on failure
 */
function jwt_decode(string $token, string $secret) {
    // Split token
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    // Verify signature
    $signatureInput = $headerEncoded . '.' . $payloadEncoded;
    $expectedSignature = base64url_encode(hash_hmac('sha256', $signatureInput, $secret, true));

    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        return false; // Invalid signature
    }

    // Decode header
    $header = json_decode(base64url_decode($headerEncoded), true);
    if (!$header || !isset($header['alg']) || $header['alg'] !== JWT_ALGORITHM) {
        return false; // Unsupported algorithm
    }

    // Decode payload
    $payload = json_decode(base64url_decode($payloadEncoded), true);
    if (!$payload) {
        return false; // Invalid payload
    }

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Token expired
    }

    // Check "not before" claim
    if (isset($payload['nbf']) && $payload['nbf'] > time()) {
        return false; // Token not yet valid
    }

    // Verify issuer
    if (isset($payload['iss']) && $payload['iss'] !== JWT_ISSUER) {
        return false; // Invalid issuer
    }

    return $payload;
}

// ===== TOKEN GENERATION =====

/**
 * Generate a unique JWT ID (jti)
 */
function generate_jti(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Ambil generasi sesi (token_version) untuk dimasukkan sebagai klaim `tv`.
 *
 * Nilai diambil dari baris user bila sudah tersedia; kalau tidak, dibaca dari
 * DB agar pemanggil lama (yang hanya meneruskan id/name/role) tetap ikut
 * mendapat klaim ini.
 *
 * Mengembalikan null bila kolom token_version belum ada (migrasi 012 belum
 * dijalankan). Token tanpa klaim `tv` tetap diterima oleh verifikator, jadi
 * penerapan migrasi tidak wajib serentak dengan deploy kode.
 *
 * @param array $user Baris user, minimal berisi 'id'.
 */
function ksmedu_resolve_token_version(array $user): ?int {
    if (isset($user['token_version']) && $user['token_version'] !== '') {
        return (int) $user['token_version'];
    }
    if (empty($user['id'])) {
        return null;
    }

    try {
        global $pdo;
        if (!$pdo) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT token_version FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $user['id']]);
        $tv = $stmt->fetchColumn();
        return ($tv === false || $tv === null) ? null : (int) $tv;
    } catch (Exception $e) {
        // Kolom/tabel belum siap: jalankan tanpa klaim tv.
        return null;
    }
}

/**
 * Naikkan token_version user sehingga SELURUH access & refresh token yang
 * sudah beredar untuk user tersebut langsung tidak berlaku.
 *
 * Dipakai saat reuse refresh token terdeteksi (indikasi token dicuri).
 *
 * @return bool true bila generasi berhasil dinaikkan.
 */
function revoke_all_user_sessions(int $userId, string $reason = 'reuse_detected'): bool {
    try {
        global $pdo;
        if (!$pdo) {
            return false;
        }
        $stmt = $pdo->prepare("UPDATE users SET token_version = token_version + 1 WHERE id = ?");
        $stmt->execute([$userId]);
        error_log(sprintf('Session revocation for user %d (reason: %s)', $userId, $reason));
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('revoke_all_user_sessions failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cek apakah klaim `tv` pada token masih sesuai generasi sesi user saat ini.
 *
 * FAIL CLOSED: bila status tidak dapat dipastikan (query gagal), token
 * dianggap tidak valid. Ini konsisten dengan pemeriksaan revokasi lain di
 * proyek ini — kegagalan infrastruktur tidak boleh menjadi celah otorisasi.
 *
 * Token lama tanpa klaim `tv` dianggap valid agar sesi yang sedang berjalan
 * tidak terputus saat fitur ini dirilis; token tersebut akan hilang sendiri
 * setelah rotasi/kedaluwarsa berikutnya.
 */
function jwt_token_version_valid(array $payload): bool {
    if (!isset($payload['tv'])) {
        return true;
    }
    if (empty($payload['sub'])) {
        return false;
    }

    try {
        global $pdo;
        if (!$pdo) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT token_version FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $payload['sub']]);
        $current = $stmt->fetchColumn();
        if ($current === false || $current === null) {
            return false;
        }
        return (int) $current === (int) $payload['tv'];
    } catch (Exception $e) {
        error_log('Token version check failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * Generate an Access Token (short-lived, 30 minutes default)
 * 
 * @param array       $user    User data ['id', 'name', 'role', 'email']
 * @param string|null $context Konteks penerbit token ('admin'|'user').
 *                             Default: konteks request saat ini.
 * @return array ['token' => string, 'expires_in' => int, 'jti' => string]
 */
function generate_access_token(array $user, ?string $context = null): array {
    $jti = generate_jti();
    $now = time();
    $ctx = ksmedu_normalize_context($context) ?? ksmedu_request_context();

    $payload = [
        'iss' => JWT_ISSUER,
        'sub' => (int) $user['id'],
        'iat' => $now,
        'exp' => $now + JWT_ACCESS_EXPIRY,
        'nbf' => $now,
        'jti' => $jti,
        'type' => 'access',
        'ctx' => $ctx,
        'name' => $user['name'] ?? '',
        'role' => $user['role'] ?? 'user',
        'email' => $user['email'] ?? ''
    ];

    // Access token juga membawa `tv` supaya pencabutan sesi (reuse detection)
    // langsung mematikan access token yang masih hidup, bukan hanya memblokir
    // refresh berikutnya.
    $tv = ksmedu_resolve_token_version($user);
    if ($tv !== null) {
        $payload['tv'] = $tv;
    }

    return [
        'token' => jwt_encode($payload, JWT_SECRET),
        'expires_in' => JWT_ACCESS_EXPIRY,
        'jti' => $jti
    ];
}


/**
 * Generate a Refresh Token (long-lived, 7 days default)
 * 
 * @param array       $user    User data ['id', 'role']
 * @param string|null $context Konteks penerbit token ('admin'|'user').
 * @return array ['token' => string, 'expires_in' => int, 'jti' => string]
 */
function generate_refresh_token(array $user, ?string $context = null): array {
    $jti = generate_jti();
    $now = time();
    $ctx = ksmedu_normalize_context($context) ?? ksmedu_request_context();

    $payload = [
        'iss' => JWT_ISSUER,
        'sub' => (int) $user['id'],
        'iat' => $now,
        'exp' => $now + JWT_REFRESH_EXPIRY,
        'nbf' => $now,
        'jti' => $jti,
        'type' => 'refresh',
        'ctx' => $ctx,
        'role' => $user['role'] ?? 'user'
    ];

    $tv = ksmedu_resolve_token_version($user);
    if ($tv !== null) {
        $payload['tv'] = $tv;
    }

    return [
        'token' => jwt_encode($payload, JWT_SECRET),
        'expires_in' => JWT_REFRESH_EXPIRY,
        'jti' => $jti
    ];
}

/**
 * Terbitkan refresh token pengganti sekaligus mencabut yang lama (rotation).
 *
 * Urutan penting: token lama dicatat sebagai 'rotated' LEBIH DULU, baru token
 * baru diterbitkan. Bila proses gagal di tengah, klien akan gagal refresh dan
 * harus login ulang — kondisi yang aman. Sebaliknya, bila token baru terbit
 * tapi yang lama tidak tercabut, dua token valid akan hidup bersamaan.
 *
 * @param array $user     Baris user (harus berisi id, role, token_version).
 * @param array $oldClaims Payload refresh token yang sedang dipakai.
 * @return array|null Hasil generate_refresh_token(), atau null bila pencabutan
 *                    token lama gagal.
 */
function rotate_refresh_token(array $user, array $oldClaims, ?string $context = null): ?array {
    if (!isset($oldClaims['jti'], $oldClaims['exp'])) {
        return null;
    }
    if (!blacklist_token((string) $oldClaims['jti'], (int) $oldClaims['exp'], 'rotated')) {
        return null;
    }
    return generate_refresh_token($user, $context);
}


// ===== TOKEN VALIDATION =====

/**
 * Extract Bearer token from Authorization header
 * 
 * @return string|null The token, or null if not found
 */
function get_bearer_token(): ?string {
    // Check Authorization header
    $authHeader = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        // Apache redirect workaround
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }

    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

/**
 * Validate JWT from the Authorization header
 * Returns the decoded payload if valid, null otherwise.
 * Does NOT send any HTTP response — caller must handle auth failure.
 * 
 * @return array|null Decoded JWT payload, or null if invalid/missing
 */
function validate_jwt(): ?array {
    $token = get_bearer_token();
    if (!$token) {
        return null;
    }

    $payload = jwt_decode($token, JWT_SECRET);
    if (!$payload) {
        return null;
    }

    // Ensure it's an access token (not a refresh token)
    if (isset($payload['type']) && $payload['type'] !== 'access') {
        return null;
    }

    // Check blacklist (if database is available)
    if (isset($payload['jti'])) {
        try {
            global $pdo;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT id FROM jwt_blacklist WHERE token_jti = ? LIMIT 1");
                $stmt->execute([$payload['jti']]);
                if ($stmt->fetch()) {
                    return null; // Token has been revoked
                }
            }
        } catch (Exception $e) {
            // Table might not exist yet — skip blacklist check
        }
    }

    // Generasi sesi: menolak token dari generasi lama (mis. setelah reuse
    // refresh token terdeteksi dan seluruh sesi user dicabut).
    if (!jwt_token_version_valid($payload)) {
        return null;
    }

    return $payload;
}

/**
 * Cari catatan pencabutan sebuah jti pada blacklist.
 *
 * @return array{reason:string,revoked_age:int}|null null bila jti tidak ada di
 *         blacklist. revoked_age = detik sejak dicabut (0 bila tidak diketahui).
 * @throws Exception bila blacklist tidak dapat dibaca — pemanggil harus
 *         memutuskan (fail closed), bukan menganggap token bersih.
 */
function find_blacklisted_token(string $jti): ?array {
    global $pdo;
    if (!$pdo) {
        throw new Exception('Database tidak tersedia untuk pemeriksaan blacklist.');
    }

    // TIMESTAMPDIFF dihitung di MySQL agar tidak terpengaruh selisih
    // date.timezone PHP vs time_zone MySQL.
    $stmt = $pdo->prepare(
        "SELECT reason, TIMESTAMPDIFF(SECOND, COALESCE(revoked_at, NOW()), NOW()) AS revoked_age
         FROM jwt_blacklist WHERE token_jti = ? LIMIT 1"
    );
    $stmt->execute([$jti]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'reason' => (string) ($row['reason'] ?? 'logout'),
        'revoked_age' => (int) ($row['revoked_age'] ?? 0),
    ];
}

/**
 * Blacklist a token JTI (used during logout and refresh rotation)
 * 
 * @param string $jti       The JWT ID to blacklist
 * @param int    $expiresAt Unix timestamp when the token expires
 * @param string $reason    'logout' | 'rotated' | 'reuse_detected'
 * @return bool Success
 */
function blacklist_token(string $jti, int $expiresAt, string $reason = 'logout'): bool {
    try {
        global $pdo;
        if (!$pdo) return false;

        // Insert into blacklist.
        // expires_at dihitung oleh MySQL (FROM_UNIXTIME) supaya nilainya selalu
        // sebanding dengan NOW() saat housekeeping/pembacaan, terlepas dari
        // perbedaan date.timezone PHP dan time_zone MySQL.
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO jwt_blacklist (token_jti, expires_at, reason, revoked_at)
             VALUES (?, FROM_UNIXTIME(?), ?, NOW())"
        );
        $stmt->execute([$jti, $expiresAt, $reason]);


        // Housekeeping dipisahkan dari jalur insert: dijalankan probabilistik
        // (±2% request) agar baris yang baru masuk tidak pernah ikut terhapus
        // pada transaksi yang sama, dan agar logout tetap cepat.
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM jwt_blacklist WHERE expires_at < NOW()");
        }

        return true;
    } catch (Exception $e) {
        error_log("JWT Blacklist error: " . $e->getMessage());
        return false;
    }
}
