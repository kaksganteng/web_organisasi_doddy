<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

$user_id = $_SESSION['user_id'] ?? 1;

// ==================================================================
// 1. PROSES POST (UPDATE USERNAME & GANTI PASSWORD)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: profile.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. GANTI USERNAME ---
    if ($action === 'update_username') {
        $username = trim($_POST['username'] ?? '');

        if (empty($username)) {
            $_SESSION['error_msg'] = "Username tidak boleh kosong!";
            header("Location: profile.php");
            exit;
        }

        try {
            // Cek duplikasi username di tabel 'users'
            $stmt_chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_chk->execute([$username, $user_id]);
            if ($stmt_chk->fetch()) {
                $_SESSION['error_msg'] = "Username sudah digunakan oleh akun lain!";
                header("Location: profile.php");
                exit;
            }

            // Update Username & updated_at di tabel 'users'
            $stmt_up = $db->prepare("UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?");
            $stmt_up->execute([$username, $user_id]);

            // Update session
            if (isset($_SESSION['username'])) {
                $_SESSION['username'] = $username;
            }

            $_SESSION['success_msg'] = "Username berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memperbarui username: " . $ex->getMessage();
        }

        header("Location: profile.php");
        exit;
    }

    // --- B. GANTI KATA SANDI / PASSWORD ---
    if ($action === 'change_password') {
        $pass_lama = $_POST['pass_lama'] ?? '';
        $pass_baru = $_POST['pass_baru'] ?? '';
        $pass_konf = $_POST['pass_konf'] ?? '';

        if (empty($pass_lama) || empty($pass_baru) || empty($pass_konf)) {
            $_SESSION['error_msg'] = "Semua kolom kata sandi wajib diisi!";
            header("Location: profile.php");
            exit;
        }

        if ($pass_baru !== $pass_konf) {
            $_SESSION['error_msg'] = "Konfirmasi kata sandi baru tidak cocok!";
            header("Location: profile.php");
            exit;
        }

        if (strlen($pass_baru) < 6) {
            $_SESSION['error_msg'] = "Kata sandi baru minimal 6 karakter!";
            header("Location: profile.php");
            exit;
        }

        try {
            // Verifikasi password lama dari tabel 'users'
            $stmt_pass = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pass->execute([$user_id]);
            $current_hash = $stmt_pass->fetchColumn();

            // Mendukung plain text (sesuai contoh DB kamu) maupun password_verify/md5
            $is_valid = password_verify($pass_lama, $current_hash) 
                        || md5($pass_lama) === $current_hash 
                        || $pass_lama === $current_hash;

            if (!$is_valid) {
                $_SESSION['error_msg'] = "Password lama Anda salah!";
                header("Location: profile.php");
                exit;
            }

            // Jika sistem login kamu masih teks biasa (plain text), ganti $new_hash = $pass_baru;
            // Rekomendasi: Gunakan password_hash() demi keamanan
            $new_hash = password_hash($pass_baru, PASSWORD_BCRYPT);

            $stmt_up_pass = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt_up_pass->execute([$new_hash, $user_id]);

            $_SESSION['success_msg'] = "Password berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal mengubah password: " . $ex->getMessage();
        }

        header("Location: profile.php");
        exit;
    }
}

// ==================================================================
// 2. FETCH DATA USER DARI TABEL 'users'
// ==================================================================
$stmt = $db->prepare("SELECT id, username, role, created_at, updated_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$current_username = $user['username'] ?? ($_SESSION['username'] ?? 'admin');

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-person-gear text-primary me-2"></i>Pengaturan Akun Admin</h4>
        <p class="text-muted small mb-0">Kelola kredensial login Anda (Username & Password).</p>
    </div>
</div>

<!-- NOTIFIKASI ALERTS -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" data-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" data-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- CARD 1: GANTI USERNAME -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Ganti Username</h6>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="update_username">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username Saat Ini</label>
                        <input type="text" class="form-control bg-light" value="<?= e($current_username) ?>" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username Baru *</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username baru..." value="<?= e($current_username) ?>" required>
                        <small class="text-muted">Username digunakan untuk login ke dalam sistem.</small>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary fw-semibold px-4">
                            <i class="bi bi-save me-1"></i> Simpan Username
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CARD 2: GANTI PASSWORD -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-key-fill text-warning me-2"></i>Ganti Password</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Saat Ini *</label>
                        <input type="password" name="pass_lama" class="form-control" placeholder="Masukkan password saat ini..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Baru *</label>
                        <input type="password" name="pass_baru" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Konfirmasi Password Baru *</label>
                        <input type="password" name="pass_konf" class="form-control" placeholder="Ulangi password baru..." required minlength="6">
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-warning fw-semibold px-4">
                            <i class="bi bi-shield-lock me-1"></i> Perbarui Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>