<?php
$page_title = 'Form User';
require_once __DIR__ . '/../includes/header_admin.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$edit_user = null;

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        flash_set('users', 'User tidak ditemukan.', 'danger');
        redirect(BASE_URL . '/admin/users.php');
    }
}

// ---------- POST ----------
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $password  = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $full_name === '' || !in_array($role_id, [1,2])) {
        $error = 'Lengkapi semua field wajib.';
    } elseif (!$is_edit && strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($is_edit && $password !== '' && strlen($password) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } else {
        // Cek username unik
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->execute([$username, $is_edit ? $id : 0]);
        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan.';
        }
    }

    if (!$error) {
        try {
            if ($is_edit) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'UPDATE users SET username=?, full_name=?, role_id=?, is_active=?, password_hash=? WHERE id=?'
                    );
                    $stmt->execute([$username, $full_name, $role_id, $is_active, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET username=?, full_name=?, role_id=?, is_active=? WHERE id=?'
                    );
                    $stmt->execute([$username, $full_name, $role_id, $is_active, $id]);
                }
                log_activity($pdo, $user['id'], 'update_user', 'user', (string)$id, 'Update user: ' . $username);
                flash_set('users', 'User berhasil diperbarui.', 'success');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, full_name, role_id, is_active, password_hash)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$username, $full_name, $role_id, $is_active, $hash]);
                $new_id = (int)$pdo->lastInsertId();
                log_activity($pdo, $user['id'], 'create_user', 'user', (string)$new_id, 'Buat user: ' . $username);
                flash_set('users', 'User berhasil dibuat.', 'success');
            }
            redirect(BASE_URL . '/admin/users.php');
        } catch (Exception $e) {
            $error = 'Gagal simpan: ' . $e->getMessage();
        }
    }
}

$val = function($k, $default='') use ($edit_user) {
    if ($_SERVER['REQUEST_METHOD']==='POST') return $_POST[$k] ?? $default;
    return $edit_user[$k] ?? $default;
};
?>

<a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-light mb-3">
    <i class="bi bi-arrow-left"></i> Kembali
</a>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="mb-3">
                    <i class="bi bi-person-<?= $is_edit ? 'gear' : 'plus' ?>-fill text-primary"></i>
                    <?= $is_edit ? 'Edit User' : 'Tambah User Baru' ?>
                </h5>

                <?php if ($error): ?>
                    <div class="alert alert-danger small"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required maxlength="50"
                               value="<?= h($val('username')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required maxlength="100"
                               value="<?= h($val('full_name')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="1" <?= $val('role_id')==1?'selected':'' ?>>Admin</option>
                            <option value="2" <?= $val('role_id')==2?'selected':'' ?>>Petugas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Password <?= $is_edit ? '' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="password" name="password" class="form-control" minlength="6"
                               <?= $is_edit ? '' : 'required' ?>>
                        <?php if ($is_edit): ?>
                            <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                        <?php else: ?>
                            <div class="form-text">Minimal 6 karakter.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?= $val('is_active', 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary-brand">
                            <i class="bi bi-save-fill"></i> <?= $is_edit ? 'Update' : 'Simpan' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
