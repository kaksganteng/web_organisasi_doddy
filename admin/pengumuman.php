<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Folder penyimpanan file lampiran
$upload_dir = __DIR__ . '/../uploads/pengumuman/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ==================================================================
// 1. PROSES POST (TAMBAH, EDIT, HAPUS)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: pengumuman.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. TAMBAH PENGUMUMAN ---
    if ($action === 'add') {
        $judul    = trim($_POST['judul'] ?? '');
        $isi      = trim($_POST['isi'] ?? '');
        $id_admin = $_SESSION['user_id'] ?? 1;
        $lampiran_nama = NULL;

        if (empty($judul) || empty($isi)) {
            $_SESSION['error_msg'] = "Judul dan Isi pengumuman wajib diisi!";
            header("Location: pengumuman.php");
            exit;
        }

        // Handle File Upload Lampiran
        if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['lampiran']['tmp_name'];
            $file_name = $_FILES['lampiran']['name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            if (in_array($file_ext, $allowed_ext)) {
                $lampiran_nama = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file_name);
                move_uploaded_file($file_tmp, $upload_dir . $lampiran_nama);
            } else {
                $_SESSION['error_msg'] = "Format file lampiran tidak didukung!";
                header("Location: pengumuman.php");
                exit;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO pengumuman (judul, isi, lampiran, id_admin, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$judul, $isi, $lampiran_nama, $id_admin]);

            $_SESSION['success_msg'] = "Pengumuman berhasil diterbitkan!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal menambah pengumuman: " . $ex->getMessage();
        }

        header("Location: pengumuman.php");
        exit;
    }

    // --- B. EDIT PENGUMUMAN ---
    if ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $isi   = trim($_POST['isi'] ?? '');

        if ($id <= 0 || empty($judul) || empty($isi)) {
            $_SESSION['error_msg'] = "Data tidak valid atau field wajib belum diisi!";
            header("Location: pengumuman.php");
            exit;
        }

        try {
            // Cek data lama
            $stmt_old = $db->prepare("SELECT lampiran FROM pengumuman WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch();

            $lampiran_nama = $old_data['lampiran'] ?? NULL;

            // Handle Update File Lampiran
            if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['lampiran']['tmp_name'];
                $file_name = $_FILES['lampiran']['name'];
                $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                if (in_array($file_ext, $allowed_ext)) {
                    // Hapus file lama jika ada
                    if (!empty($lampiran_nama) && file_exists($upload_dir . $lampiran_nama)) {
                        unlink($upload_dir . $lampiran_nama);
                    }
                    $lampiran_nama = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file_name);
                    move_uploaded_file($file_tmp, $upload_dir . $lampiran_nama);
                }
            }

            $stmt_up = $db->prepare("
                UPDATE pengumuman 
                SET judul = ?, isi = ?, lampiran = ? 
                WHERE id = ?
            ");
            $stmt_up->execute([$judul, $isi, $lampiran_nama, $id]);

            $_SESSION['success_msg'] = "Pengumuman berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memperbarui pengumuman: " . $ex->getMessage();
        }

        header("Location: pengumuman.php");
        exit;
    }

    // --- C. HAPUS PENGUMUMAN ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        try {
            // Ambil nama file lampiran untuk dihapus dari server
            $stmt_file = $db->prepare("SELECT lampiran FROM pengumuman WHERE id = ?");
            $stmt_file->execute([$id]);
            $file = $stmt_file->fetchColumn();

            if ($file && file_exists($upload_dir . $file)) {
                unlink($upload_dir . $file);
            }

            $stmt_del = $db->prepare("DELETE FROM pengumuman WHERE id = ?");
            $stmt_del->execute([$id]);

            $_SESSION['success_msg'] = "Pengumuman berhasil dihapus!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal menghapus pengumuman: " . $ex->getMessage();
        }

        header("Location: pengumuman.php");
        exit;
    }
}

// ==================================================================
// 2. FETCH DATA & SEARCH
// ==================================================================
$search = trim($_GET['q'] ?? '');

$sql = "SELECT p.* FROM pengumuman p WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.judul LIKE ? OR p.isi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$list_pengumuman = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER TITLE & TOMBOL TAMBAH -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-megaphone text-primary me-2"></i>Kelola Pengumuman</h4>
        <p class="text-muted small mb-0">Buat, perbarui, dan bagikan informasi pengumuman kepada seluruh anggota.</p>
    </div>
    <button type="button" 
            id="btnOpenTambah" 
            class="btn btn-primary shadow-sm fw-semibold" 
            data-bs-toggle="modal" 
            data-bs-target="#modalTambahPengumuman"
            data-toggle="modal" 
            data-target="#modalTambahPengumuman">
        <i class="bi bi-plus-circle me-1"></i> Buat Pengumuman Baru
    </button>
</div>

<!-- ALERTS NOTIFIKASI -->
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

<!-- SEARCH BAR -->
<div class="card border-0 shadow-sm p-3 mb-4 bg-white">
    <form method="GET" action="" class="row g-2">
        <div class="col-md-10">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" class="form-control border-start-0" placeholder="Cari judul atau isi pengumuman..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Cari</button>
        </div>
    </form>
</div>

<!-- TABEL PENGUMUMAN -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4 bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width: 50px;">#</th>
                    <th style="width: 300px;">Judul Pengumuman</th>
                    <th>Isi Pengumuman (Pratinjau)</th>
                    <th style="width: 130px;">Lampiran</th>
                    <th style="width: 160px;">Tanggal Dibuat</th>
                    <th class="text-center pe-3" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_pengumuman)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Belum ada data pengumuman yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($list_pengumuman as $row): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $no++ ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= e($row['judul']) ?></strong>
                            </td>
                            <td class="text-muted">
                                <?= e(mb_strimwidth(strip_tags($row['isi']), 0, 90, '...')) ?>
                            </td>
                            <td>
                                <?php if (!empty($row['lampiran'])): ?>
                                    <a href="../uploads/pengumuman/<?= e($row['lampiran']) ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold">
                                        <i class="bi bi-paperclip me-1"></i>Unduh
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small"><em>Tidak ada</em></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td class="text-center pe-3">
                                <div class="btn-group btn-group-sm">
                                    <!-- Detail/View -->
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $row['id'] ?>" data-toggle="modal" data-target="#modalDetail<?= $row['id'] ?>" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <!-- Edit -->
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id'] ?>" data-toggle="modal" data-target="#modalEdit<?= $row['id'] ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Delete -->
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id'] ?>" data-toggle="modal" data-target="#modalHapus<?= $row['id'] ?>" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================================================================== -->
<!-- MODAL DETAIL, EDIT & HAPUS (LOOPING DATA)                         -->
<!-- ================================================================== -->
<?php if (!empty($list_pengumuman)): ?>
    <?php foreach ($list_pengumuman as $row): ?>
        
        <!-- MODAL DETAIL -->
        <div class="modal fade" id="modalDetail<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Detail Pengumuman</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <h4 class="fw-bold text-dark mb-2"><?= e($row['judul']) ?></h4>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-calendar-event me-1"></i>Diterbitkan pada <?= date('d F Y, H:i', strtotime($row['created_at'])) ?> WIB
                        </p>
                        <hr>
                        <div class="lh-base mb-4 text-secondary" style="white-space: pre-line;">
                            <?= e($row['isi']) ?>
                        </div>

                        <?php if (!empty($row['lampiran'])): ?>
                            <div class="p-3 bg-light rounded border">
                                <span class="fw-semibold d-block mb-1 text-dark"><i class="bi bi-paperclip me-1"></i>File Lampiran:</span>
                                <a href="../uploads/pengumuman/<?= e($row['lampiran']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="bi bi-download me-1"></i> Unduh File (<?= e($row['lampiran']) ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL EDIT -->
        <div class="modal fade" id="modalEdit<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">

                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Pengumuman</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Judul Pengumuman *</label>
                                <input type="text" name="judul" class="form-control" value="<?= e($row['judul']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Isi Pengumuman *</label>
                                <textarea name="isi" class="form-control" rows="6" required><?= e($row['isi']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Ganti Lampiran (Opsional)</label>
                                <input type="file" name="lampiran" class="form-control">
                                <?php if (!empty($row['lampiran'])): ?>
                                    <small class="text-muted d-block mt-1">
                                        File saat ini: <a href="../uploads/pengumuman/<?= e($row['lampiran']) ?>" target="_blank"><?= e($row['lampiran']) ?></a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-check-circle me-1"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL HAPUS -->
        <div class="modal fade" id="modalHapus<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">

                        <div class="modal-header bg-danger text-white">
                            <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Konfirmasi Hapus</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <i class="bi bi-exclamation-triangle text-warning display-4 d-block mb-2"></i>
                            <p class="mb-1">Apakah Anda yakin ingin menghapus pengumuman ini?</p>
                            <strong class="text-dark d-block">"<?= e($row['judul']) ?>"</strong>
                            <small class="text-muted d-block mt-2">Tindakan ini tidak dapat dibatalkan.</small>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger btn-sm fw-semibold">Ya, Hapus Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
<?php endif; ?>

<!-- ================================================================== -->
<!-- MODAL TAMBAH PENGUMUMAN BARU                                       -->
<!-- ================================================================== -->
<div class="modal fade" id="modalTambahPengumuman" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTambahLabel"><i class="bi bi-megaphone me-2"></i>Buat Pengumuman Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- DB: judul -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Pengumuman *</label>
                        <input type="text" name="judul" class="form-control" placeholder="Masukkan judul pengumuman..." required>
                    </div>

                    <!-- DB: isi -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Isi Pengumuman *</label>
                        <textarea name="isi" class="form-control" rows="6" placeholder="Tuliskan detail pengumuman di sini..." required></textarea>
                    </div>

                    <!-- DB: lampiran -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Lampiran (Opsional)</label>
                        <input type="file" name="lampiran" class="form-control">
                        <small class="text-muted">Format yang didukung: PDF, DOC, DOCX, JPG, PNG, ZIP (Maks. 5MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-send me-1"></i> Terbitkan Pengumuman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- SCRIPT JS GARANSI MODAL BISA DI-KLIK                               -->
<!-- ================================================================== -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    var btn = document.getElementById('btnOpenTambah');
    var modalElement = document.getElementById('modalTambahPengumuman');

    if (btn && modalElement) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                modalInstance.show();
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $(modalElement).modal('show');
            } else {
                alert("File JS Bootstrap tidak terdeteksi. Pastikan footer.php memuat file Bootstrap JS!");
            }
        });
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>