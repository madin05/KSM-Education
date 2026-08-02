<?php
/**
 * POST /services/auth_refresh.php
 * 
 * Refresh an expired Access Token using a valid Refresh Token.
 * 
 * Request Body: { "refresh_token": "eyJ..." }
 * Response:     { "ok": true, "access_token": "eyJ...", "expires_in": 1800 }
 * 
 * @package KSM Education
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_context.php';
require_once __DIR__ . '/jwt_helper.php';


// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['refresh_token'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'refresh_token is required']);
        exit;
    }

    $refreshToken = trim($data['refresh_token']);

    // Decode and validate the refresh token
    $payload = jwt_decode($refreshToken, JWT_SECRET);
    if (!$payload) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid or expired refresh token',
            'code' => 'REFRESH_INVALID'
        ]);
        exit;
    }

    // Ensure it's actually a refresh token
    if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Token is not a refresh token',
            'code' => 'WRONG_TOKEN_TYPE'
        ]);
        exit;
    }

    // Refresh token hanya berlaku pada konteks penerbitnya. Tanpa cek ini,
    // refresh token admin bisa ditukar menjadi access token untuk area user
    // (dan sebaliknya), sehingga isolasi konteks bocor lewat endpoint refresh.
    $token_ctx = ksmedu_normalize_context($payload['ctx'] ?? '') ?? KSMEDU_CTX_USER;
    if ($token_ctx !== ksmedu_request_context()) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Refresh token tidak berlaku pada konteks ini',
            'code' => 'CONTEXT_MISMATCH'
        ]);
        exit;
    }

    // Check if refresh token is blacklisted

    // Pemeriksaan revokasi bersifat FAIL CLOSED. Sebelumnya, error pada query
    // (tabel hilang, DB bermasalah) membuat blok ini di-skip sehingga refresh
    // token yang sudah di-logout/revoke tetap bisa dipakai menerbitkan access
    // token baru. Sekarang kegagalan pemeriksaan = tolak permintaan.
    //
    // $inGracePeriod menandai token yang SUDAH dirotasi namun masih dalam
    // jendela toleransi; token ini tidak dirotasi ulang (jti-nya sudah ada di
    // blacklist) tapi tetap dilayani agar request paralel tidak saling
    // mematikan sesi.
    $inGracePeriod = false;

    if (isset($payload['jti'])) {
        try {
            $revocation = find_blacklisted_token((string) $payload['jti']);
        } catch (Exception $e) {

            error_log('Refresh blacklist check failed: ' . $e->getMessage());
            http_response_code(503);
            header('Retry-After: 60');
            echo json_encode([
                'ok' => false,
                'message' => 'Tidak dapat memverifikasi status token. Coba lagi nanti.',
                'code' => 'REVOCATION_CHECK_UNAVAILABLE'
            ]);
            exit;
        }

        if ($revocation !== null) {
            $isRotated = $revocation['reason'] === 'rotated';
            $withinGrace = $revocation['revoked_age'] <= JWT_ROTATION_GRACE;

            if ($isRotated && $withinGrace) {
                // Race condition wajar: beberapa tab/permintaan paralel
                // mengirim refresh token yang sama nyaris bersamaan.
                $inGracePeriod = true;
            } elseif ($isRotated) {
                // Token yang sudah lama dirotasi dipakai kembali. Pemilik sah
                // seharusnya sudah memegang penggantinya, jadi ini indikasi
                // token dicuri: cabut SELURUH sesi user agar token hasil rotasi
                // yang dipegang penyerang pun mati.
                revoke_all_user_sessions((int) $payload['sub'], 'refresh_token_reuse');
                error_log(sprintf(
                    'SECURITY: refresh token reuse detected (user %d, jti %s, age %ds)',
                    (int) $payload['sub'],
                    (string) $payload['jti'],
                    $revocation['revoked_age']
                ));
                http_response_code(401);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Sesi dicabut karena terdeteksi penggunaan token berulang. Silakan login kembali.',
                    'code' => 'SESSION_REVOKED'
                ]);
                exit;
            } else {
                // Dicabut karena logout / pencabutan eksplisit.
                http_response_code(401);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Refresh token has been revoked',
                    'code' => 'TOKEN_REVOKED'
                ]);
                exit;
            }
        }
    } else {

        // Tanpa jti, token tidak dapat dicabut sama sekali. Token seperti ini
        // hanya bisa berasal dari versi lama; tolak agar semua refresh token
        // yang beredar benar-benar dapat direvokasi.
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Refresh token tidak valid. Silakan login kembali.',
            'code' => 'REFRESH_INVALID'
        ]);
        exit;
    }

    // Fetch the latest user data from database
    $userId = (int) $payload['sub'];
    $stmt = $pdo->prepare(
        "SELECT id, email, name, role, token_version, UNIX_TIMESTAMP(password_changed_at) AS pwd_changed

         FROM users WHERE id = ? AND (account_status = 'active' OR account_status IS NULL) LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'User no longer exists',
            'code' => 'USER_NOT_FOUND'
        ]);
        exit;
    }

    // Refresh token yang diterbitkan sebelum password terakhir diubah harus
    // ditolak. Tanpa cek ini, sesi lama tetap bisa memperpanjang diri sendiri
    // walaupun user sudah ganti password / melakukan reset password.
    $pwdChanged = isset($user['pwd_changed']) ? (int) $user['pwd_changed'] : 0;
    $issuedAt = isset($payload['iat']) ? (int) $payload['iat'] : 0;
    if ($pwdChanged > 0 && $issuedAt > 0 && $issuedAt < $pwdChanged) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Password telah diubah. Silakan login kembali.',
            'code' => 'PASSWORD_CHANGED'
        ]);
        exit;
    }


    // Generasi sesi: token dari generasi lama (mis. sesi sudah dicabut karena
    // reuse) tidak boleh menerbitkan apa pun.
    if (isset($payload['tv']) && (int) $payload['tv'] !== (int) ($user['token_version'] ?? 0)) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Sesi sudah tidak berlaku. Silakan login kembali.',
            'code' => 'SESSION_REVOKED'
        ]);
        exit;
    }

    // Rotasi: satu refresh token hanya sah untuk satu kali penukaran. Token
    // lama dicabut lebih dulu, lalu penggantinya diterbitkan.
    if ($inGracePeriod) {
        // jti lama sudah tercatat 'rotated'; cukup terbitkan pengganti baru
        // supaya klien yang kalah race tidak terjebak memakai token mati.
        $newRefresh = generate_refresh_token($user, $token_ctx);
    } else {
        $newRefresh = rotate_refresh_token($user, $payload, $token_ctx);
        if ($newRefresh === null) {
            error_log('Refresh rotation failed for user ' . $userId . ' — old token could not be revoked.');
            http_response_code(503);
            header('Retry-After: 60');
            echo json_encode([
                'ok' => false,
                'message' => 'Tidak dapat memperbarui sesi saat ini. Coba lagi nanti.',
                'code' => 'ROTATION_FAILED'
            ]);
            exit;
        }
    }

    // Generate a new access token with fresh user data
    $accessToken = generate_access_token($user, $token_ctx);

    echo json_encode([
        'ok' => true,
        'access_token' => $accessToken['token'],
        'expires_in' => $accessToken['expires_in'],
        'refresh_token' => $newRefresh['token'],
        'refresh_expires_in' => $newRefresh['expires_in'],
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ]
    ]);


} catch (Exception $e) {
    error_log("Token refresh error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error during token refresh'
    ]);
}
