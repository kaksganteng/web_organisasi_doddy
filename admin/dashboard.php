<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
$new_credentials = $_SESSION['new_credentials'] ?? null;

unset($_SESSION['success_msg'], $_SESSION['error_msg'], $_SESSION['new_credentials']);

// ------------------------------------------------------------------
// 1. PROCESS POST ACTIONS (TAMBAH, EDIT, TOGGLE STATUS, HAPUS)
// ------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: anggota.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --------------------------------------------------------------
    // ACTION: TAMBAH ANGGOTA BARU
    // --------------------------------------------------------------
    if ($action === 'add') {
        $nama_lengkap      = trim($_POST['nama_lengkap'] ?? '');
        $tempat_lahir      = trim($_POST['tempat_lahir'] ?? '');
        $tanggal_lahir     = trim($_POST['tanggal_lahir'] ?? '');
        $jenis_kelamin     = trim($_POST['jenis_kelamin'] ?? 'Laki-laki');
        $golongan_darah    = trim($_POST['golongan_darah'] ?? 'A');
        
        // Rayon / Wilayah otomatis dikonversi menjadi HURUF BESAR
        $rayon             = strtoupper(trim($_POST['rayon'] ?? ''));

        $no_hp             = trim($_POST['no_hp'] ?? '');
        $alamat            = trim($_POST['alamat'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $tanggal_bergabung = trim($_POST['tanggal_bergabung'] ?? date('Y-m-d'));
        $status_aktif      = isset($_POST['status_aktif']) ? 1 : 0;

        // Validasi input wajib (termasuk rayon)
        if (empty($nama_lengkap) || empty($email) || empty($no_hp) || empty($tanggal_lahir) || empty($rayon)) {
            $_SESSION['error_msg'] = "Semua kolom bertanda bintang (*) termasuk wilayah/rayon wajib diisi!";
            header("Location: anggota.php");
            exit;
        }

        // Cek Keunikan Email
        $stmt_check = $db->prepare("SELECT id FROM anggota WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            $_SESSION['error_msg'] = "Email sudah terdaftar! Gunakan email lain.";
            header("Location: anggota.php");
            exit;
        }

        // 1. Buat Username otomatis dari prefix email
        $email_parts   = explode('@', $email);
        $base_username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($email_parts[0]));
        $username      = $base_username;
        $counter       = 1;

        // Cek keunikan username
        while (true) {
            $stmt_u = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_u->execute([$username]);
            if (!$stmt_u->fetch()) {
                break;
            }
            $username = $base_username . $counter;
            $counter++;
        }

        // 2. Buat Password Random 6 Karakter & Hash
        $plain_password  = generate_random_password(6);
        $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

        try {
            $db->beginTransaction();

            // Insert ke tabel anggota (termasuk kolom rayon)
            $stmt_add_a = $db->prepare("
                INSERT INTO anggota (nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, golongan_darah, rayon, no_hp, alamat, email, tanggal_bergabung, status_aktif) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_add_a->execute([$nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $golongan_darah, $rayon, $no_hp, $alamat, $email, $tanggal_bergabung, $status_aktif]);
            $id_anggota_baru = $db->lastInsertId();

            // Insert ke tabel users
            $stmt_add_u = $db->prepare("
                INSERT INTO users (username, password, role, id_anggota) 
                VALUES (?, ?, 'anggota', ?)
            ");
            $stmt_add_u->execute([$username, $hashed_password, $id_anggota_baru]);

            // Audit Log
            $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], "Menambahkan anggota baru: $nama_lengkap (Rayon: $rayon)", $_SERVER['REMOTE_ADDR']]);

            $db->commit();

            $_SESSION['success_msg'] = "Anggota <strong>" . e($nama_lengkap) . "</strong> berhasil ditambahkan!";
            $_SESSION['new_credentials'] = [
                'nama'     => $nama_lengkap,
                'username' => $username,
                'password' => $plain_password
            ];

        } catch (Exception $ex) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Gagal menambah anggota: " . $ex->getMessage();
        }

        header("Location: anggota.php");
        exit;
    }

    // --------------------------------------------------------------
    // ACTION: EDIT ANGGOTA
    // --------------------------------------------------------------
    if ($action === 'edit') {
        $id                = (int)($_POST['id'] ?? 0);
        $nama_lengkap      = trim($_POST['nama_lengkap'] ?? '');
        $tempat_lahir      = trim($_POST['tempat_lahir'] ?? '');
        $tanggal_lahir     = trim($_POST['tanggal_lahir'] ?? '');
        $jenis_kelamin     = trim($_POST['jenis_kelamin'] ?? 'Laki-laki');
        $golongan_darah    = trim($_POST['golongan_darah'] ?? 'A');
        
        // Rayon / Wilayah otomatis dikonversi menjadi HURUF BESAR
        $rayon             = strtoupper(trim($_POST['rayon'] ?? ''));

        $no_hp             = trim($_POST['no_hp'] ?? '');
        $alamat            = trim($_POST['alamat'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $tanggal_bergabung = trim($_POST['tanggal_bergabung'] ?? date('Y-m-d'));
        $status_aktif      = isset($_POST['status_aktif']) ? 1 : 0;

        // Cek Keunikan Email
        $stmt_check = $db->prepare("SELECT id FROM anggota WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $id]);
        if ($stmt_check->fetch()) {
            $_SESSION['error_msg'] = "Email sudah digunakan oleh anggota lain!";
            header("Location: anggota.php");
            exit;
        }

        try {
            $stmt_edit = $db->prepare("
                UPDATE anggota SET 
                    nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?, golongan_darah = ?, rayon = ?,
                    no_hp = ?, alamat = ?, email = ?, tanggal_bergabung = ?, status_aktif = ?
                WHERE id = ?
            ");
            $stmt_edit->execute([$nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $golongan_darah, $rayon, $no_hp, $alamat, $email, $tanggal_bergabung, $status_aktif, $id]);

            // Audit Log
            $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], "Mengubah data anggota ID: $id", $_SERVER['REMOTE_ADDR']]);

            $_SESSION['success_msg'] = "Data anggota berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $ex->getMessage();
        }

        header("Location: anggota.php");
        exit;
    }

    // --------------------------------------------------------------
    // ACTION: TOGGLE STATUS AKTIF / NONAKTIF
    // --------------------------------------------------------------
    if ($action === 'toggle_status') {
        $id              = (int)($_POST['id'] ?? 0);
        $status_sekarang = (int)($_POST['status_sekarang'] ?? 0);
        $status_baru     = $status_sekarang === 1 ? 0 : 1;

        $stmt_toggle = $db->prepare("UPDATE anggota SET status_aktif = ? WHERE id = ?");
        $stmt_toggle->execute([$status_baru, $id]);

        $text_status = $status_baru === 1 ? 'Diaktifkan' : 'Dinonaktifkan';
        $_SESSION['success_msg'] = "Status anggota berhasil $text_status!";
        header("Location: anggota.php");
        exit;
    }

    // --------------------------------------------------------------
    // ACTION: HAPUS ANGGOTA
    // --------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        try {
            $db->beginTransaction();

            // Hapus User Account Terkait
            $stmt_del_u = $db->prepare("DELETE FROM users WHERE id_anggota = ?");
            $stmt_del_u->execute([$id]);

            // Hapus Anggota
            $stmt_del_a = $db->prepare("DELETE FROM anggota WHERE id = ?");
            $stmt_del_a->execute([$id]);

            $db->commit();
            $_SESSION['success_msg'] = "Data anggota berhasil dihapus secara permanen!";
        } catch (Exception $ex) {
            $db->rollBack();
            $_SESSION['error_msg'] = "Gagal menghapus data anggota: " . $ex->getMessage();
        }

        header("Location: anggota.php");
        exit;
    }
}

// ------------------------------------------------------------------
// 2. FETCH DATA ANGGOTA, PENCARIAN & FILTER RAYON
// ------------------------------------------------------------------
$search        = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$filter_rayon  = trim($_GET['rayon'] ?? 'all');

// Ambil daftar rayon unik untuk opsi filter dropdown & datalist form
$list_rayon_db = $db->query("SELECT DISTINCT rayon FROM anggota WHERE rayon IS NOT NULL AND rayon != '' ORDER BY rayon ASC")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
    SELECT a.*, u.username 
    FROM anggota a 
    LEFT JOIN users u ON a.id = u.id_anggota 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (a.nama_lengkap LIKE ? OR a.email LIKE ? OR a.no_hp LIKE ? OR u.username LIKE ? OR a.rayon LIKE ?)";
    $term   = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($filter_status !== 'all') {
    $sql .= " AND a.status_aktif = ?";
    $params[] = (int)$filter_status;
}

if ($filter_rayon !== 'all') {
    $sql .= " AND a.rayon = ?";
    $params[] = $filter_rayon;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$list_anggota = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- DATALIST GLOBAL UNTUK PILIHAN RAYON YANG SUDAH ADA -->
<datalist id="existingRayons">
    <?php foreach ($list_rayon_db as $r_item): ?>
        <option value="<?= e($r_item) ?>">
    <?php endforeach; ?>
</datalist>

<!-- HEADER PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Manajemen Anggota</h4>
        <p class="text-muted small mb-0">Kelola data pendaftaran, hak akses, dan status keaktifan anggota organisasi.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAnggota">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Anggota
    </button>
</div>

<!-- ALERT MESSAGES -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- MODAL KREDENSIAL ANGGOTA BARU -->
<?php if ($new_credentials): ?>
    <div class="modal fade" id="modalKredensial" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2"></i>Kredensial Akun Anggota Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3">Akun untuk <strong><?= e($new_credentials['nama']) ?></strong> telah berhasil dibuat. Harap catat atau kirimkan kredensial di bawah ini kepada anggota:</p>
                    <div class="bg-light p-3 rounded border">
                        <div class="mb-2">
                            <small class="text-muted d-block fw-semibold">Username Login:</small>
                            <span class="fs-5 fw-bold text-primary font-monospace"><?= e($new_credentials['username']) ?></span>
                        </div>
                        <div>
                            <small class="text-muted d-block fw-semibold">Password Temporary (6 Karakter):</small>
                            <span class="fs-5 fw-bold text-danger font-monospace"><?= e($new_credentials['password']) ?></span>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-3"><i class="bi bi-info-circle me-1"></i>Anggota dapat mengganti password ini setelah melakukan login pertama kali.</small>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti & Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var myModal = new bootstrap.Modal(document.getElementById('modalKredensial'));
            myModal.show();
        });
    </script>
<?php endif; ?>

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2">
        <div class="col-12 col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Cari nama, email, no HP, rayon..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select name="rayon" class="form-select">
                <option value="all" <?= $filter_rayon === 'all' ? 'selected' : '' ?>>-- Semua Rayon --</option>
                <?php foreach ($list_rayon_db as $r_opt): ?>
                    <option value="<?= e($r_opt) ?>" <?= $filter_rayon === $r_opt ? 'selected' : '' ?>><?= e($r_opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>-- Status --</option>
                <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
            <?php if (!empty($search) || $filter_status !== 'all' || $filter_rayon !== 'all'): ?>
                <a href="anggota.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DATA TABLE ANGGOTA -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Nama & Username</th>
                    <th>Rayon / Wilayah</th>
                    <th>Kontak & Email</th>
                    <th>TTL, JK & Gol. Darah</th>
                    <th>Tgl Bergabung</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_anggota)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Data anggota tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($list_anggota as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($row['nama_lengkap']) ?></div>
                                <small class="text-primary font-monospace"><i class="bi bi-person me-1"></i><?= e($row['username'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                    <i class="bi bi-geo-alt-fill me-1"></i><?= e($row['rayon'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <div><i class="bi bi-envelope me-1 text-muted"></i><?= e($row['email']) ?></div>
                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($row['no_hp']) ?></small>
                            </td>
                            <td>
                                <small class="d-block"><?= e($row['tempat_lahir']) ?>, <?= date('d M Y', strtotime($row['tanggal_lahir'])) ?></small>
                                <div class="mt-1 d-flex gap-1">
                                    <span class="badge bg-outline-dark border text-dark">JK: <strong><?= e($row['jenis_kelamin'] ?? '-') ?></strong></span>
                                    <span class="badge bg-outline-dark border text-dark">Gol: <strong><?= e($row['golongan_darah']) ?></strong></span>
                                </div>
                            </td>
                            <td><small><?= format_tanggal($row['tanggal_bergabung']) ?></small></td>
                            <td>
                                <?php if ($row['status_aktif'] == 1): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Button Edit Modal -->
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id'] ?>" title="Edit Data">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Button Toggle Status -->
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="status_sekarang" value="<?= $row['status_aktif'] ?>">
                                        <button type="submit" class="btn btn-outline-<?= $row['status_aktif'] == 1 ? 'warning' : 'success' ?>" title="<?= $row['status_aktif'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                    </form>

                                    <!-- Button Delete -->
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data anggota ini beserta akun login-nya?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus Permanen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- MODAL EDIT ANGGOTA -->
                        <div class="modal fade" id="modalEdit<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Data Anggota</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Nama Lengkap *</label>
                                                    <input type="text" name="nama_lengkap" class="form-control" value="<?= e($row['nama_lengkap']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Email Gmail *</label>
                                                    <input type="email" name="email" class="form-control" value="<?= e($row['email']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Tempat Lahir</label>
                                                    <input type="text" name="tempat_lahir" class="form-control" value="<?= e($row['tempat_lahir']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Tanggal Lahir *</label>
                                                    <input type="date" name="tanggal_lahir" class="form-control" value="<?= $row['tanggal_lahir'] ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Jenis Kelamin *</label>
                                                    <select name="jenis_kelamin" class="form-select" required>
                                                        <option value="Laki-laki" <?= ($row['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                                        <option value="Perempuan" <?= ($row['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Golongan Darah</label>
                                                    <select name="golongan_darah" class="form-select">
                                                        <?php foreach (['A', 'B', 'AB', 'O'] as $gol): ?>
                                                            <option value="<?= $gol ?>" <?= $row['golongan_darah'] === $gol ? 'selected' : '' ?>><?= $gol ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Rayon / Wilayah *</label>
                                                    <input type="text" name="rayon" class="form-control" list="existingRayons" value="<?= e($row['rayon']) ?>" placeholder="Pilih atau ketik rayon..." required style="text-transform: uppercase;">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Nomor HP / WhatsApp *</label>
                                                    <input type="text" name="no_hp" class="form-control" value="<?= e($row['no_hp']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Tanggal Bergabung</label>
                                                    <input type="date" name="tanggal_bergabung" class="form-control" value="<?= $row['tanggal_bergabung'] ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small fw-semibold">Alamat Lengkap</label>
                                                    <textarea name="alamat" class="form-control" rows="2"><?= e($row['alamat']) ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="status_aktif" id="editStatus<?= $row['id'] ?>" <?= $row['status_aktif'] == 1 ? 'checked' : '' ?>>
                                                        <label class="form-check-label fw-semibold" for="editStatus<?= $row['id'] ?>">Status Keaktifan Anggota</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH ANGGOTA BARU -->
<div class="modal fade" id="modalTambahAnggota" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Form Tambah Anggota Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Lengkap *</label>
                            <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Budi Santoso" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Gmail *</label>
                            <input type="email" name="email" class="form-control" placeholder="budi@gmail.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tempat Lahir *</label>
                            <input type="text" name="tempat_lahir" class="form-control" placeholder="Jakarta" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Lahir *</label>
                            <input type="date" name="tanggal_lahir" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" class="form-select" required>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Golongan Darah *</label>
                            <select name="golongan_darah" class="form-select">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="AB">AB</option>
                                <option value="O">O</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Rayon / Wilayah *</label>
                            <input type="text" name="rayon" class="form-control" list="existingRayons" placeholder="Pilih atau ketik rayon..." required style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor HP / WA *</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08123456789" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Bergabung *</label>
                            <input type="date" name="tanggal_bergabung" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Alamat Lengkap *</label>
                            <textarea name="alamat" class="form-control" rows="2" placeholder="Jl. Merdeka No. 123, Jakarta" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status_aktif" id="addStatus" checked>
                                <label class="form-check-label fw-semibold" for="addStatus">Langsung Aktifkan Status Anggota</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Tambah Anggota</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>