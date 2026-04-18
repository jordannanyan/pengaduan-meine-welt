<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$page_title = $page_title ?? 'Sistem Pengaduan Meine Welt Kafe';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?> - Meine Welt Kafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#4a2c2a">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/public/index.php">
            <i class="bi bi-cup-hot-fill"></i> Meine Welt Kafe
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/index.php">
                        <i class="bi bi-pencil-square"></i> Buat Pengaduan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/track.php">
                        <i class="bi bi-search"></i> Lacak Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">
                        <i class="bi bi-box-arrow-in-right"></i> Login Petugas
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
