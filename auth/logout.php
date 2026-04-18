<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

if (is_logged_in()) {
    $uid = $_SESSION['user_id'] ?? null;
    $uname = $_SESSION['username'] ?? '';
    log_activity($pdo, $uid, 'logout', 'user', (string)$uid, 'Logout: ' . $uname);
}

logout_user();
redirect(BASE_URL . '/auth/login.php');
