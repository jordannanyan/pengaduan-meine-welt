<?php
// Redirect ke halaman publik pelanggan
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL . '/public/index.php');
exit;
