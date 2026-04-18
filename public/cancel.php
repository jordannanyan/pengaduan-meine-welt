<?php
// ================================================================
// BATALKAN PENGADUAN OLEH PELAPOR
// Hanya boleh dibatalkan jika status masih 'new' (belum ditangani)
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/public/track.php');
}

$code = trim($_POST['code'] ?? '');

if ($code === '') {
    flash_set('track', 'Kode laporan tidak valid.', 'danger');
    redirect(BASE_URL . '/public/track.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE ticket_code = ? FOR UPDATE');
    $stmt->execute([$code]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        throw new Exception('Laporan tidak ditemukan.');
    }

    if ($complaint['status'] !== 'new') {
        throw new Exception('Laporan tidak dapat dibatalkan karena sudah dalam proses penanganan.');
    }

    // Update status
    $stmt = $pdo->prepare('UPDATE complaints SET status = "cancelled" WHERE id = ?');
    $stmt->execute([$complaint['id']]);

    // Catat status history
    $stmt = $pdo->prepare(
        'INSERT INTO status_history (complaint_id, from_status, to_status, reason)
         VALUES (?, ?, "cancelled", "Dibatalkan oleh pelapor")'
    );
    $stmt->execute([$complaint['id'], $complaint['status']]);

    // Log aktivitas
    log_activity($pdo, null, 'cancel_complaint', 'complaint', (string)$complaint['id'],
        'Pengaduan ' . $code . ' dibatalkan oleh pelapor');

    $pdo->commit();

    flash_set('track', 'Pengaduan berhasil dibatalkan.', 'success');

} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('track', 'Gagal membatalkan: ' . $e->getMessage(), 'danger');
}

redirect(BASE_URL . '/public/track.php?code=' . urlencode($code));
