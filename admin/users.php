<?php
$page_title = 'Kelola User';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- POST: toggle aktif / hapus ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id === (int)$user['id']) {
        flash_set('users', 'Tidak boleh mengubah akun Anda sendiri.', 'danger');
    } elseif ($action === 'toggle') {
        $stmt = $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        log_activity($pdo, $user['id'], 'toggle_user', 'user', (string)$id, 'Toggle status user');
        flash_set('users', 'Status user diubah.', 'success');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        log_activity($pdo, $user['id'], 'delete_user', 'user', (string)$id, 'User dihapus');
        flash_set('users', 'User dihapus.', 'success');
    }

    redirect(BASE_URL . '/admin/users.php');
}

// ---------- List ----------
$rows = $pdo->query(
    'SELECT u.*, r.name AS role_name
     FROM users u JOIN roles r ON u.role_id = r.id
     ORDER BY u.id ASC'
)->fetchAll();
?>

<?= flash_render('users') ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary"></i> Daftar User</h6>
            <a href="<?= BASE_URL ?>/admin/user_form.php" class="btn btn-primary-brand btn-sm">
                <i class="bi bi-plus-lg"></i> Tambah User
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td class="font-monospace"><?= h($r['username']) ?></td>
                        <td><?= h($r['full_name']) ?></td>
                        <td>
                            <?php if ($r['role_name']==='admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Petugas</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['is_active']): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= tgl_id($r['created_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/user_form.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <?php if ($r['id'] !== (int)$user['id']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning" title="Toggle Aktif">
                                        <i class="bi bi-power"></i>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Hapus user <?= h($r['username']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
