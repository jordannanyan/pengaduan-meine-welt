<?php
// ================================================================
// KONFIGURASI GLOBAL APLIKASI
// ================================================================

// Base URL aplikasi (sesuaikan dengan path di XAMPP)
define('BASE_URL', 'http://localhost/meine-welt-web');

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