<?php
$page_title = 'Kategori Pengaduan';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- POST: update deskripsi / toggle aktif ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = $_POST['code'] ?? '';

    if (!in_array($code, ['PLY','PRD','HRG','SUI'])) {
        flash_set('categories', 'Kode kategori tidak valid.', 'danger');
    } elseif ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare('UPDATE categories SET name=?, description=?, is_active=? WHERE code=?');
        $stmt->execute([$name, $desc, $active, $code]);
        log_activity($pdo, $user['id'], 'update_category', 'category', $code, 'Update kategori ' . $code);
        flash_set('categories', 'Kategori diperbarui.', 'success');
    }

    redirect(BASE_URL . '/admin/categories.php');
}

$rows = $pdo->query('SELECT * FROM categories ORDER BY code ASC')->fetchAll();

// Hitung jumlah pengaduan per kategori
$counts = ['PLY'=>0,'PRD'=>0,'HRG'=>0,'SUI'=>0];
$cat_rows = $pdo->query(
    "SELECT final_category_code FROM complaints
     WHERE final_category_code IS NOT NULL AND final_category_code <> ''"
)->fetchAll();
foreach ($cat_rows as $r) {
    foreach (explode(',', $r['final_category_code']) as $c) {
        $c = trim($c);
        if (isset($counts[$c])) $counts[$c]++;
    }
}
?>

<?= flash_render('categories') ?>

<div class="alert alert-info small">
    <i class="bi bi-info-circle-fill"></i>
    Kategori bersifat <b>tetap</b> mengikuti model AI (4 aspek). Anda hanya dapat mengubah nama tampilan, deskripsi, dan status aktif.
</div>

<div class="row g-3">
    <?php foreach ($rows as $r):
        $cnt = $counts[$r['code']] ?? 0;
    ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <?= kategori_badge($r['code']) ?>
                        <span class="small text-muted ms-2 font-monospace"><?= h($r['code']) ?></span>
                    </div>
                    <span class="badge bg-light text-dark border">
                        <?= $cnt ?> pengaduan
                    </span>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="code" value="<?= h($r['code']) ?>">

                    <div class="mb-2">
                        <label class="form-label small mb-1">Nama Tampilan</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               value="<?= h($r['name']) ?>" required maxlength="50">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Deskripsi</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"
                                  maxlength="255"><?= h($r['description']) ?></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active"
                               id="act_<?= $r['code'] ?>" <?= $r['is_active']?'checked':'' ?>>
                        <label class="form-check-label small" for="act_<?= $r['code'] ?>">Aktif</label>
                    </div>
                    <button class="btn btn-sm btn-primary-brand">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
