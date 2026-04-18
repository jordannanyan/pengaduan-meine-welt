<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Jika sudah login, arahkan ke dashboard sesuai role
if (is_logged_in()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        redirect(BASE_URL . '/admin/dashboard.php');
    } elseif ($role === 'petugas') {
        redirect(BASE_URL . '/petugas/dashboard.php');
    }
    redirect(BASE_URL . '/public/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.username = ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            log_activity($pdo, $user['id'], 'login', 'user', (string)$user['id'],
                'Login berhasil: ' . $username);

            if ($user['role_name'] === 'admin') {
                redirect(BASE_URL . '/admin/dashboard.php');
            } elseif ($user['role_name'] === 'petugas') {
                redirect(BASE_URL . '/petugas/dashboard.php');
            }
            redirect(BASE_URL . '/public/index.php');
        } else {
            $error = 'Username atau password salah.';
            log_activity($pdo, null, 'login_failed', 'user', null,
                'Login gagal untuk username: ' . $username);
        }
    }
}

$page_title = 'Login Petugas';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($page_title) ?> | Meine Welt Kafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #4a2c2a 0%, #8b5a3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            margin: 2rem auto;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.2);
        }
        .brand-logo {
            font-size: 3rem;
            color: #4a2c2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card card border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="brand-logo"><i class="bi bi-cup-hot-fill"></i></div>
                    <h4 class="mt-2 mb-1">Meine Welt Kafe</h4>
                    <div class="text-muted small">Sistem Pengaduan Pelanggan</div>
                </div>

                <h5 class="mb-3 text-center">Login Petugas / Admin</h5>

                <?php if ($error): ?>
                    <div class="alert alert-danger small">
                        <i class="bi bi-exclamation-circle-fill"></i> <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" class="form-control" required autofocus
                                   value="<?= h($_POST['username'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" id="password" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-brand btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Masuk
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                <div class="text-center small">
                    <a href="<?= BASE_URL ?>/public/index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Kembali ke Halaman Pelapor
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePwd').addEventListener('click', function() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>
