<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ------------------------------------------------------------------
// 1. PROCESS POST ACTIONS (BERSIHKAN LOGS)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid!";
        header("Location: logs.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'clear_all') {
        try {
            $db->exec("TRUNCATE TABLE activity_log");
            
            // Re-insert log untuk tindakan pembersihan log
            $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], "Membersihkan seluruh riwayat Activity Log", $_SERVER['REMOTE_ADDR']]);

            $_SESSION['success_msg'] = "Seluruh catatan riwayat aktivitas berhasil dibersihkan!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal membersihkan log: " . $ex->getMessage();
        }

        header("Location: logs.php");
        exit;
    }
}

// ------------------------------------------------------------------
// 2. FETCH DAFTAR USER UNTUK FILTER
// ------------------------------------------------------------------
$users_list = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

// ------------------------------------------------------------------
// 3. FETCH LOGS DENGAN FILTER PENCARIAN
// ------------------------------------------------------------------
$filter_user   = (int)($_GET['user_id'] ?? 0);
$filter_tgl_m  = $_GET['tgl_mulai'] ?? '';
$filter_tgl_s  = $_GET['tgl_selesai'] ?? '';
$search        = trim($_GET['search'] ?? '');

$sql = "
    SELECT l.*, u.username, u.role 
    FROM activity_log l
    LEFT JOIN users u ON l.id_user = u.id
    WHERE 1=1
";
$params = [];

if ($filter_user > 0) {
    $sql .= " AND l.id_user = ?";
    $params[] = $filter_user;
}

if (!empty($filter_tgl_m)) {
    $sql .= " AND DATE(l.created_at) >= ?";
    $params[] = $filter_tgl_m;
}

if (!empty($filter_tgl_s)) {
    $sql .= " AND DATE(l.created_at) <= ?";
    $params[] = $filter_tgl_s;
}

if (!empty($search)) {
    $sql .= " AND l.aktivitas LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY l.id DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-primary me-2"></i>Audit Activity Log</h4>
        <p class="text-muted small mb-0">Catatan riwayat seluruh aktivitas operasional dan interaksi pengguna pada sistem.</p>
    </div>
    <div>
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalClearLog">
            <i class="bi bi-trash3 me-1"></i> Bersihkan Log
        </button>
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

<!-- FILTER FORM -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Pengguna / User</label>
            <select name="user_id" class="form-select">
                <option value="0">Semua Pengguna</option>
                <?php foreach ($users_list as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_user === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= e($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($filter_tgl_m) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($filter_tgl_s) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Cari Aktivitas</label>
            <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
            <?php if ($filter_user > 0 || !empty($filter_tgl_m) || !empty($filter_tgl_s) || !empty($search)): ?>
                <a href="logs.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DATA LOGS TABLE -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-list-stars me-2"></i>Menampilkan 200 Catatan Terakhir</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 170px;">Waktu & Tanggal</th>
                    <th style="width: 150px;">Pengguna</th>
                    <th>Aktivitas / Tindakan</th>
                    <th style="width: 130px;" class="text-end">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Belum ada catatan aktivitas yang sesuai.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($logs as $row): ?>
                        <tr>
                            <td class="text-muted small"><?= $no++ ?></td>
                            <td>
                                <span class="d-block fw-semibold text-dark small"><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-primary d-block"><?= e($row['username'] ?? 'Sistem') ?></span>
                                <small class="badge bg-secondary-subtle text-secondary border border-secondary" style="font-size: 7.5pt;">
                                    <?= strtoupper($row['role'] ?? 'SYSTEM') ?>
                                </small>
                            </td>
                            <td class="text-dark font-monospace small">
                                <?= e($row['aktivitas']) ?>
                            </td>
                            <td class="text-end text-muted font-monospace small">
                                <?= e($row['ip_address'] ?: '127.0.0.1') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CONFIRM CLEAR LOG -->
<div class="modal fade" id="modalClearLog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="clear_all">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Bersihkan Log</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="mb-2 fs-5 text-dark fw-bold">Apakah Anda yakin ingin menghapus seluruh log?</p>
                    <p class="text-muted small mb-0">Tindakan ini akan mengosongkan seluruh riwayat aktivitas dari database dan tidak dapat dikembalikan.</p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i> Ya, Bersihkan Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/header.php'; ?>