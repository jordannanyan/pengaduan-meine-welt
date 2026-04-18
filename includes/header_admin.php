<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');

$user = current_user();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($page_title ?? 'Admin') ?> | Meine Welt Kafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 p-0 sidebar">
            <div class="text-center mb-4">
                <i class="bi bi-cup-hot-fill" style="font-size:2rem"></i>
                <h5 class="mt-2 mb-0">Meine Welt</h5>
                <small class="opacity-75">Panel Admin</small>
            </div>

            <nav>
                <a href="<?= BASE_URL ?>/admin/dashboard.php"
                   class="sidebar-link <?= $current==='dashboard.php'?'active':'' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="<?= BASE_URL ?>/admin/pengaduan.php"
                   class="sidebar-link <?= in_array($current,['pengaduan.php','pengaduan_detail.php'])?'active':'' ?>">
                    <i class="bi bi-inbox-fill"></i> Kelola Pengaduan
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="sidebar-link <?= in_array($current,['users.php','user_form.php'])?'active':'' ?>">
                    <i class="bi bi-people-fill"></i> Kelola User
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php"
                   class="sidebar-link <?= $current==='categories.php'?'active':'' ?>">
                    <i class="bi bi-tags-fill"></i> Kategori
                </a>
                <a href="<?= BASE_URL ?>/admin/reports.php"
                   class="sidebar-link <?= $current==='reports.php'?'active':'' ?>">
                    <i class="bi bi-bar-chart-fill"></i> Laporan
                </a>
                <a href="<?= BASE_URL ?>/admin/logs.php"
                   class="sidebar-link <?= $current==='logs.php'?'active':'' ?>">
                    <i class="bi bi-clock-history"></i> Log Aktivitas
                </a>

                <hr class="my-3 opacity-25">

                <a href="<?= BASE_URL ?>/public/index.php" class="sidebar-link" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Lihat Halaman Publik
                </a>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-link">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main -->
        <main class="col-md-9 col-lg-10 py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><?= h($page_title ?? 'Admin') ?></h4>
                    <small class="text-muted"><?= date('l, d F Y') ?></small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= h($user['full_name']) ?>
                        <span class="badge bg-danger ms-1">Admin</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
