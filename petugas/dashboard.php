<?php
$page_title = 'Dashboard Petugas';
require_once __DIR__ . '/../includes/header_petugas.php';

// Statistik untuk petugas (fokus ke yang perlu ditindaklanjuti)
$new   = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='new'")->fetchColumn();
$inprog= (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='in_progress'")->fetchColumn();
$done  = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='resolved'")->fetchColumn();

// Pengaduan yang ditangani petugas ini
$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT complaint_id) FROM followups WHERE actor_user_id = ?"
);
$stmt->execute([$user['id']]);
$my_followups = (int)$stmt->fetchColumn();

// Pengaduan baru (belum ditangani)
$baru = $pdo->query(
    "SELECT ticket_code, submitted_at, final_category_code, sentiment, complaint_text
     FROM complaints WHERE status='new'
     ORDER BY submitted_at ASC LIMIT 10"
)->fetchAll();

// Pengaduan sedang diproses
$proses = $pdo->query(
    "SELECT ticket_code, submitted_at, final_category_code, sentiment, complaint_text
     FROM complaints WHERE status='in_progress'
     ORDER BY submitted_at ASC LIMIT 10"
)->fetchAll();
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Perlu Ditangani</div>
                        <h3 class="mb-0 text-primary"><?= $new ?></h3>
                    </div>
                    <div class="fs-1 text-primary opacity-50">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Diproses</div>
                        <h3 class="mb-0 text-warning"><?= $inprog ?></h3>
                    </div>
                    <div class="fs-1 text-warning opacity-50">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Selesai</div>
                        <h3 class="mb-0 text-success"><?= $done ?></h3>
                    </div>
                    <div class="fs-1 text-success opacity-50">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Kontribusi Saya</div>
                        <h3 class="mb-0 text-info"><?= $my_followups ?></h3>
                    </div>
                    <div class="fs-1 text-info opacity-50">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                </div>
                <small class="text-muted">Pengaduan yang saya tangani</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bell-fill text-primary"></i> Pengaduan Baru</h6>
                    <a href="<?= BASE_URL ?>/petugas/pengaduan.php?status=new" class="btn btn-sm btn-outline-primary">
                        Semua
                    </a>
                </div>
                <?php if (!$baru): ?>
                    <p class="text-muted small">Tidak ada pengaduan baru.</p>
                <?php else: foreach ($baru as $b): ?>
                    <a href="<?= BASE_URL ?>/petugas/pengaduan_detail.php?code=<?= urlencode($b['ticket_code']) ?>"
                       class="d-block border-bottom py-2 text-decoration-none text-body">
                        <div class="d-flex justify-content-between">
                            <div class="font-monospace small"><?= h($b['ticket_code']) ?></div>
                            <div class="small text-muted"><?= tgl_id($b['submitted_at']) ?></div>
                        </div>
                        <div class="small"><?= h(mb_strimwidth($b['complaint_text'], 0, 90, '...')) ?></div>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?= kategori_badge($b['final_category_code']) ?>
                            <?= sentimen_badge($b['sentiment'] ?? null) ?>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-gear-fill text-warning"></i> Sedang Diproses</h6>
                    <a href="<?= BASE_URL ?>/petugas/pengaduan.php?status=in_progress" class="btn btn-sm btn-outline-primary">
                        Semua
                    </a>
                </div>
                <?php if (!$proses): ?>
                    <p class="text-muted small">Tidak ada pengaduan yang sedang diproses.</p>
                <?php else: foreach ($proses as $b): ?>
                    <a href="<?= BASE_URL ?>/petugas/pengaduan_detail.php?code=<?= urlencode($b['ticket_code']) ?>"
                       class="d-block border-bottom py-2 text-decoration-none text-body">
                        <div class="d-flex justify-content-between">
                            <div class="font-monospace small"><?= h($b['ticket_code']) ?></div>
                            <div class="small text-muted"><?= tgl_id($b['submitted_at']) ?></div>
                        </div>
                        <div class="small"><?= h(mb_strimwidth($b['complaint_text'], 0, 90, '...')) ?></div>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?= kategori_badge($b['final_category_code']) ?>
                            <?= sentimen_badge($b['sentiment'] ?? null) ?>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
