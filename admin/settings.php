<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Helper function untuk mengambil nilai setting dari tabel 'settings'
function get_setting_val($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetchColumn();
        return $res !== false ? $res : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper function untuk simpan/update nilai setting
function set_setting_val($db, $key, $value) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

// ------------------------------------------------------------------
// PROCESS POST ACTIONS (SIMPAN PENGATURAN)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid!";
        header("Location: settings.php");
        exit;
    }

    try {
        $settings_map = [
            'nama_organisasi'  => trim($_POST['nama_organisasi'] ?? ''),
            'sub_nama'         => trim($_POST['sub_nama'] ?? ''),
            'alamat_lengkap'   => trim($_POST['alamat_lengkap'] ?? ''),
            'telepon'          => trim($_POST['telepon'] ?? ''),
            'email_organisasi' => trim($_POST['email_organisasi'] ?? ''),
            'nominal_iuran'    => (float)($_POST['nominal_iuran'] ?? 0),
            'ketua_nama'       => trim($_POST['ketua_nama'] ?? ''),
            'ketua_nip'        => trim($_POST['ketua_nip'] ?? ''),
            'bendahara_nama'   => trim($_POST['bendahara_nama'] ?? ''),
            'bendahara_nip'    => trim($_POST['bendahara_nip'] ?? '')
        ];

        foreach ($settings_map as $key => $val) {
            set_setting_val($db, $key, $val);
        }

        // Audit Log
        $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], "Memperbarui pengaturan sistem organisasi", $_SERVER['REMOTE_ADDR']]);

        $_SESSION['success_msg'] = "Pengaturan sistem berhasil disimpan!";
    } catch (Exception $ex) {
        $_SESSION['error_msg'] = "Gagal menyimpan pengaturan: " . $ex->getMessage();
    }

    header("Location: settings.php");
    exit;
}

// Ambil nilai pengaturan dari DB
$val_nama_org   = get_setting_val($db, 'nama_organisasi', 'Sistem Keuangan Organisasi');
$val_sub_nama   = get_setting_val($db, 'sub_nama', 'Pengurus Pusat Pemberdayaan Masyarakat');
$val_alamat     = get_setting_val($db, 'alamat_lengkap', 'Jl. Pemuda No. 123, Surabaya');
$val_telepon    = get_setting_val($db, 'telepon', '(031) 555-0192');
$val_email      = get_setting_val($db, 'email_organisasi', 'info@organisasi.or.id');
$val_iuran      = get_setting_val($db, 'nominal_iuran', '100000');
$val_ketua_n    = get_setting_val($db, 'ketua_nama', 'Dr. H. Hendra Wijaya, M.Si.');
$val_ketua_nip  = get_setting_val($db, 'ketua_nip', 'ORG-001');
$val_bend_n     = get_setting_val($db, 'bendahara_nama', 'Siti Nurhaliza, S.E.');
$val_bend_nip   = get_setting_val($db, 'bendahara_nip', 'ORG-008');

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Pengaturan Sistem Organisasi</h4>
        <p class="text-muted small mb-0">Kelola identitas organisasi, tarif iuran default, dan pejabat penandatangan laporan.</p>
    </div>
</div>

<!-- ALERTS -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="row g-4">
        <!-- SEKSI 1: IDENTITAS ORGANISASI -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-building me-2"></i>Identitas Organisasi & Kop Surat</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Utama Organisasi *</label>
                        <input type="text" name="nama_organisasi" class="form-control" value="<?= e($val_nama_org) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sub-Judul / Nama Wilayah / Divisi</label>
                        <input type="text" name="sub_nama" class="form-control" value="<?= e($val_sub_nama) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat Lengkap</label>
                        <textarea name="alamat_lengkap" class="form-control" rows="2"><?= e($val_alamat) ?></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">Nomor Telepon / Hotline</label>
                            <input type="text" name="telepon" class="form-control" value="<?= e($val_telepon) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">Email Resmi Organisasi</label>
                            <input type="email" name="email_organisasi" class="form-control" value="<?= e($val_email) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEKSI 2: KEANUGERAHAN & KEUANGAN -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-wallet2 me-2"></i>Konfigurasi Keuangan & Signatur Laporan</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Nominal Standard Iuran Bulanan (Rp) *</label>
                        <input type="number" name="nominal_iuran" class="form-control fw-bold text-success" value="<?= e($val_iuran) ?>" min="0" step="5000" required>
                        <small class="text-muted">Nominal default saat menggenerate tagihan iuran bulanan baru.</small>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary mb-3 small text-uppercase"><i class="bi bi-pen me-1"></i>Penandatangan Laporan PDF</h6>

                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Nama Ketua Umum</label>
                            <input type="text" name="ketua_nama" class="form-control" value="<?= e($val_ketua_n) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">NIP / ID Ketua</label>
                            <input type="text" name="ketua_nip" class="form-control" value="<?= e($val_ketua_nip) ?>">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Nama Bendahara Umum</label>
                            <input type="text" name="bendahara_nama" class="form-control" value="<?= e($val_bend_n) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">NIP / ID Bendahara</label>
                            <input type="text" name="bendahara_nip" class="form-control" value="<?= e($val_bend_nip) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
            <i class="bi bi-save me-2"></i>Simpan Seluruh Pengaturan
        </button>
    </div>
</form>

<?php include_once __DIR__ . '/../includes/header.php'; ?>