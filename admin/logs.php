<?php
$page_title = 'Log Aktivitas';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- Filter ----------
$action_f = $_GET['action'] ?? '';
$user_f   = $_GET['user'] ?? '';

$where = [];
$params = [];
if ($action_f !== '') { $where[] = 'l.action = ?'; $params[] = $action_f; }
if ($user_f !== '')   { $where[] = 'u.username LIKE ?'; $params[] = '%' . $user_f . '%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- Pagination ----------
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$off = ($page - 1) * $per;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON l.actor_user_id = u.id $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total / $per);

$sql = "SELECT l.*, u.username, u.full_name
        FROM activity_logs l LEFT JOIN users u ON l.actor_user_id = u.id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT $per OFFSET $off";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Daftar action unik untuk filter
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="user" class="form-control" placeholder="Cari username..."
                       value="<?= h($user_f) ?>">
            </div>
            <div class="col-md-4">
                <select name="action" class="form-select">
                    <option value="">-- Semua Action --</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= h($a) ?>" <?= $action_f===$a?'selected':'' ?>><?= h($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary-brand w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="?" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="small text-muted mb-2">Total <b><?= $total ?></b> log</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="small"><?= tgl_id($l['created_at']) ?></td>
                        <td class="small">
                            <?php if ($l['username']): ?>
                                <b><?= h($l['username']) ?></b>
                                <div class="text-muted"><?= h($l['full_name']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">(publik/sistem)</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info text-dark"><?= h($l['action']) ?></span></td>
                        <td class="small">
                            <?= h($l['entity_type']) ?>
                            <?php if ($l['entity_id']): ?>
                                <span class="text-muted">#<?= h($l['entity_id']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= h($l['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada log.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1):
            $qs = $_GET; ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $start = max(1, $page - 3);
                    $end = min($total_pages, $page + 3);
                    for ($i = $start; $i <= $end; $i++):
                        $qs['page'] = $i;
                    ?>
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
