<?php
$page_title = 'Daftar Pengaduan';
require_once __DIR__ . '/../includes/header_petugas.php';

// ---------- Filter ----------
$status   = $_GET['status']   ?? '';
$kategori = $_GET['kategori'] ?? '';
$q        = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($status !== '' && in_array($status, ['new','in_progress','resolved','cancelled'])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($kategori !== '' && in_array($kategori, ['PLY','PRD','HRG','SUI'])) {
    $where[] = 'FIND_IN_SET(?, final_category_code) > 0';
    $params[] = $kategori;
}
if ($q !== '') {
    $where[] = '(ticket_code LIKE ? OR complaint_text LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- Pagination ----------
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 15;
$off  = ($page - 1) * $per;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total / $per);

$sql = "SELECT * FROM complaints $whereSql
        ORDER BY
          CASE status
            WHEN 'new' THEN 1
            WHEN 'in_progress' THEN 2
            ELSE 3
          END,
          submitted_at DESC
        LIMIT $per OFFSET $off";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Cari kode / teks..."
                       value="<?= h($q) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="new"         <?= $status==='new'?'selected':'' ?>>Baru</option>
                    <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>Diproses</option>
                    <option value="resolved"    <?= $status==='resolved'?'selected':'' ?>>Selesai</option>
                    <option value="cancelled"   <?= $status==='cancelled'?'selected':'' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="kategori" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    <option value="PLY" <?= $kategori==='PLY'?'selected':'' ?>>Pelayanan</option>
                    <option value="PRD" <?= $kategori==='PRD'?'selected':'' ?>>Produk</option>
                    <option value="HRG" <?= $kategori==='HRG'?'selected':'' ?>>Harga</option>
                    <option value="SUI" <?= $kategori==='SUI'?'selected':'' ?>>Suasana</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary-brand w-100"><i class="bi bi-funnel-fill"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="small text-muted mb-2">Total <b><?= $total ?></b> pengaduan</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Waktu</th>
                        <th>Ringkasan</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-monospace small"><?= h($r['ticket_code']) ?></td>
                        <td class="small"><?= tgl_id($r['submitted_at']) ?></td>
                        <td class="small" style="max-width:320px">
                            <?= h(mb_strimwidth($r['complaint_text'], 0, 90, '...')) ?>
                        </td>
                        <td><?= kategori_badge($r['final_category_code']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/petugas/pengaduan_detail.php?code=<?= urlencode($r['ticket_code']) ?>"
                               class="btn btn-sm btn-primary-brand">
                                <i class="bi bi-eye"></i> Tangani
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1):
            $qs = $_GET; ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i=1; $i<=$total_pages; $i++):
                        $qs['page'] = $i; ?>
                        <li class="page-item <?= $i===$page?'active':'' ?>">
                            <a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
