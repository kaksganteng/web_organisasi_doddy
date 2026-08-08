<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['anggota']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

$user_id = $_SESSION['user_id'] ?? 0;

// ==================================================================
// 1. PROSES POST (HANYA GANTI PASSWORD)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: profile.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $pass_lama = $_POST['pass_lama'] ?? '';
        $pass_baru = $_POST['pass_baru'] ?? '';
        $pass_konf = $_POST['pass_konf'] ?? '';

        if (empty($pass_lama) || empty($pass_baru) || empty($pass_konf)) {
            $_SESSION['error_msg'] = "Semua kolom password wajib diisi!";
            header("Location: profile.php");
            exit;
        }

        if ($pass_baru !== $pass_konf) {
            $_SESSION['error_msg'] = "Konfirmasi password baru tidak cocok!";
            header("Location: profile.php");
            exit;
        }

        if (strlen($pass_baru) < 6) {
            $_SESSION['error_msg'] = "Password baru minimal 6 karakter!";
            header("Location: profile.php");
            exit;
        }

        try {
            // Verifikasi password lama dari tabel users
            $stmt_pass = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pass->execute([$user_id]);
            $current_hash = $stmt_pass->fetchColumn();

            // Mendukung komparasi password (plain-text, md5, atau password_verify)
            $is_valid = password_verify($pass_lama, $current_hash) 
                        || md5($pass_lama) === $current_hash 
                        || $pass_lama === $current_hash;

            if (!$is_valid) {
                $_SESSION['error_msg'] = "Password saat ini salah!";
                header("Location: profile.php");
                exit;
            }

            // Hash password baru demi keamanan
            $new_hash = password_hash($pass_baru, PASSWORD_BCRYPT);

            $stmt_up_pass = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt_up_pass->execute([$new_hash, $user_id]);

            $_SESSION['success_msg'] = "Password Anda berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal mengubah password: " . $ex->getMessage();
        }

        header("Location: profile.php");
        exit;
    }
}

// ==================================================================
// 2. FETCH DATA LENGKAP ANGGOTA (JOIN TABEL users DENGAN anggota)
// ==================================================================
$stmt = $db->prepare("
    SELECT 
        u.id AS user_id, 
        u.username, 
        u.role, 
        u.created_at AS akun_dibuat,
        u.updated_at AS akun_diperbarui,
        a.* 
    FROM users u
    LEFT JOIN anggota a ON u.id_anggota = a.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-person-circle text-primary me-2"></i>Profil & Data Pribadi Saya</h4>
        <p class="text-muted small mb-0">Lihat informasi data diri Anda dan kelola kata sandi akun.</p>
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
    <!-- KOLOM KIRI: DATA LENGKAP ANGGOTA -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-card-heading text-primary me-2"></i>Informasi Data Anggota</h6>
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 rounded-pill small">
                    <i class="bi bi-shield-check me-1"></i> Role: <?= ucfirst(e($profile['role'] ?? 'anggota')) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Username / ID Login</label>
                        <span class="fw-bold text-dark fs-6">@<?= e($profile['username'] ?? '-') ?></span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Nama Lengkap</label>
                        <span class="fw-bold text-dark fs-6"><?= e($profile['nama'] ?? $profile['nama_lengkap'] ?? '-') ?></span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Rayon / Wilayah</label>
                        <span class="fw-semibold text-dark">
                            <?php if (!empty($profile['rayon'])): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                    <i class="bi bi-geo-alt-fill me-1"></i><?= e($profile['rayon']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Jenis Kelamin</label>
                        <span class="fw-semibold text-dark">
                            <?php 
                            $jk = strtolower($profile['jenis_kelamin'] ?? $profile['jk'] ?? '');
                            if ($jk === 'l' || $jk === 'laki-laki') echo 'Laki-Laki';
                            elseif ($jk === 'p' || $jk === 'perempuan') echo 'Perempuan';
                            else echo '-';
                            ?>
                        </span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Nomor Telepon / WhatsApp</label>
                        <span class="fw-semibold text-dark"><?= e($profile['no_hp'] ?? $profile['telepon'] ?? '-') ?></span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Email</label>
                        <span class="fw-semibold text-dark"><?= e($profile['email'] ?? '-') ?></span>
                    </div>

                    <div class="col-12">
                        <label class="text-muted small d-block">Alamat Lengkap</label>
                        <span class="fw-semibold text-dark"><?= e($profile['alamat'] ?? '-') ?></span>
                    </div>

                    <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Tanggal Terdaftar Akun</label>
                        <span class="small text-secondary">
                            <i class="bi bi-calendar-check me-1"></i>
                            <?= !empty($profile['akun_dibuat']) ? date('d M Y, H:i', strtotime($profile['akun_dibuat'])) : '-' ?>
                        </span>
                    </div>

                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Terakhir Diperbarui</label>
                        <span class="small text-secondary">
                            <i class="bi bi-clock-history me-1"></i>
                            <?= !empty($profile['akun_diperbarui']) ? date('d M Y, H:i', strtotime($profile['akun_diperbarui'])) : '-' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: FORM GANTI PASSWORD -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Ubah Password Akun</h6>
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
                        <button type="submit" class="btn btn-warning fw-semibold w-100">
                            <i class="bi bi-key me-1"></i> Perbarui Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>