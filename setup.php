<?php
// ================================================================
// SETUP SCRIPT
// Jalankan sekali setelah import database.sql untuk:
// - Reset password admin & petugas dengan hash yang benar
// - Cek koneksi Python
// ================================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$messages = [];

// ----------------------------------------------------------------
// 1. Reset password default
// ----------------------------------------------------------------
$default_users = [
    ['username' => 'admin',   'password' => 'admin123',   'role_id' => 1, 'full_name' => 'Administrator Kafe'],
    ['username' => 'petugas', 'password' => 'petugas123', 'role_id' => 2, 'full_name' => 'Petugas Kafe'],
];

foreach ($default_users as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$u['username']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $pdo->prepare('UPDATE users SET password_hash = ?, full_name = ?, role_id = ?, is_active = 1 WHERE username = ?');
        $upd->execute([$hash, $u['full_name'], $u['role_id'], $u['username']]);
        $messages[] = "User <b>{$u['username']}</b> password direset.";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (role_id, username, password_hash, full_name, is_active) VALUES (?, ?, ?, ?, 1)');
        $ins->execute([$u['role_id'], $u['username'], $hash, $u['full_name']]);
        $messages[] = "User <b>{$u['username']}</b> dibuat.";
    }
}

// ----------------------------------------------------------------
// 2. Tes koneksi Python
// ----------------------------------------------------------------
$py_test = shell_exec(escapeshellarg(PYTHON_PATH) . ' --version 2>&1');
$py_ok = $py_test && stripos($py_test, 'python') !== false;

// ----------------------------------------------------------------
// 3. Tes prediksi
// ----------------------------------------------------------------
$pred_ok = false;
$pred_output = '';
if ($py_ok) {
    $test_result = classify_complaint('Kopi disini enak banget dan harganya murah');
    $pred_ok = !empty($test_result['success']);
    $pred_output = json_encode($test_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setup - Meine Welt Kafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:720px">
    <h2 class="mb-4">Setup Sistem Pengaduan Meine Welt Kafe</h2>

    <div class="card mb-3">
        <div class="card-header bg-primary text-white">1. Reset User Default</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($messages as $msg): ?>
                <li class="list-group-item"><?= $msg ?></li>
            <?php endforeach; ?>
            <li class="list-group-item">
                <b>Login default:</b><br>
                - Admin: <code>admin</code> / <code>admin123</code><br>
                - Petugas: <code>petugas</code> / <code>petugas123</code>
            </li>
        </ul>
    </div>

    <div class="card mb-3">
        <div class="card-header <?= $py_ok ? 'bg-success' : 'bg-danger' ?> text-white">2. Tes Python</div>
        <div class="card-body">
            <p><b>Path:</b> <code><?= h(PYTHON_PATH) ?></code></p>
            <pre class="bg-light p-2"><?= h(trim($py_test ?? '(tidak ada output)')) ?></pre>
            <?php if (!$py_ok): ?>
                <div class="alert alert-danger mb-0">
                    Python tidak terdeteksi. Edit <code>config/config.php</code> dan set <code>PYTHON_PATH</code> ke path Python yang benar.
                    Cek via CMD: <code>where python</code>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header <?= $pred_ok ? 'bg-success' : 'bg-warning text-dark' ?> text-white">3. Tes Prediksi Model</div>
        <div class="card-body">
            <p>Teks uji: <i>"Kopi disini enak banget dan harganya murah"</i></p>
            <?php if ($pred_ok): ?>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <div class="text-muted small">Kategori:</div>
                        <?= kategori_badge($test_result['kode_string'] ?? null) ?>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Sentimen:</div>
                        <?= sentimen_badge($test_result['sentimen'] ?? null, $test_result['sentimen_confidence'] ?? null) ?>
                    </div>
                </div>
            <?php endif; ?>
            <pre class="bg-light p-2" style="max-height:300px;overflow:auto"><?= h($pred_output) ?></pre>
            <?php if (!$pred_ok): ?>
                <div class="alert alert-warning mb-0">
                    Prediksi gagal. Pastikan file <code>model_nb_binary_relevance.pkl</code>, <code>tfidf_vectorizer.pkl</code>, <code>mlb_transformer.pkl</code>, dan <code>model_sentimen.pkl</code> sudah di-copy ke folder ini.
                    Juga pastikan package <code>joblib</code>, <code>scikit-learn</code>, dan <code>Sastrawi</code> sudah terinstall di Python.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-primary">Buka Aplikasi</a>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-secondary">Login</a>

    <div class="mt-4 text-muted small">
        <b>Catatan:</b> Hapus file <code>setup.php</code> setelah setup selesai untuk alasan keamanan.
    </div>
</div>
</body>
</html>
