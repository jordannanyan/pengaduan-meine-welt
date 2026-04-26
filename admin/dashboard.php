<?php
$page_title = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- Statistik ----------
$total = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$new   = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='new'")->fetchColumn();
$inprog= (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='in_progress'")->fetchColumn();
$done  = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='resolved'")->fetchColumn();
$cancel= (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status='cancelled'")->fetchColumn();

$total_users = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();

// Pengaduan hari ini
$today = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE DATE(submitted_at)=CURDATE()")->fetchColumn();

// ---------- Distribusi kategori (multi-label) ----------
$cat_rows = $pdo->query(
    "SELECT final_category_code FROM complaints
     WHERE final_category_code IS NOT NULL AND final_category_code <> ''"
)->fetchAll();

$cat_count = ['PLY'=>0,'PRD'=>0,'HRG'=>0,'SUI'=>0];
foreach ($cat_rows as $r) {
    foreach (explode(',', $r['final_category_code']) as $c) {
        $c = trim($c);
        if (isset($cat_count[$c])) $cat_count[$c]++;
    }
}

// ---------- Distribusi sentimen ----------
$sent_rows = $pdo->query(
    "SELECT sentiment, COUNT(*) AS jml
     FROM complaints
     WHERE sentiment IS NOT NULL AND sentiment <> ''
     GROUP BY sentiment"
)->fetchAll();

$sent_count = ['positif'=>0, 'negatif'=>0, 'netral'=>0];
foreach ($sent_rows as $r) {
    $key = strtolower((string)$r['sentiment']);
    if (isset($sent_count[$key])) $sent_count[$key] = (int)$r['jml'];
}

// ---------- 10 pengaduan terbaru ----------
$recent = $pdo->query(
    'SELECT ticket_code, submitted_at, status, final_category_code, sentiment, complaint_text
     FROM complaints
     ORDER BY submitted_at DESC LIMIT 10'
)->fetchAll();
?>

<!-- Kartu statistik -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Total Pengaduan</div>
                        <h3 class="mb-0"><?= $total ?></h3>
                    </div>
                    <div class="fs-1 text-primary opacity-50">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                </div>
                <small class="text-muted">Hari ini: <b><?= $today ?></b></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Baru</div>
                        <h3 class="mb-0 text-primary"><?= $new ?></h3>
                    </div>
                    <div class="fs-1 opacity-50 text-primary">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                </div>
                <small class="text-muted">Menunggu ditangani</small>
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
                    <div class="fs-1 opacity-50 text-warning">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                </div>
                <small class="text-muted">Sedang ditindaklanjuti</small>
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
                    <div class="fs-1 opacity-50 text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <small class="text-muted">Dibatalkan: <b><?= $cancel ?></b></small>
            </div>
        </div>
    </div>
</div>

<!-- Distribusi kategori & user aktif -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-primary"></i> Distribusi Kategori Pengaduan</h6>
                <?php
                $total_cat = array_sum($cat_count);
                $colors = ['PLY'=>'info','PRD'=>'success','HRG'=>'warning','SUI'=>'secondary'];
                foreach ($cat_count as $code => $cnt):
                    $pct = $total_cat > 0 ? round($cnt / $total_cat * 100, 1) : 0;
                ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><?= kategori_badge($code) ?></span>
                            <span><b><?= $cnt ?></b> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-<?= $colors[$code] ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_cat === 0): ?>
                    <p class="text-muted small mb-0">Belum ada data kategori.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-emoji-smile-fill text-primary"></i> Distribusi Sentimen</h6>
                <?php
                $total_sent = array_sum($sent_count);
                $sent_color = ['positif'=>'success','negatif'=>'danger','netral'=>'secondary'];
                $sent_icon  = ['positif'=>'bi-emoji-smile-fill','negatif'=>'bi-emoji-frown-fill','netral'=>'bi-emoji-neutral-fill'];
                foreach ($sent_count as $key => $cnt):
                    $pct = $total_sent > 0 ? round($cnt / $total_sent * 100, 1) : 0;
                ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><i class="bi <?= $sent_icon[$key] ?> text-<?= $sent_color[$key] ?>"></i> <?= ucfirst($key) ?></span>
                            <span><b><?= $cnt ?></b> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-<?= $sent_color[$key] ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_sent === 0): ?>
                    <p class="text-muted small mb-0">Belum ada data sentimen.</p>
                <?php endif; ?>

                <hr class="my-3">
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><i class="bi bi-person-check text-success"></i> User aktif: <b><?= $total_users ?></b></li>
                    <li><i class="bi bi-shield-check text-warning"></i> Role: <b>Administrator</b></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Pengaduan terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary"></i> 10 Pengaduan Terbaru</h6>
            <a href="<?= BASE_URL ?>/admin/pengaduan.php" class="btn btn-sm btn-outline-primary">
                Lihat Semua <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if (!$recent): ?>
            <p class="text-muted small mb-0">Belum ada pengaduan.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Waktu</th>
                            <th>Ringkasan</th>
                            <th>Kategori</th>
                            <th>Sentimen</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td class="font-monospace small"><?= h($r['ticket_code']) ?></td>
                                <td class="small"><?= tgl_id($r['submitted_at']) ?></td>
                                <td class="small"><?= h(mb_strimwidth($r['complaint_text'], 0, 70, '...')) ?></td>
                                <td><?= kategori_badge($r['final_category_code']) ?></td>
                                <td><?= sentimen_badge($r['sentiment'] ?? null) ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/pengaduan_detail.php?code=<?= urlencode($r['ticket_code']) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
