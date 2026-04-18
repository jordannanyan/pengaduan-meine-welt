<?php
$page_title = 'Lacak Laporan';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header_public.php';

$code = trim($_GET['code'] ?? '');
$complaint = null;
$history = [];
$followups = [];
$attachments = [];
$not_found = false;

if ($code !== '') {
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE ticket_code = ?');
    $stmt->execute([$code]);
    $complaint = $stmt->fetch();

    if ($complaint) {
        $cid = $complaint['id'];

        $stmt = $pdo->prepare('SELECT * FROM status_history WHERE complaint_id = ? ORDER BY changed_at ASC');
        $stmt->execute([$cid]);
        $history = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT f.*, u.full_name AS actor_name
             FROM followups f LEFT JOIN users u ON f.actor_user_id = u.id
             WHERE f.complaint_id = ? AND f.visibility = "public"
             ORDER BY f.created_at ASC'
        );
        $stmt->execute([$cid]);
        $followups = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT * FROM attachments WHERE complaint_id = ? ORDER BY created_at ASC');
        $stmt->execute([$cid]);
        $attachments = $stmt->fetchAll();
    } else {
        $not_found = true;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <?= flash_render('track') ?>

        <div class="card card-form mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3"><i class="bi bi-search text-primary"></i> Lacak Status Pengaduan</h4>
                <form method="GET" class="row g-2">
                    <div class="col-md-9">
                        <input type="text" name="code" class="form-control form-control-lg"
                               placeholder="Masukkan kode laporan (contoh: MWK-250101-A3F2)"
                               value="<?= h($code) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary-brand btn-lg w-100">
                            <i class="bi bi-arrow-right"></i> Cek
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($not_found): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Kode laporan <b><?= h($code) ?></b> tidak ditemukan. Pastikan kode benar.
            </div>
        <?php endif; ?>

        <?php if ($complaint): ?>
            <div class="card card-form mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">Detail Pengaduan</h5>
                            <div class="text-muted small">
                                Kode: <b class="font-monospace"><?= h($complaint['ticket_code']) ?></b>
                                &middot; Dikirim: <?= tgl_id($complaint['submitted_at']) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <?= status_badge($complaint['status']) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Deskripsi:</div>
                        <div class="border rounded p-3 bg-light"><?= nl2br(h($complaint['complaint_text'])) ?></div>
                    </div>

                    <?php if (!$complaint['is_anonymous']): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Nama:</div>
                                <div><?= h($complaint['reporter_name'] ?: '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Kontak:</div>
                                <div><?= h($complaint['reporter_contact'] ?: '-') ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <div class="text-muted small">Kategori (hasil klasifikasi):</div>
                        <?= kategori_badge($complaint['final_category_code'] ?: $complaint['predicted_category_code']) ?>
                        <?php if ($complaint['predicted_confidence']): ?>
                            <div class="small text-muted mt-1">
                                Tingkat kepercayaan model: <?= number_format($complaint['predicted_confidence'] * 100, 1) ?>%
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($attachments): ?>
                        <div class="mb-3">
                            <div class="text-muted small mb-1">Lampiran:</div>
                            <?php foreach ($attachments as $a): ?>
                                <a href="<?= UPLOAD_URL . '/' . h($a['file_path']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline-primary me-2 mb-1">
                                    <i class="bi bi-paperclip"></i> <?= h($a['file_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($complaint['status'] === 'new'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/public/cancel.php"
                              onsubmit="return confirm('Yakin membatalkan pengaduan ini?')">
                            <input type="hidden" name="code" value="<?= h($complaint['ticket_code']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-circle"></i> Batalkan Laporan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($followups): ?>
                <div class="card card-form mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><i class="bi bi-chat-left-dots text-primary"></i> Tindak Lanjut dari Petugas</h5>
                        <?php foreach ($followups as $f): ?>
                            <div class="border-start border-3 border-primary ps-3 mb-3">
                                <div class="small text-muted">
                                    <b><?= h($f['actor_name'] ?? 'Petugas') ?></b>
                                    &middot; <?= tgl_id($f['created_at']) ?>
                                </div>
                                <div><?= nl2br(h($f['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card card-form">
                <div class="card-body p-4">
                    <h5 class="mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat Status</h5>
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item">
                                <div class="small text-muted"><?= tgl_id($h['changed_at']) ?></div>
                                <div>
                                    <?php if ($h['from_status']): ?>
                                        <?= status_badge($h['from_status']) ?> <i class="bi bi-arrow-right"></i>
                                    <?php endif; ?>
                                    <?= status_badge($h['to_status']) ?>
                                </div>
                                <?php if ($h['reason']): ?>
                                    <div class="small mt-1"><?= h($h['reason']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
