<?php

/**
 * Diagnostik alur pesan kontak (user -> admin).
 *
 * Menampilkan isi tabel contact_messages beserta ringkasan status, sehingga
 * bisa dibedakan antara "pesan tidak tersimpan" (masalah di send_contact.php)
 * dan "pesan tersimpan tapi tidak tampil" (masalah di panel admin).
 *
 * Jalankan: php tools/diag_contact_inbox.php
 */

require_once __DIR__ . '/../services/db.php';

function line(string $text = ''): void
{
    echo $text, PHP_EOL;
}

try {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
    line("Total pesan di contact_messages: {$total}");
    line();

    line('Ringkasan per status:');
    $statuses = $pdo->query(
        'SELECT status, COUNT(*) AS jumlah
         FROM contact_messages
         GROUP BY status
         ORDER BY jumlah DESC'
    )->fetchAll();
    if (!$statuses) {
        line('  (kosong)');
    }
    foreach ($statuses as $row) {
        line(sprintf('  %-10s %s', (string)$row['status'], (string)$row['jumlah']));
    }
    line();

    line('20 pesan terbaru:');
    $rows = $pdo->query(
        'SELECT id, user_id, name, email, subject, status, created_at
         FROM contact_messages
         ORDER BY id DESC
         LIMIT 20'
    )->fetchAll();

    if (!$rows) {
        line('  (kosong) -> pesan tidak pernah sampai ke database.');
    }
    foreach ($rows as $row) {
        line(sprintf(
            '  #%-4s status=%-8s user_id=%-5s %s <%s> | %s | %s',
            (string)$row['id'],
            (string)$row['status'],
            $row['user_id'] === null ? '-' : (string)$row['user_id'],
            (string)$row['name'],
            (string)$row['email'],
            (string)$row['subject'],
            (string)$row['created_at']
        ));
    }
    line();

    // Kolom tabel: memastikan skema lokal sinkron dengan yang dipakai endpoint admin.
    line('Kolom tabel contact_messages:');
    $cols = $pdo->query('SHOW COLUMNS FROM contact_messages')->fetchAll();
    $names = array_map(static fn ($c) => (string)$c['Field'], $cols);
    line('  ' . implode(', ', $names));

    $expected = [
        'id', 'user_id', 'name', 'email', 'subject', 'message', 'status',
        'admin_reply', 'replied_by', 'replied_at', 'read_at', 'closed_at',
        'created_at', 'updated_at',
    ];
    $missing = array_values(array_diff($expected, $names));
    line($missing === [] ? '  semua kolom yang dipakai endpoint admin tersedia' : '  KOLOM HILANG: ' . implode(', ', $missing));
} catch (Throwable $e) {
    line('ERROR: ' . $e->getMessage());
    exit(1);
}
