<?php
/**
 * tools/sim_otp_preflight.php — pemeriksaan prasyarat sebelum simulasi Email OTP.
 * Read-only: tidak mengubah data apa pun.
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

require_once __DIR__ . '/../services/db.php';

function line(string $k, string $v): void { echo str_pad($k, 30) . ': ' . $v . PHP_EOL; }
function present($v): string { return ($v === '' || $v === false || $v === null) ? 'EMPTY' : 'SET(len=' . strlen((string)$v) . ')'; }

echo "=== ENV ===\n";
foreach (['APP_ENV','APP_TIMEZONE','DB_HOST','DB_NAME','DB_USER','RESEND_API_KEY','MAIL_FROM','MAIL_FROM_NAME','JWT_SECRET','TELEGRAM_BOT_TOKEN','TELEGRAM_ADMIN_CHAT_ID','APP_BASE_URL'] as $k) {
    $v = get_env_var($k, '');
    // Nilai sensitif tidak pernah dicetak, hanya status keberadaannya.
    $safe = in_array($k, ['APP_ENV','APP_TIMEZONE','DB_HOST','DB_NAME','DB_USER','MAIL_FROM','MAIL_FROM_NAME','APP_BASE_URL'], true);
    line($k, $safe ? ($v === '' ? 'EMPTY' : (string)$v) : present($v));
}

echo "\n=== DB ===\n";
line('connected', 'yes (' . get_env_var('DB_NAME', '?') . ')');
line('mysql version', (string)$pdo->query('SELECT VERSION()')->fetchColumn());
line('php now', date('Y-m-d H:i:s'));
line('mysql now', (string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());

$tables = ['users','email_verifications','email_otp_ip_attempts','user_token_wallets','token_transactions','journals','jwt_blacklist'];
foreach ($tables as $t) {
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t))->fetchColumn();
    line("table $t", $exists ? 'OK' : 'MISSING');
}

$cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('email_verified_at','account_status')")->fetchAll();
echo "\n=== users columns ===\n";
foreach ($cols as $c) { line($c['COLUMN_NAME'], $c['COLUMN_TYPE'] . ' NULL=' . $c['IS_NULLABLE']); }

echo "\n=== data snapshot ===\n";
line('users total', (string)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
line('users unverified', (string)$pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn());
line('email_verifications rows', (string)$pdo->query('SELECT COUNT(*) FROM email_verifications')->fetchColumn());
line('ip_attempts rows', (string)$pdo->query('SELECT COUNT(*) FROM email_otp_ip_attempts')->fetchColumn());
line('journals rows', (string)$pdo->query('SELECT COUNT(*) FROM journals')->fetchColumn());

echo "\n=== HTTP probe ===\n";
$base = rtrim((string)get_env_var('APP_BASE_URL', 'http://localhost/ksmedu'), '/');
line('base url', $base);
$ch = curl_init($base . '/services/auth_login.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => false]);
$body = curl_exec($ch);
line('GET auth_login.php', $body === false ? 'CURL ERROR: ' . curl_error($ch) : 'HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' body=' . substr((string)$body, 0, 120));
curl_close($ch);
