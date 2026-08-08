<?php
require_once __DIR__ . '/../config/functions.php';
check_login();

// Ambil Nama User / Profile Ringkas
$user_display_name = $_SESSION['username'];
if ($_SESSION['role'] === 'anggota' && !empty($_SESSION['id_anggota'])) {
    $stmt_name = $db->prepare("SELECT nama_lengkap FROM anggota WHERE id = ?");
    $stmt_name->execute([$_SESSION['id_anggota']]);
    $res = $stmt_name->fetch();
    if ($res) {
        $user_display_name = $res['nama_lengkap'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Organisasi</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <style>
        :root {
            --sidebar-width: 260px;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1e293b;
            color: #fff;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.8rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: #334155;
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
        }
        @media (max-width: 991.88px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .sidebar.show {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- Navbar Mobile -->
<nav class="navbar navbar-dark bg-dark d-lg-none mb-3">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand mb-0 h1 fs-6">Sistem Organisasi</span>
    </div>
</nav>

<div class="d-flex">
    <!-- Sidebar Desktop & Offcanvas Mobile -->
    <div class="offcanvas-lg offcanvas-start sidebar p-3" tabindex="-1" id="mobileSidebar">
        <div class="d-flex align-items-center mb-4 px-2">
            <i class="bi bi-building fs-3 text-primary me-2"></i>
            <h5 class="fw-bold text-white mb-0">ORG-MANAGER</h5>
        </div>

        <div class="mb-3 px-2 text-muted small text-uppercase fw-bold">
            Role: <span class="badge bg-primary"><?= strtoupper($_SESSION['role']) ?></span>
        </div>

        <nav class="nav flex-column mb-auto">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'dashboard.php') ? 'active' : '' ?>" href="<?= base_url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'anggota.php') ? 'active' : '' ?>" href="<?= base_url('admin/anggota.php') ?>"><i class="bi bi-people"></i> Anggota</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'iuran.php') ? 'active' : '' ?>" href="<?= base_url('admin/iuran.php') ?>"><i class="bi bi-wallet2"></i> Kelola Iuran</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'kas.php') ? 'active' : '' ?>" href="<?= base_url('admin/kas.php') ?>"><i class="bi bi-journal-text"></i> Buku Kas</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'pengumuman.php') ? 'active' : '' ?>" href="<?= base_url('admin/pengumuman.php') ?>"><i class="bi bi-megaphone"></i> Pengumuman</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'chat.php') ? 'active' : '' ?>" href="<?= base_url('admin/chat.php') ?>"><i class="bi bi-chat-dots"></i> Broadcast Chat</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'profile.php') ? 'active' : '' ?>" href="<?= base_url('admin/profile.php') ?>"><i class="bi bi-gear"></i> Pengaturan Akun</a>
            <?php else: ?>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'dashboard.php') ? 'active' : '' ?>" href="<?= base_url('anggota/dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'profile.php') ? 'active' : '' ?>" href="<?= base_url('anggota/profile.php') ?>"><i class="bi bi-person-circle"></i> Profil Saya</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'iuran.php') ? 'active' : '' ?>" href="<?= base_url('anggota/iuran.php') ?>"><i class="bi bi-wallet2"></i> Data Iuran</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'kas.php') ? 'active' : '' ?>" href="<?= base_url('anggota/kas.php') ?>"><i class="bi bi-journal-text"></i> Laporan Kas</a>
                <a class="nav-link <?= str_contains($_SERVER['SCRIPT_NAME'], 'pengumuman.php') ? 'active' : '' ?>" href="<?= base_url('anggota/pengumuman.php') ?>"><i class="bi bi-megaphone"></i> Pengumuman</a>
            <?php endif; ?>
            <a class="nav-link text-danger mt-4" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content Area -->
    <div class="main-content w-100">
        <!-- Top Bar Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 shadow-sm">
            <div>
                <h5 class="mb-0 text-dark fw-bold">Selamat datang, <?= e($user_display_name) ?> 👋</h5>
                <small class="text-muted"><?= format_tanggal(date('Y-m-d')) ?></small>
            </div>
            <div class="dropdown">
                <a class="btn btn-light rounded-circle p-2" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-fill fs-5"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><span class="dropdown-item-text text-muted small">Login sebagai <strong><?= e($_SESSION['username']) ?></strong></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        