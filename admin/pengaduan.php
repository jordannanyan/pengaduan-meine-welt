<?php
$page_title = 'Kelola Pengaduan';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- Filter ----------
$status     = $_GET['status']     ?? '';
$kategori   = $_GET['kategori']   ?? '';
$sentimen   = $_GET['sentimen']   ?? '';
$order_type = $_GET['order_type'] ?? '';
$q          = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($status !== '' && in_array($status, ['new','in_progress','resolved','cancelled'])) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}
if ($kategori !== '' && in_array($kategori, ['PLY','PRD','HRG','SUI'])) {
    $where[] = 'FIND_IN_SET(?, c.final_category_code) > 0';
    $params[] = $kategori;
}
if ($sentimen !== '' && in_array($sentimen, ['positif','negatif','netral'])) {
    $where[] = 'c.sentiment = ?';
    $params[] = $sentimen;
}
if ($order_type !== '' && in_array($order_type, ['dine_in','take_away','online'])) {
    $where[] = 'c.order_type = ?';
    $params[] = $order_type;
}
if ($q !== '') {
    $where[] = '(c.ticket_code LIKE ? OR c.complaint_text LIKE ? OR c.reporter_name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- Pagination ----------
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 15;
$off  = ($page - 1) * $per;

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM complaints c $whereSql")->execute($params) ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total / $per);

$sql = "SELECT c.* FROM complaints c $whereSql
        ORDER BY c.submitted_at DESC
        LIMIT $per OFFSET $off";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<?= flash_render('admin_pengaduan') ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control" placeholder="Cari kode / teks / nama..."
                       value="<?= h($q) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="new"         <?= $status==='new'?'selected':'' ?>>Baru</option>
                    <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>Diproses</option>
                    <option value="resolved"    <?= $status==='resolved'?'selected':'' ?>>Selesai</option>
                    <option value="cancelled"   <?= $status==='cancelled'?'selected':'' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="kategori" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    <option value="PLY" <?= $kategori==='PLY'?'selected':'' ?>>Pelayanan</option>
                    <option value="PRD" <?= $kategori==='PRD'?'selected':'' ?>>Produk</option>
                    <option value="HRG" <?= $kategori==='HRG'?'selected':'' ?>>Harga</option>
                    <option value="SUI" <?= $kategori==='SUI'?'selected':'' ?>>Suasana</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sentimen" class="form-select">
                    <option value="">-- Semua Sentimen --</option>
                    <option value="positif" <?= $sentimen==='positif'?'selected':'' ?>>Positif</option>
                    <option value="negatif" <?= $sentimen==='negatif'?'selected':'' ?>>Negatif</option>
                    <option value="netral"  <?= $sentimen==='netral'?'selected':'' ?>>Netral</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="order_type" class="form-select">
                    <option value="">-- Semua Pesanan --</option>
                    <option value="dine_in"   <?= $order_type==='dine_in'?'selected':'' ?>>Dine In</option>
                    <option value="take_away" <?= $order_type==='take_away'?'selected':'' ?>>Take Away</option>
                    <option value="online"    <?= $order_type==='online'?'selected':'' ?>>Online</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary-brand w-100"><i class="bi bi-funnel-fill"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
            <div class="small text-muted">
                Menampilkan <b><?= count($rows) ?></b> dari <b><?= $total ?></b> pengaduan
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Waktu</th>
                        <th>Pelapor</th>
                        <th>Pesanan</th>
                        <th>Ringkasan</th>
                        <th>Kategori</th>
                        <th>Sentimen</th>
                        <th>Conf.</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-monospace small"><?= h($r['ticket_code']) ?></td>
                        <td class="small"><?= tgl_id($r['submitted_at']) ?></td>
                        <td class="small">
                            <?php if ($r['is_anonymous']): ?>
                                <span class="badge bg-dark"><i class="bi bi-incognito"></i> Anonim</span>
                            <?php else: ?>
                                <?= h($r['reporter_name'] ?: '-') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= order_type_badge($r['order_type'] ?? null) ?></td>
                        <td class="small" style="max-width:280px">
                            <?= h(mb_strimwidth($r['complaint_text'], 0, 80, '...')) ?>
                        </td>
                        <td><?= kategori_badge($r['final_category_code']) ?></td>
                        <td><?= sentimen_badge($r['sentiment'] ?? null) ?></td>
                        <td class="small">
                            <?php if ($r['predicted_confidence']): ?>
                                <?= number_format($r['predicted_confidence'] * 100, 1) ?>%
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/pengaduan_detail.php?code=<?= urlencode($r['ticket_code']) ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $qs = $_GET; ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++):
                        $qs['page'] = $i;
                        $url = '?' . http_build_query($qs); ?>
                        <li class="page-item <?= $i===$page?'active':'' ?>">
                            <a class="page-link" href="<?= h($url) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
