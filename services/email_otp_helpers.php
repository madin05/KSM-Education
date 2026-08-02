<?php
/**
 * services/email_otp_helpers.php — helper verifikasi email berbasis OTP.
 *
 * Semua OTP disimpan sebagai hash (password_hash) pada tabel
 * email_verifications. Plaintext OTP hanya hidup di memori proses saat
 * pembuatan dan langsung dikirim lewat Resend API.
 *
 * File ini hanya berisi fungsi; tidak mengeluarkan output apa pun.
 */

if (!defined('KSMEDU_OTP_TTL_MINUTES')) {
    define('KSMEDU_OTP_TTL_MINUTES', 5);      // masa berlaku OTP
    define('KSMEDU_OTP_RESEND_COOLDOWN', 60); // detik antar permintaan kirim ulang
    define('KSMEDU_OTP_RESEND_MAX', 3);       // maksimal kirim ulang / 15 menit
    define('KSMEDU_OTP_MAX_ATTEMPTS', 5);     // maksimal percobaan per OTP
    define('KSMEDU_OTP_IP_WINDOW_MINUTES', 10); // jendela rate limit per IP
    define('KSMEDU_OTP_IP_MAX_VERIFY', 5);      // maksimal verifikasi / IP / jendela
    define('KSMEDU_OTP_IP_MAX_RESEND', 5);      // maksimal kirim ulang / IP / jendela
}

/**
 * Hash alamat IP pemanggil. Mengikuti pola contact_messages.ip_hash dan
 * password_reset_tokens.requested_ip_hash: IP mentah tidak pernah disimpan.
 */
function otp_client_ip_hash(): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/**
 * Catat satu percobaan per IP. Sengaja dijalankan DI LUAR transaksi verifikasi
 * supaya rollback transaksi tidak menghapus jejak rate limit.
 *
 * @param string $action 'verify' atau 'resend'
 */
function otp_record_ip_attempt(PDO $pdo, string $action): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO email_otp_ip_attempts (ip_hash, action_type) VALUES (?, ?)'
    );
    $stmt->execute([otp_client_ip_hash(), $action]);
}

/**
 * True bila IP pemanggil sudah melewati batas percobaan pada jendela waktu.
 * Pengecekan dilakukan tanpa menyentuh identitas user sama sekali sehingga
 * tidak bisa dipakai untuk menyimpulkan keberadaan email.
 *
 * @param string $action 'verify' atau 'resend'
 */
function otp_ip_rate_limited(PDO $pdo, string $action, int $max): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM email_otp_ip_attempts
         WHERE ip_hash = ? AND action_type = ?
           AND created_at >= (CURRENT_TIMESTAMP - INTERVAL ' . (int)KSMEDU_OTP_IP_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute([otp_client_ip_hash(), $action]);

    return (int)$stmt->fetchColumn() >= $max;
}


/**
 * Generate OTP numerik 6 digit dengan sumber acak kriptografis.
 */
function otp_generate_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Buat OTP baru untuk user dan batalkan OTP lama yang masih aktif.
 *
 * @param int  $resendCount Nilai resend_count yang dibawa ke baris baru.
 * @return string OTP plaintext (hanya untuk dikirim via email).
 */
function otp_issue_for_user(PDO $pdo, int $userId, int $resendCount = 0): string
{
    $code = otp_generate_code();
    $hash = password_hash($code, PASSWORD_DEFAULT);

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // OTP lama tidak boleh berlaku lagi begitu OTP baru diterbitkan.
        $invalidate = $pdo->prepare(
            'UPDATE email_verifications SET consumed_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND consumed_at IS NULL'
        );
        $invalidate->execute([$userId]);

        $insert = $pdo->prepare(
            'INSERT INTO email_verifications (user_id, otp_hash, expires_at, resend_count)
             VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . (int)KSMEDU_OTP_TTL_MINUTES . ' MINUTE), ?)'
        );
        $insert->execute([$userId, $hash, $resendCount]);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $code;
}

/**
 * Ambil OTP aktif terakhir milik user (termasuk yang sudah expired supaya
 * pemanggil bisa membedakan "salah" dan "kadaluarsa").
 *
 * @return array<string,mixed>|null
 */
function otp_latest_pending(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, otp_hash, attempt_count, resend_count, created_at,
                expires_at, (expires_at < CURRENT_TIMESTAMP) AS is_expired
         FROM email_verifications
         WHERE user_id = ? AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Sama seperti otp_latest_pending(), tetapi memakai locking read
 * (SELECT ... FOR UPDATE) sehingga baris OTP terkunci sampai transaksi
 * pemanggil commit/rollback. Wajib dipanggil di dalam transaksi.
 *
 * Locking read pada InnoDB selalu membaca versi commit terbaru, jadi request
 * kedua yang menunggu lock akan melihat consumed_at yang sudah terisi dan
 * mendapatkan null (OTP sudah dikonsumsi request lain).
 *
 * @return array<string,mixed>|null
 */
function otp_lock_latest_pending(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, otp_hash, attempt_count, resend_count, created_at,
                expires_at, (expires_at < CURRENT_TIMESTAMP) AS is_expired
         FROM email_verifications
         WHERE user_id = ? AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Template HTML email OTP.
 */

function otp_email_html(string $name, string $code): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $ttl = (int)KSMEDU_OTP_TTL_MINUTES;

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Verifikasi Email KSM Journal</title></head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0f4c81;padding:20px 24px;color:#ffffff;font-size:18px;font-weight:bold;">KSM Journal</td>
    </tr>
    <tr>
      <td style="padding:24px;">
        <p style="margin:0 0 16px;font-size:15px;">Halo {$safeName},</p>
        <p style="margin:0 0 12px;font-size:15px;">Kode OTP Anda adalah</p>
        <p style="margin:0 0 16px;font-size:32px;font-weight:bold;letter-spacing:6px;color:#0f4c81;">{$safeCode}</p>
        <p style="margin:0 0 12px;font-size:14px;">Kode berlaku selama {$ttl} menit.</p>
        <p style="margin:0 0 12px;font-size:14px;">Jangan bagikan kode ini kepada siapa pun.</p>
        <p style="margin:24px 0 0;font-size:12px;color:#7b8794;">Jika Anda tidak melakukan pendaftaran, abaikan email ini.</p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Kirim OTP lewat Resend API. Mengembalikan false bila konfigurasi belum ada
 * atau API menolak permintaan (detail dicatat ke error log, bukan ke klien).
 */
function otp_send_email(string $recipient, string $name, string $code): bool
{
    $apiKey = trim((string)get_env_var('RESEND_API_KEY', ''));
    $from = trim((string)get_env_var('MAIL_FROM', ''));
    $fromName = trim((string)get_env_var('MAIL_FROM_NAME', 'KSM Education'));

    if ($apiKey === '' || $from === '') {
        error_log('OTP email skipped: RESEND_API_KEY / MAIL_FROM belum dikonfigurasi.');
        return false;
    }

    $sender = $fromName !== '' ? sprintf('%s <%s>', $fromName, $from) : $from;
    $payload = json_encode([
        'from' => $sender,
        'to' => [$recipient],
        'subject' => 'Verifikasi Email KSM Journal',
        'html' => otp_email_html($name, $code),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        // Jangan pernah log API key maupun OTP plaintext.
        error_log(sprintf(
            'Resend OTP send failed (HTTP %d): %s',
            $status,
            $curlError !== '' ? $curlError : substr((string)$response, 0, 300)
        ));
        return false;
    }

    return true;
}
