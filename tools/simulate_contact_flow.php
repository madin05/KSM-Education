<?php
/**
 * tools/simulate_contact_flow.php
 *
 * Simulasi END-TO-END lewat HTTP (butuh Apache/XAMPP hidup):
 *
 *   php tools/simulate_contact_flow.php [baseUrl]
 *   default baseUrl = http://localhost/ksmedu
 *
 * Alur yang disimulasikan:
 *   1. Buat akun user uji + akun admin uji (dibersihkan lagi di akhir).
 *   2. Login user pada konteks 'user'  -> dapat access token ctx=user.
 *   3. User kirim pesan kontak (services/send_contact.php).
 *   4. Login admin pada konteks 'admin' -> dapat access token ctx=admin.
 *   5. Admin membaca inbox (services/admin/contact_messages.php) dan
 *      memastikan pesan user tadi masuk.
 *   6. UJI ISOLASI (inti bug):
 *      - token admin dipakai ke endpoint user  -> harus 401
 *      - token user dipakai ke endpoint admin  -> harus 401/403
 *      - cookie session admin dipakai ke area user -> harus TIDAK login
 *      - setelah login admin, auth_me konteks user -> harus 401
 *   7. Bersihkan semua data uji.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../services/db.php';

$baseUrl = rtrim($argv[1] ?? 'http://localhost/ksmedu', '/');

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        echo "  FAIL  $label" . ($extra !== '' ? "  -> $extra" : '') . "\n";
    }
}

/**
 * Helper HTTP sederhana dengan dukungan cookie jar terpisah per "browser".
 *
 * @param array<string,string> $headers
 * @return array{status:int, body:string, json:mixed, cookies:array<string,string>}
 */
function http_req(
    string $method,
    string $url,
    ?array $payload = null,
    array $headers = [],
    ?string $bearer = null,
    array &$cookieJar = []
): array {
    $ch = curl_init();
    $hdrs = ['Accept: application/json'];
    if ($payload !== null) {
        $hdrs[] = 'Content-Type: application/json';
    }
    if ($bearer !== null) {
        $hdrs[] = 'Authorization: Bearer ' . $bearer;
    }
    foreach ($headers as $k => $v) {
        $hdrs[] = "$k: $v";
    }
    if ($cookieJar) {
        $pairs = [];
        foreach ($cookieJar as $k => $v) {
            $pairs[] = "$k=$v";
        }
        $hdrs[] = 'Cookie: ' . implode('; ', $pairs);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        fwrite(STDERR, "HTTP error ($method $url): $err\n");
        return ['status' => 0, 'body' => '', 'json' => null, 'cookies' => []];
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    // Serap Set-Cookie ke cookie jar milik "browser" ini.
    $setCookies = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (stripos($line, 'Set-Cookie:') === 0) {
            $cookie = trim(substr($line, 11));
            $first = explode(';', $cookie)[0];
            if (strpos($first, '=') !== false) {
                [$ck, $cv] = explode('=', $first, 2);
                $setCookies[trim($ck)] = trim($cv);
                $cookieJar[trim($ck)] = trim($cv);
            }
        }
    }

    return [
        'status'  => $status,
        'body'    => $body,
        'json'    => json_decode($body, true),
        'cookies' => $setCookies,
    ];
}

// ---------------------------------------------------------------------------
// Persiapan data uji
// ---------------------------------------------------------------------------
$stamp = (string)time();
$userEmail = "sim_user_{$stamp}@example.test";
$adminEmail = "sim_admin_{$stamp}@example.test";
$password = 'SimPass!2345';
$subject = "Simulasi pesan {$stamp}";

echo "Base URL: $baseUrl\n\n";

$cleanup = function () use ($pdo, $userEmail, $adminEmail, $subject) {
    try {
        $pdo->prepare('DELETE FROM contact_messages WHERE subject = ?')->execute([$subject]);
        $pdo->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$userEmail, $adminEmail]);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cleanup warning: ' . $e->getMessage() . "\n");
    }
};

// Akun admin uji dibuat langsung di DB (tidak ada endpoint publik untuk itu).
$hash = password_hash($password, PASSWORD_BCRYPT);
try {
    $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, account_status)
         VALUES (?, ?, ?, 'admin', 'active')"
    )->execute(['Sim Admin', $adminEmail, $hash]);
} catch (Throwable $e) {
    // Skema lama mungkin tidak punya account_status.
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
        ->execute(['Sim Admin', $adminEmail, $hash]);
}


$userJar = [];   // "browser" tab user
$adminJar = [];  // "browser" tab admin

try {
    echo "== 1. Registrasi & login user (konteks user) ==\n";
    $reg = http_req('POST', "$baseUrl/services/auth_register.php", [
        'name' => 'Sim User',
        'email' => $userEmail,
        'password' => $password,
        'confirm_password' => $password,
    ], ['X-KSM-Context' => 'user'], null, $userJar);
    check('registrasi user berhasil', in_array($reg['status'], [200, 201], true), "status={$reg['status']} body=" . substr($reg['body'], 0, 200));

    $login = http_req('POST', "$baseUrl/services/auth_login.php", [
        'email' => $userEmail,
        'password' => $password,
    ], ['X-KSM-Context' => 'user'], null, $userJar);
    $userToken = $login['json']['access_token'] ?? $login['json']['token'] ?? null;
    check('login user berhasil', $login['status'] === 200, "status={$login['status']} body=" . substr($login['body'], 0, 200));
    check('user menerima access token', is_string($userToken) && $userToken !== '');
    check(
        'session user memakai cookie KSMEDUSESS',
        isset($userJar['KSMEDUSESS']),
        'cookies=' . implode(',', array_keys($userJar))
    );

    echo "\n== 2. User mengirim pesan ke admin ==\n";
    $send = http_req('POST', "$baseUrl/services/send_contact.php", [
        'name' => 'Sim User',
        'email' => $userEmail,
        'subject' => $subject,
        'message' => 'Ini pesan simulasi dari user ke admin untuk uji alur kontak.',
    ], ['X-KSM-Context' => 'user'], $userToken, $userJar);
    check('pesan kontak terkirim (201)', $send['status'] === 201, "status={$send['status']} body=" . substr($send['body'], 0, 200));

    $row = $pdo->prepare('SELECT user_id FROM contact_messages WHERE subject = ? LIMIT 1');
    $row->execute([$subject]);
    $storedUserId = $row->fetchColumn();
    check('pesan tersimpan & terhubung ke user_id pengirim', $storedUserId !== false && $storedUserId !== null, 'user_id=' . var_export($storedUserId, true));

    echo "\n== 3. Login admin (konteks admin, tab berbeda) ==\n";
    $adminLogin = http_req('POST', "$baseUrl/services/auth_admin_login.php", [
        'email' => $adminEmail,
        'password' => $password,
    ], ['X-KSM-Context' => 'admin'], null, $adminJar);
    $adminToken = $adminLogin['json']['access_token'] ?? $adminLogin['json']['token'] ?? null;
    check('login admin berhasil', $adminLogin['status'] === 200, "status={$adminLogin['status']} body=" . substr($adminLogin['body'], 0, 200));
    check('admin menerima access token', is_string($adminToken) && $adminToken !== '');
    check(
        'session admin memakai cookie KSMEDUADMSESS',
        isset($adminJar['KSMEDUADMSESS']),
        'cookies=' . implode(',', array_keys($adminJar))
    );
    check('cookie admin tidak menimpa cookie user', !isset($adminJar['KSMEDUSESS']));

    echo "\n== 4. Admin membaca inbox kontak ==\n";
    $inbox = http_req('GET', "$baseUrl/services/admin/contact_messages.php?limit=100", null, ['X-KSM-Context' => 'admin'], $adminToken, $adminJar);
    check('inbox admin dapat diakses (200)', $inbox['status'] === 200, "status={$inbox['status']} body=" . substr($inbox['body'], 0, 200));
    $found = false;
    foreach (($inbox['json']['results'] ?? []) as $m) {
        if (($m['subject'] ?? '') === $subject) {
            $found = true;
            break;
        }
    }
    check('pesan user terlihat di inbox admin', $found);

    echo "\n== 5. Uji isolasi lintas dashboard (inti bug) ==\n";
    $crossA = http_req('GET', "$baseUrl/services/auth_me.php", null, ['X-KSM-Context' => 'user'], $adminToken);
    check('token admin ditolak di endpoint konteks user (401)', $crossA['status'] === 401, "status={$crossA['status']}");

    $crossB = http_req('GET', "$baseUrl/services/admin/contact_messages.php", null, ['X-KSM-Context' => 'admin'], $userToken);
    check('token user ditolak di endpoint admin (401/403)', in_array($crossB['status'], [401, 403], true), "status={$crossB['status']}");

    // Skenario asli yang dilaporkan: setelah admin login, buka area user
    // dengan membawa cookie session admin -> tidak boleh ikut login.
    $adminCookieOnly = ['KSMEDUADMSESS' => $adminJar['KSMEDUADMSESS'] ?? ''];
    $bleed = http_req('GET', "$baseUrl/services/auth_me.php", null, ['X-KSM-Context' => 'user'], null, $adminCookieOnly);
    check('cookie session admin TIDAK ikut login di area user (401)', $bleed['status'] === 401, "status={$bleed['status']} body=" . substr($bleed['body'], 0, 160));

    // Sebaliknya: cookie user tidak memberi akses panel admin.
    $userCookieOnly = ['KSMEDUSESS' => $userJar['KSMEDUSESS'] ?? ''];
    $bleed2 = http_req('GET', "$baseUrl/services/admin/contact_messages.php", null, ['X-KSM-Context' => 'admin'], null, $userCookieOnly);
    check('cookie session user TIDAK memberi akses panel admin', in_array($bleed2['status'], [401, 403], true), "status={$bleed2['status']}");

    // Sesi user tetap utuh walau admin login di tab lain (tidak saling menendang).
    $stillUser = http_req('GET', "$baseUrl/services/auth_me.php", null, ['X-KSM-Context' => 'user'], $userToken, $userJar);
    $meEmail = $stillUser['json']['user']['email'] ?? $stillUser['json']['email'] ?? null;
    check('sesi user tetap aktif & identitasnya tetap user sendiri', $stillUser['status'] === 200 && $meEmail === $userEmail, "status={$stillUser['status']} email=" . var_export($meEmail, true));

    // Sesi admin juga tetap utuh.
    $stillAdmin = http_req('GET', "$baseUrl/services/admin/contact_messages.php?limit=1", null, ['X-KSM-Context' => 'admin'], $adminToken, $adminJar);
    check('sesi admin tetap aktif berbarengan dengan sesi user', $stillAdmin['status'] === 200, "status={$stillAdmin['status']}");

    echo "\n== 6. Logout satu sisi tidak mematikan sisi lain ==\n";
    http_req('POST', "$baseUrl/services/auth_logout.php", [], ['X-KSM-Context' => 'admin'], $adminToken, $adminJar);
    $userAfterAdminLogout = http_req('GET', "$baseUrl/services/auth_me.php", null, ['X-KSM-Context' => 'user'], $userToken, $userJar);
    check('logout admin tidak memutus sesi user', $userAfterAdminLogout['status'] === 200, "status={$userAfterAdminLogout['status']}");
} finally {
    $cleanup();
    echo "\n(data uji dibersihkan)\n";
}

echo "\n== RINGKASAN ==\n";
echo "  PASS: $pass\n";
echo "  FAIL: $fail\n";

exit($fail === 0 ? 0 : 1);
