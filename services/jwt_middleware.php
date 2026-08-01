<?php
/**
 * JWT Middleware — Hybrid Authentication (JWT + Session Fallback)
 * 
 * Include this file at the top of any protected endpoint.
 * It will set $auth_user with the authenticated user info.
 * 
 * Auth priority:
 *   1. JWT (Authorization: Bearer <token>)
 *   2. PHP Session ($_SESSION['user_id'])
 *   3. 401 Unauthorized
 * 
 * Usage:
 *   require_once __DIR__ . '/jwt_middleware.php';
 *   // $auth_user is now available: ['id', 'role', 'name', 'email', 'auth_method']
 *   // For admin-only endpoints, add: require_admin();
 *
 * ISOLASI KONTEKS (perbaikan bug "admin ikut login di dashboard user"):
 * Session dan token dipisah per konteks ('admin' untuk /admin, 'user' untuk
 * /user & publik). Token yang diterbitkan pada satu konteks TIDAK berlaku di
 * konteks lain, dan session fallback hanya membaca cookie milik konteks
 * request saat ini. Lihat services/auth_context.php.
 * 
 * @package KSM Education
 */

require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/auth_context.php';


/**
 * Authenticate the current request.
 * Sets global $auth_user on success.
 * Returns the authenticated user array or null.
 */
function authenticate_request(): ?array {
    global $auth_user, $pdo;

    $request_ctx = ksmedu_request_context();

    // === STRATEGY 1: JWT Token ===
    $jwt_payload = validate_jwt();
    if ($jwt_payload) {
        // Token hanya sah pada konteks penerbitnya. Token admin (dibuat di
        // /admin) tidak boleh dipakai untuk mengakses area user, dan
        // sebaliknya. Token lama tanpa klaim 'ctx' dianggap konteks 'user'
        // agar sesi pengguna yang sedang berjalan tidak langsung putus.
        $token_ctx = ksmedu_normalize_context($jwt_payload['ctx'] ?? '') ?? KSMEDU_CTX_USER;
        if ($token_ctx !== $request_ctx) {
            $auth_user = null;
            return null;
        }

        // Account lifecycle is authoritative in the database.  This prevents
        // an access token issued before a disable/delete from using legacy
        // protected endpoints after the account has been closed.
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $statusStmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ? LIMIT 1");
                $statusStmt->execute([(int)$jwt_payload['sub']]);
                $accountStatus = $statusStmt->fetchColumn();
                if ($accountStatus !== 'active') {
                    $auth_user = null;
                    return null;
                }
            } catch (Throwable $e) {
                // Preserve the existing middleware behavior for legacy
                // installations that have not applied the Phase 3 migration.
            }
        }
        $auth_user = [
            'id'          => (int) $jwt_payload['sub'],
            'name'        => $jwt_payload['name'] ?? '',
            'role'        => $jwt_payload['role'] ?? 'user',
            'email'       => $jwt_payload['email'] ?? '',
            'auth_method' => 'jwt',
            'jti'         => $jwt_payload['jti'] ?? null
        ];
        return $auth_user;
    }

    // === STRATEGY 2: PHP Session (Fallback) ===
    // Hanya membaca session milik konteks request ini (cookie terpisah antara
    // panel admin dan area user), sehingga login di /admin tidak lagi membuat
    // halaman /user ikut terautentikasi.
    ksmedu_session_start($request_ctx);

    if (!empty($_SESSION['user_id']) && ($_SESSION['ksm_ctx'] ?? $request_ctx) === $request_ctx) {

        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $statusStmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ? LIMIT 1");
                $statusStmt->execute([(int)$_SESSION['user_id']]);
                $accountStatus = $statusStmt->fetchColumn();
                if ($accountStatus !== 'active') {
                    $auth_user = null;
                    return null;
                }
            } catch (Throwable $e) {
                // Preserve session fallback for legacy installations.
            }
        }
        $auth_user = [
            'id'          => (int) $_SESSION['user_id'],
            'role'        => $_SESSION['role'] ?? 'user',
            'name'        => $_SESSION['name'] ?? '',
            'email'       => $_SESSION['email'] ?? '',
            'auth_method' => 'session'
        ];
        return $auth_user;
    }

    // === NO AUTH ===
    $auth_user = null;
    return null;
}

/**
 * Require authentication — returns 401 if not authenticated.
 * Call this in endpoints that MUST have a logged-in user.
 */
function require_auth(): array {
    $user = authenticate_request();
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Authentication required. Please login.',
            'code' => 'AUTH_REQUIRED'
        ]);
        exit;
    }
    return $user;
}

/**
 * Require admin role — returns 403 if not admin.
 * Must be called AFTER require_auth() or authenticate_request().
 */
function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Admin access required.',
            'code' => 'ADMIN_REQUIRED'
        ]);
        exit;
    }
    return $user;
}

/**
 * Optional authentication — authenticates if token/session is present,
 * but does NOT block the request if unauthenticated.
 * Useful for endpoints that behave differently for logged-in users.
 */
function optional_auth(): ?array {
    return authenticate_request();
}

// Auto-authenticate on include (sets $auth_user global)
$auth_user = null;
authenticate_request();
