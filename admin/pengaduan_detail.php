<?php
$page_title = 'Detail Pengaduan';
require_once __DIR__ . '/../includes/header_admin.php';

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    redirect(BASE_URL . '/admin/pengaduan.php');
}

// ---------- Ambil data ----------
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

        if ($action === 'override_category') {
            $codes = $_POST['categories'] ?? [];
            $codes = array_filter($codes, fn($c) => in_array($c, ['PLY','PRD','HRG','SUI']));
            $new = implode(',', $codes);

            $stmt = $pdo->prepare('UPDATE complaints SET final_category_code = ? WHERE id = ?');
            $stmt->execute([$new, $cid]);

            log_activity($pdo, $user['id'], 'override_category', 'complaint', (string)$cid,
                'Override kategori: ' . ($complaint['final_category_code'] ?? '-') . ' → ' . ($new ?: '-'));

            flash_set('admin_detail', 'Kategori berhasil di-override.', 'success');
        }
        elseif ($action === 'change_status') {
            $new_status = $_POST['new_status'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            if (!in_array($new_status, ['new','in_progress','resolved','cancelled'])) {
                throw new Exception('Status tidak valid.');
            }
            if ($new_status === $complaint['status']) {
                throw new Exception('Status sudah sama.');
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

            flash_set('admin_detail', 'Status berhasil diubah.', 'success');
        }
        elseif ($action === 'add_followup') {
            $message = trim($_POST['message'] ?? '');
            $visibility = $_POST['visibility'] === 'internal' ? 'internal' : 'public';

            if ($message === '') {
                throw new Exception('Pesan tindak lanjut tidak boleh kosong.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO followups (complaint_id, actor_user_id, message, visibility)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$cid, $user['id'], $message, $visibility]);

            log_activity($pdo, $user['id'], 'add_followup', 'complaint', (string)$cid,
                'Tindak lanjut [' . $visibility . ']');

            flash_set('admin_detail', 'Tindak lanjut ditambahkan.', 'success');
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('admin_detail', 'Gagal: ' . $e->getMessage(), 'danger');
    }

    redirect(BASE_URL . '/admin/pengaduan_detail.php?code=' . urlencode($code));
}

// ---------- Ambil data terkait ----------
$stmt = $pdo->prepare('SELECT * FROM status_history WHERE complaint_id = ? ORDER BY changed_at ASC');
$stmt->execute([$cid]);
$history = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT f.*, u.full_name AS actor_name, u.username
     FROM followups f LEFT JOIN users u ON f.actor_user_id = u.id
     WHERE f.complaint_id = ? ORDER BY f.created_at ASC'
);
$stmt->execute([$cid]);
$followups = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM attachments WHERE complaint_id = ? ORDER BY created_at ASC');
$stmt->execute([$cid]);
$attachments = $stmt->fetchAll();

$final_codes = array_filter(array_map('trim', explode(',', $complaint['final_category_code'] ?? '')));
?>

<a href="<?= BASE_URL ?>/admin/pengaduan.php" class="btn btn-sm btn-light mb-3">
    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
</a>

<?= flash_render('admin_detail') ?>

<div class="row g-3">
    <!-- Kolom kiri: detail -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-file-text-fill text-primary"></i>
                            Pengaduan <span class="font-monospace"><?= h($complaint['ticket_code']) ?></span>
                        </h5>
                        <div class="text-muted small">Dikirim: <?= tgl_id($complaint['submitted_at']) ?></div>
                    </div>
                    <?= status_badge($complaint['status']) ?>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Deskripsi:</div>
                    <div class="border rounded p-3 bg-light"><?= nl2br(h($complaint['complaint_text'])) ?></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Pelapor:</div>
                        <?php if ($complaint['is_anonymous']): ?>
                            <span class="badge bg-dark"><i class="bi bi-incognito"></i> Anonim</span>
                        <?php else: ?>
                            <div><?= h($complaint['reporter_name'] ?: '-') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Kontak:</div>
                        <div><?= h($complaint['is_anonymous'] ? '-' : ($complaint['reporter_contact'] ?: '-')) ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Prediksi Model:</div>
                        <?= kategori_badge($complaint['predicted_category_code']) ?>
                        <?php if ($complaint['predicted_confidence']): ?>
                            <div class="small text-muted mt-1">
                                Confidence: <?= number_format($complaint['predicted_confidence'] * 100, 1) ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Kategori Final:</div>
                        <?= kategori_badge($complaint['final_category_code']) ?>
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
        <div class="card border-0 shadow-sm mb-3">
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

                <hr>
                <form method="POST">
                    <input type="hidden" name="action" value="add_followup">
                    <div class="mb-2">
                        <textarea name="message" class="form-control" rows="3"
                                  placeholder="Tulis tindak lanjut..." required></textarea>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <select name="visibility" class="form-select form-select-sm" style="max-width:200px">
                            <option value="public">Publik (terlihat pelapor)</option>
                            <option value="internal">Internal (hanya petugas)</option>
                        </select>
                        <button class="btn btn-primary-brand btn-sm">
                            <i class="bi bi-send-fill"></i> Kirim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: aksi -->
    <div class="col-lg-4">
        <!-- Override kategori -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-tags-fill text-primary"></i> Override Kategori</h6>
                <p class="small text-muted">Atur ulang kategori final jika prediksi model kurang tepat.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="override_category">
                    <?php $all_cats = ['PLY'=>'Pelayanan','PRD'=>'Produk','HRG'=>'Harga','SUI'=>'Suasana']; ?>
                    <?php foreach ($all_cats as $code_cat => $lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]"
                                   value="<?= $code_cat ?>" id="cat_<?= $code_cat ?>"
                                   <?= in_array($code_cat, $final_codes) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cat_<?= $code_cat ?>"><?= $lbl ?></label>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-sm btn-primary-brand mt-2 w-100">
                        <i class="bi bi-save"></i> Simpan Override
                    </button>
                </form>
            </div>
        </div>

        <!-- Ubah status -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-arrow-repeat text-primary"></i> Ubah Status</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="change_status">
                    <div class="mb-2">
                        <select name="new_status" class="form-select" required>
                            <option value="">-- Pilih Status --</option>
                            <option value="new">Baru</option>
                            <option value="in_progress">Diproses</option>
                            <option value="resolved">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="reason" class="form-control" placeholder="Alasan (opsional)" maxlength="255">
                    </div>
                    <button class="btn btn-sm btn-primary-brand w-100">
                        <i class="bi bi-check2"></i> Update Status
                    </button>
                </form>
            </div>
        </div>

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
