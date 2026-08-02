<?php
/**
 * Test fungsional rotasi refresh token (migrasi 012) terhadap DB lokal.
 *
 * Yang diverifikasi:
 *   1. Rotasi: refresh token lama dicabut (reason='rotated') dan pengganti
 *      dengan jti berbeda diterbitkan.
 *   2. Grace period: token yang baru saja dirotasi terdeteksi 'rotated' dengan
 *      revoked_age <= JWT_ROTATION_GRACE (permintaan paralel tetap dilayani).
 *   3. Reuse detection: token 'rotated' yang sudah lewat grace memicu
 *      revoke_all_user_sessions -> token_version naik.
 *   4. Klaim tv: access/refresh token membawa generasi sesi, dan token dari
 *      generasi lama ditolak jwt_token_version_valid().
 *   5. Logout biasa tercatat dengan reason='logout' (bukan 'rotated').
 *
 * Membersihkan user & baris blacklist uji setelah selesai.
 * Jalankan: php tools/_test_refresh_rotation.php
 */

require_once __DIR__ . '/../services/db.php';
require_once __DIR__ . '/../services/auth_context.php';
require_once __DIR__ . '/../services/jwt_helper.php';

$fails = 0;
$passes = 0;
$userId = null;
$jtis = [];

function check(string $label, bool $cond, string $extra = ''): void
{
    global $fails, $passes;
    if ($cond) {
        $passes++;
        echo "PASS  {$label}\n";
    } else {
        $fails++;
        echo "FAIL  {$label}" . ($extra !== '' ? "  ({$extra})" : '') . "\n";
    }
}

try {
    // --- Siapkan user uji ---
    $email = 'rotationtest+' . bin2hex(random_bytes(4)) . '@example.test';
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, name, role, account_status, token_version)
         VALUES (?, ?, 'Rotation Test', 'user', 'active', 0)"
    );
    $stmt->execute([$email, password_hash('dummy-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();

    $user = $pdo->query("SELECT id, email, name, role, token_version FROM users WHERE id = {$userId}")->fetch();

    // --- 1. Token awal membawa klaim tv ---
    $first = generate_refresh_token($user, KSMEDU_CTX_USER);
    $firstClaims = jwt_decode($first['token'], JWT_SECRET);
    $jtis[] = $firstClaims['jti'];
    check('refresh token membawa klaim tv', isset($firstClaims['tv']));
    check('klaim tv = token_version user',
        (int) ($firstClaims['tv'] ?? -1) === 0,
        'tv=' . var_export($firstClaims['tv'] ?? null, true));

    $access = generate_access_token($user, KSMEDU_CTX_USER);
    $accessClaims = jwt_decode($access['token'], JWT_SECRET);
    check('access token juga membawa klaim tv', isset($accessClaims['tv']));

    // --- 2. Rotasi menerbitkan jti baru & mencabut yang lama ---
    $second = rotate_refresh_token($user, $firstClaims, KSMEDU_CTX_USER);
    check('rotasi berhasil (bukan null)', $second !== null);

    $secondClaims = $second ? jwt_decode($second['token'], JWT_SECRET) : null;
    if ($secondClaims) {
        $jtis[] = $secondClaims['jti'];
    }
    check('token pengganti punya jti berbeda',
        $secondClaims && $secondClaims['jti'] !== $firstClaims['jti']);
    check('token pengganti bertipe refresh',
        $secondClaims && ($secondClaims['type'] ?? '') === 'refresh');
    check('konteks token pengganti tetap user',
        $secondClaims && ($secondClaims['ctx'] ?? '') === KSMEDU_CTX_USER);

    // --- 3. Token lama tercatat sebagai 'rotated' dalam grace period ---
    $rev = find_blacklisted_token((string) $firstClaims['jti']);
    check('token lama masuk blacklist', $rev !== null);
    check("reason = 'rotated'", $rev && $rev['reason'] === 'rotated',
        'reason=' . ($rev['reason'] ?? 'null'));
    check('revoked_age masih dalam grace period',
        $rev && $rev['revoked_age'] <= JWT_ROTATION_GRACE,
        'age=' . ($rev['revoked_age'] ?? '?') . 's, grace=' . JWT_ROTATION_GRACE . 's');

    // --- 4. Reuse di luar grace: mundurkan revoked_at, lalu cabut semua sesi ---
    $pdo->prepare("UPDATE jwt_blacklist SET revoked_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE token_jti = ?")
        ->execute([JWT_ROTATION_GRACE + 120, (string) $firstClaims['jti']]);

    $revOld = find_blacklisted_token((string) $firstClaims['jti']);
    check('token lama kini di luar grace period',
        $revOld && $revOld['revoked_age'] > JWT_ROTATION_GRACE,
        'age=' . ($revOld['revoked_age'] ?? '?') . 's');

    check('token_version masih 0 sebelum reuse terdeteksi',
        (int) $pdo->query("SELECT token_version FROM users WHERE id = {$userId}")->fetchColumn() === 0);

    check('revoke_all_user_sessions berhasil',
        revoke_all_user_sessions($userId, 'refresh_token_reuse'));

    $newVersion = (int) $pdo->query("SELECT token_version FROM users WHERE id = {$userId}")->fetchColumn();
    check('token_version naik setelah pencabutan', $newVersion === 1, "tv={$newVersion}");

    // --- 5. Token generasi lama ditolak verifikator ---
    check('refresh token pengganti (tv lama) kini tidak valid',
        $secondClaims && jwt_token_version_valid($secondClaims) === false);
    check('access token lama (tv lama) kini tidak valid',
        jwt_token_version_valid($accessClaims) === false);

    // Token baru setelah pencabutan harus valid kembali.
    $userAfter = $pdo->query("SELECT id, email, name, role, token_version FROM users WHERE id = {$userId}")->fetch();
    $fresh = generate_refresh_token($userAfter, KSMEDU_CTX_USER);
    $freshClaims = jwt_decode($fresh['token'], JWT_SECRET);
    $jtis[] = $freshClaims['jti'];
    check('token yang baru diterbitkan valid lagi', jwt_token_version_valid($freshClaims) === true);
    check('klaim tv token baru = generasi terkini',
        (int) ($freshClaims['tv'] ?? -1) === $newVersion);

    // --- 6. Token tanpa klaim tv tetap diterima (kompatibilitas) ---
    $legacy = $freshClaims;
    unset($legacy['tv']);
    check('token lama tanpa klaim tv tetap diterima', jwt_token_version_valid($legacy) === true);

    // --- 7. Logout biasa memakai reason 'logout' ---
    $logoutJti = generate_jti();
    $jtis[] = $logoutJti;
    check('blacklist_token (logout) berhasil',
        blacklist_token($logoutJti, time() + 3600, 'logout'));
    $revLogout = find_blacklisted_token($logoutJti);
    check("reason logout tidak dianggap 'rotated'",
        $revLogout && $revLogout['reason'] === 'logout',
        'reason=' . ($revLogout['reason'] ?? 'null'));

} finally {
    // Bersihkan data uji.
    if (!empty($jtis)) {
        $in = implode(',', array_fill(0, count($jtis), '?'));
        $pdo->prepare("DELETE FROM jwt_blacklist WHERE token_jti IN ({$in})")->execute($jtis);
    }
    if ($userId !== null) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    }
    echo "\nCleanup: user & baris blacklist uji dihapus.\n";
}

echo "\nHASIL: {$passes} pass, {$fails} fail\n";
exit($fails === 0 ? 0 : 1);
