<?php
// ================================================================
// KONFIGURASI GLOBAL APLIKASI
// ================================================================

// Base URL aplikasi — otomatis terdeteksi dari request.
// Bisa di-override dengan set env BASE_URL atau ganti manual di bawah.
if (!defined('BASE_URL')) {
    $env_base = getenv('BASE_URL');
    if ($env_base) {
        define('BASE_URL', rtrim($env_base, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443
               || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Deteksi subfolder: /meine-welt-web/public/index.php -> /meine-welt-web
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        // Hilangkan /public, /admin, /petugas, /auth di akhir
        $base_path = preg_replace('#/(public|admin|petugas|auth)$#', '', $script);
        $base_path = rtrim($base_path, '/');

        define('BASE_URL', $scheme . '://' . $host . $base_path);
    }
}

// Path absolut ke Python executable (untuk shell_exec)
// Sesuaikan dengan lokasi Python Anda. Cek dengan: where python
define('PYTHON_PATH', 'python');

// Path absolut ke script predict.py
define('PREDICT_SCRIPT', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'predict.py');

// Folder upload lampiran
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/pdf'
]);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Start session hanya jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}