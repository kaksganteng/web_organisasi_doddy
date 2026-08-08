<?php
require_once 'config/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = "Sesi keamanan berakhir, silakan coba lagi.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($username) && !empty($password)) {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // Pengecekan password secara PLAIN TEXT (tanpa hash)
            if ($user && $password === $user['password']) {
                // Set User Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['id_anggota'] = $user['id_anggota'];

                // Audit Log
                $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
                $log_stmt->execute([$user['id'], 'User login ke dalam sistem', $_SERVER['REMOTE_ADDR']]);

                // Redirect berdasarkan Role
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: anggota/dashboard.php");
                }
                exit;
            } else {
                $error = "Username atau password salah!";
            }
        } else {
            $error = "Harap isi semua kolom!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Organisasi</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            background: #ffffff;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card login-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px;">
                            <i class="bi bi-building fs-2"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Portal Organisasi</h4>
                        <p class="text-muted small">Masuk dengan kredensial akun Anda</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="text" name="username" class="form-control" id="usernameInput" placeholder="Username" required autocomplete="off">
                            <label for="usernameInput"><i class="bi bi-person me-2"></i>Username</label>
                        </div>

                        <div class="form-floating mb-4">
                            <input type="password" name="password" class="form-control" id="passwordInput" placeholder="Password" required>
                            <label for="passwordInput"><i class="bi bi-lock me-2"></i>Password</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-3 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Ke Sistem
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-muted">&copy; <?= date('Y') ?> Sistem Informasi Manajemen Organisasi</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>