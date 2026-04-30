<?php
$page_title = 'Tangani Pengaduan';
require_once __DIR__ . '/../includes/header_petugas.php';

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    redirect(BASE_URL . '/petugas/pengaduan.php');
}

$stmt = $pdo->prepare('SELECT * FROM complaints WHERE ticket_code = ?');
$stmt->execute([$code]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo '<div class="alert alert-danger">Pengaduan tidak ditemukan.</div>';
    require_once __DIR__ . '/../includes/footer_panel.php';
    exit;
}
$cid = $complaint['id'];

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($action === 'change_status') {
            // Petugas hanya boleh ubah: new→in_progress, in_progress→resolved
            $new_status = $_POST['new_status'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            $allowed_transitions = [
                'new'         => ['in_progress'],
                'in_progress' => ['resolved'],
            ];
            $allowed = $allowed_transitions[$complaint['status']] ?? [];
            if (!in_array($new_status, $allowed)) {
                throw new Exception('Transisi status tidak diizinkan.');
            }

            $stmt = $pdo->prepare('UPDATE complaints SET status = ? WHERE id = ?');
            $stmt->execute([$new_status, $cid]);

            $stmt = $pdo->prepare(
                'INSERT INTO status_history (complaint_id, changed_by_user_id, from_status, to_status, reason)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$cid, $user['id'], $complaint['status'], $new_status, $reason]);

            log_activity($pdo, $user['id'], 'change_status', 'complaint', (string)$cid,
                'Status: ' . $complaint['status'] . ' → ' . $new_status);

            flash_set('petugas_detail', 'Status berhasil diubah.', 'success');
        }
        elseif ($action === 'add_followup') {
            $message = trim($_POST['message'] ?? '');
            $visibility = $_POST['visibility'] === 'internal' ? 'internal' : 'public';

            if ($message === '') {
                throw new Exception('Pesan tidak boleh kosong.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO followups (complaint_id, actor_user_id, message, visibility)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$cid, $user['id'], $message, $visibility]);

            // Jika masih new dan tindak lanjut publik → auto set in_progress
            if ($complaint['status'] === 'new' && $visibility === 'public') {
                $stmt = $pdo->prepare('UPDATE complaints SET status = "in_progress" WHERE id = ?');
                $stmt->execute([$cid]);
                $stmt = $pdo->prepare(
                    'INSERT INTO status_history (complaint_id, changed_by_user_id, from_status, to_status, reason)
                     VALUES (?, ?, "new", "in_progress", "Otomatis: petugas mulai menindaklanjuti")'
                );
                $stmt->execute([$cid, $user['id']]);
            }

            log_activity($pdo, $user['id'], 'add_followup', 'complaint', (string)$cid,
                'Tindak lanjut [' . $visibility . ']');

            flash_set('petugas_detail', 'Tindak lanjut ditambahkan.', 'success');
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('petugas_detail', 'Gagal: ' . $e->getMessage(), 'danger');
    }

    redirect(BASE_URL . '/petugas/pengaduan_detail.php?code=' . urlencode($code));
}

// ---------- Data terkait ----------
$stmt = $pdo->prepare('SELECT * FROM status_history WHERE complaint_id = ? ORDER BY changed_at ASC');
$stmt->execute([$cid]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT f.*, u.full_name AS actor_name
     FROM followups f LEFT JOIN users u ON f.actor_user_id = u.id
     WHERE f.complaint_id = ? ORDER BY f.created_at ASC'
);
$stmt->execute([$cid]);
$followups = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM attachments WHERE complaint_id = ? ORDER BY created_at ASC');
$stmt->execute([$cid]);
$attachments = $stmt->fetchAll();
?>

<a href="<?= BASE_URL ?>/petugas/pengaduan.php" class="btn btn-sm btn-light mb-3">
    <i class="bi bi-arrow-left"></i> Kembali
</a>

<?= flash_render('petugas_detail') ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1"><span class="font-monospace"><?= h($complaint['ticket_code']) ?></span></h5>
                        <div class="text-muted small">Dikirim: <?= tgl_id($complaint['submitted_at']) ?></div>
                    </div>
                    <?= status_badge($complaint['status']) ?>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Tipe Pesanan:</div>
                    <?= order_type_badge($complaint['order_type'] ?? null) ?>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Deskripsi:</div>
                    <div class="border rounded p-3 bg-light"><?= nl2br(h($complaint['complaint_text'])) ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Pelapor:</div>
                        <?php if ($complaint['is_anonymous']): ?>
                            <span class="badge bg-dark"><i class="bi bi-incognito"></i> Anonim</span>
                        <?php else: ?>
                            <div><?= h($complaint['reporter_name'] ?: '-') ?></div>
                            <div class="small text-muted"><?= h($complaint['reporter_contact'] ?: '-') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Kategori:</div>
                        <?= kategori_badge($complaint['final_category_code']) ?>
                        <?php if ($complaint['predicted_confidence']): ?>
                            <div class="small text-muted mt-1">
                                Confidence: <?= number_format($complaint['predicted_confidence'] * 100, 1) ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Sentimen:</div>
                        <?= sentimen_badge($complaint['sentiment'] ?? null, isset($complaint['sentiment_confidence']) ? (float)$complaint['sentiment_confidence'] : null) ?>
                    </div>
                </div>

                <?php if ($attachments): ?>
                    <hr>
                    <div class="text-muted small mb-1">Lampiran:</div>
                    <?php foreach ($attachments as $a): ?>
                        <a href="<?= UPLOAD_URL . '/' . h($a['file_path']) ?>" target="_blank"
                           class="btn btn-sm btn-outline-primary me-2 mb-1">
                            <i class="bi bi-paperclip"></i> <?= h($a['file_name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tindak lanjut -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-dots-fill text-primary"></i> Riwayat Tindak Lanjut</h6>

                <?php if (!$followups): ?>
                    <p class="text-muted small">Belum ada tindak lanjut.</p>
                <?php else: foreach ($followups as $f): ?>
                    <div class="border-start border-3 ps-3 mb-3
                                <?= $f['visibility']==='internal' ? 'border-warning' : 'border-primary' ?>">
                        <div class="d-flex justify-content-between small">
                            <div>
                                <b><?= h($f['actor_name'] ?? 'Sistem') ?></b>
                                <span class="text-muted">&middot; <?= tgl_id($f['created_at']) ?></span>
                            </div>
                            <?php if ($f['visibility']==='internal'): ?>
                                <span class="badge bg-warning text-dark">Internal</span>
                            <?php else: ?>
                                <span class="badge bg-success">Publik</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-1"><?= nl2br(h($f['message'])) ?></div>
                    </div>
                <?php endforeach; endif; ?>

                <?php if (in_array($complaint['status'], ['new','in_progress'])): ?>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_followup">
                        <div class="mb-2">
                            <textarea name="message" class="form-control" rows="3"
                                      placeholder="Tulis tindak lanjut untuk pengaduan ini..." required></textarea>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <select name="visibility" class="form-select form-select-sm" style="max-width:220px">
                                <option value="public">Publik (dibaca pelapor)</option>
                                <option value="internal">Internal (catatan petugas)</option>
                            </select>
                            <button class="btn btn-primary-brand btn-sm">
                                <i class="bi bi-send-fill"></i> Kirim
                            </button>
                        </div>
                        <?php if ($complaint['status'] === 'new'): ?>
                            <div class="form-text mt-2">
                                <i class="bi bi-info-circle"></i>
                                Tindak lanjut <b>publik</b> akan otomatis mengubah status menjadi <b>Diproses</b>.
                            </div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary small mb-0">
                        <i class="bi bi-lock-fill"></i>
                        Pengaduan ini sudah <?= status_badge($complaint['status']) ?> &mdash; tidak dapat ditindaklanjuti lagi.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom kanan -->
    <div class="col-lg-4">
        <!-- Aksi status -->
        <?php if (in_array($complaint['status'], ['new','in_progress'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-arrow-repeat text-primary"></i> Update Status</h6>

                <?php if ($complaint['status'] === 'new'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="new_status" value="in_progress">
                        <div class="mb-2">
                            <input type="text" name="reason" class="form-control form-control-sm"
                                   placeholder="Alasan (opsional)" maxlength="255">
                        </div>
                        <button class="btn btn-sm btn-warning w-100">
                            <i class="bi bi-gear-fill"></i> Mulai Proses
                        </button>
                    </form>
                <?php elseif ($complaint['status'] === 'in_progress'): ?>
                    <form method="POST" onsubmit="return confirm('Tandai pengaduan sebagai Selesai?')">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="new_status" value="resolved">
                        <div class="mb-2">
                            <input type="text" name="reason" class="form-control form-control-sm"
                                   placeholder="Ringkasan penyelesaian" maxlength="255">
                        </div>
                        <button class="btn btn-sm btn-success w-100">
                            <i class="bi bi-check-circle-fill"></i> Tandai Selesai
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat Status</h6>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="small text-muted"><?= tgl_id($h['changed_at']) ?></div>
                            <div class="small">
                                <?php if ($h['from_status']): ?>
                                    <?= status_badge($h['from_status']) ?> <i class="bi bi-arrow-right"></i>
                                <?php endif; ?>
                                <?= status_badge($h['to_status']) ?>
                            </div>
                            <?php if ($h['reason']): ?>
                                <div class="small mt-1 text-muted"><?= h($h['reason']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
