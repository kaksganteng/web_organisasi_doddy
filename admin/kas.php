<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ------------------------------------------------------------------
// 1. PROCESS POST ACTIONS (TAMBAH, EDIT, HAPUS TRANSAKSI KAS)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: kas.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- TAMBAH TRANSAKSI KAS ---
    if ($action === 'add') {
        $jenis = $_POST['jenis'] ?? '';
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $nominal = (float)($_POST['nominal'] ?? 0);
        $sumber_keperluan = trim($_POST['sumber_keperluan'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (!in_array($jenis, ['pemasukan', 'pengeluaran'])) {
            $_SESSION['error_msg'] = "Jenis transaksi tidak valid!";
            header("Location: kas.php");
            exit;
        }

        if ($nominal <= 0 || empty($sumber_keperluan)) {
            $_SESSION['error_msg'] = "Nominal harus lebih besar dari 0 dan sumber/keperluan wajib diisi!";
            header("Location: kas.php");
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO kas (jenis, tanggal, nominal, sumber_keperluan, keterangan, id_admin) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$jenis, $tanggal, $nominal, $sumber_keperluan, $keterangan, $_SESSION['user_id']]);

            // Audit Log
            $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'], 
                "Menambahkan transaksi kas (" . strtoupper($jenis) . "): Rp " . number_format($nominal, 0, ',', '.') . " - $sumber_keperluan", 
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success_msg'] = "Transaksi " . ucfirst($jenis) . " berhasil dicatat!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal mencatat transaksi: " . $ex->getMessage();
        }

        header("Location: kas.php");
        exit;
    }

    // --- EDIT TRANSAKSI KAS ---
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $jenis = $_POST['jenis'] ?? '';
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $nominal = (float)($_POST['nominal'] ?? 0);
        $sumber_keperluan = trim($_POST['sumber_keperluan'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($id <= 0 || !in_array($jenis, ['pemasukan', 'pengeluaran'])) {
            $_SESSION['error_msg'] = "Data transaksi tidak valid!";
            header("Location: kas.php");
            exit;
        }

        if ($nominal <= 0 || empty($sumber_keperluan)) {
            $_SESSION['error_msg'] = "Nominal harus lebih besar dari 0 dan sumber/keperluan wajib diisi!";
            header("Location: kas.php");
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE kas SET jenis = ?, tanggal = ?, nominal = ?, sumber_keperluan = ?, keterangan = ? 
                WHERE id = ?
            ");
            $stmt->execute([$jenis, $tanggal, $nominal, $sumber_keperluan, $keterangan, $id]);

            // Audit Log
            $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'], 
                "Mengubah transaksi kas ID: $id (" . strtoupper($jenis) . "): Rp " . number_format($nominal, 0, ',', '.'), 
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success_msg'] = "Transaksi kas berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memperbarui transaksi: " . $ex->getMessage();
        }

        header("Location: kas.php");
        exit;
    }

    // --- HAPUS TRANSAKSI KAS ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        try {
            $stmt_check = $db->prepare("SELECT jenis, nominal, sumber_keperluan FROM kas WHERE id = ?");
            $stmt_check->execute([$id]);
            $trans = $stmt_check->fetch();

            if ($trans) {
                $stmt_del = $db->prepare("DELETE FROM kas WHERE id = ?");
                $stmt_del->execute([$id]);

                // Audit Log
                $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'], 
                    "Menghapus transaksi kas ID: $id (" . strtoupper($trans['jenis']) . "): Rp " . number_format($trans['nominal'], 0, ',', '.'), 
                    $_SERVER['REMOTE_ADDR']
                ]);

                $_SESSION['success_msg'] = "Transaksi kas berhasil dihapus!";
            } else {
                $_SESSION['error_msg'] = "Data transaksi tidak ditemukan!";
            }
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal menghapus transaksi: " . $ex->getMessage();
        }

        header("Location: kas.php");
        exit;
    }
}

// ------------------------------------------------------------------
// 2. HITUNG AKUMULASI SALDO OTOMATIS & RINGKASAN
// ------------------------------------------------------------------
$total_pemasukan_all = (float)($db->query("SELECT SUM(nominal) FROM kas WHERE jenis = 'pemasukan'")->fetchColumn() ?: 0);
$total_pengeluaran_all = (float)($db->query("SELECT SUM(nominal) FROM kas WHERE jenis = 'pengeluaran'")->fetchColumn() ?: 0);
$saldo_kas_saat_ini = $total_pemasukan_all - $total_pengeluaran_all;

// ------------------------------------------------------------------
// 3. FETCH DATA TRANSAKSI & FILTER
// ------------------------------------------------------------------
$filter_tgl_mulai = $_GET['tgl_mulai'] ?? '';
$filter_tgl_selesai = $_GET['tgl_selesai'] ?? '';
$filter_jenis = $_GET['jenis'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT k.*, u.username 
    FROM kas k 
    LEFT JOIN users u ON k.id_admin = u.id 
    WHERE 1=1
";
$params = [];

if (!empty($filter_tgl_mulai)) {
    $sql .= " AND DATE(k.tanggal) >= ?";
    $params[] = $filter_tgl_mulai;
}

if (!empty($filter_tgl_selesai)) {
    $sql .= " AND DATE(k.tanggal) <= ?";
    $params[] = $filter_tgl_selesai;
}

if ($filter_jenis !== 'all') {
    $sql .= " AND k.jenis = ?";
    $params[] = $filter_jenis;
}

if (!empty($search)) {
    $sql .= " AND (k.sumber_keperluan LIKE ? OR k.keterangan LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY k.tanggal DESC, k.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$list_kas = $stmt->fetchAll();

// Filter Subtotals
$filtered_pemasukan = 0;
$filtered_pengeluaran = 0;
foreach ($list_kas as $row) {
    if ($row['jenis'] === 'pemasukan') {
        $filtered_pemasukan += $row['nominal'];
    } else {
        $filtered_pengeluaran += $row['nominal'];
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Buku Kas Organisasi</h4>
        <p class="text-muted small mb-0">Pencatatan arus kas masuk & keluar serta pemantauan akumulasi saldo.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahKas" data-jenis="pemasukan">
            <i class="bi bi-plus-circle me-1"></i> Catat Pemasukan
        </button>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalTambahKas" data-jenis="pengeluaran">
            <i class="bi bi-dash-circle me-1"></i> Catat Pengeluaran
        </button>
    </div>
</div>

<!-- STATS SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-primary border-4 p-3 bg-white">
            <small class="text-muted text-uppercase fw-bold">Saldo Kas Saat Ini (Total)</small>
            <h3 class="fw-bold <?= $saldo_kas_saat_ini >= 0 ? 'text-primary' : 'text-danger' ?> mb-0">
                Rp <?= number_format($saldo_kas_saat_ini, 0, ',', '.') ?>
            </h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-success border-4 p-3 bg-white">
            <small class="text-muted text-uppercase fw-bold">Akumulasi Total Pemasukan</small>
            <h3 class="fw-bold text-success mb-0">Rp <?= number_format($total_pemasukan_all, 0, ',', '.') ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-danger border-4 p-3 bg-white">
            <small class="text-muted text-uppercase fw-bold">Akumulasi Total Pengeluaran</small>
            <h3 class="fw-bold text-danger mb-0">Rp <?= number_format($total_pengeluaran_all, 0, ',', '.') ?></h3>
        </div>
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

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($filter_tgl_mulai) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($filter_tgl_selesai) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Jenis Transaksi</label>
            <select name="jenis" class="form-select">
                <option value="all">Semua</option>
                <option value="pemasukan" <?= $filter_jenis === 'pemasukan' ? 'selected' : '' ?>>Pemasukan (+)</option>
                <option value="pengeluaran" <?= $filter_jenis === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran (-)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Pencarian</label>
            <input type="text" name="search" class="form-control" placeholder="Sumber / Ket..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
            <?php if (!empty($filter_tgl_mulai) || !empty($filter_tgl_selesai) || $filter_jenis !== 'all' || !empty($search)): ?>
                <a href="kas.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- SUB-TOTAL FILTER SUMMARY (JIKA FILTER AKTIF) -->
<?php if (!empty($filter_tgl_mulai) || !empty($filter_tgl_selesai) || $filter_jenis !== 'all' || !empty($search)): ?>
    <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center mb-3">
        <small class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Hasil Filter Terpilih:</small>
        <div>
            <span class="badge bg-success me-2">Masuk: Rp <?= number_format($filtered_pemasukan, 0, ',', '.') ?></span>
            <span class="badge bg-danger me-2">Keluar: Rp <?= number_format($filtered_pengeluaran, 0, ',', '.') ?></span>
            <span class="badge bg-primary">Net: Rp <?= number_format($filtered_pemasukan - $filtered_pengeluaran, 0, ',', '.') ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- TRANSACTIONS TABLE -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Sumber / Keperluan</th>
                    <th>Keterangan</th>
                    <th>Dicatat Oleh</th>
                    <th class="text-end">Nominal (Rp)</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_kas)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Belum ada transaksi kas yang tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($list_kas as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></span></td>
                            <td>
                                <?php if ($row['jenis'] === 'pemasukan'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                        <i class="bi bi-arrow-down-left me-1"></i>Masuk
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger fw-bold">
                                        <i class="bi bi-arrow-up-right me-1"></i>Keluar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-bold text-dark"><?= e($row['sumber_keperluan']) ?></span></td>
                            <td><span class="text-muted small"><?= e($row['keterangan'] ?: '-') ?></span></td>
                            <td><small class="text-muted font-monospace"><i class="bi bi-person me-1"></i><?= e($row['username'] ?? 'Sistem') ?></small></td>
                            <td class="text-end fw-bold <?= $row['jenis'] === 'pemasukan' ? 'text-success' : 'text-danger' ?>">
                                <?= $row['jenis'] === 'pemasukan' ? '+' : '-' ?> Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Edit Trigger -->
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditKas<?= $row['id'] ?>" title="Edit Transaksi">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Delete Form -->
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan transaksi ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- MODAL EDIT TRANSAKSI KAS -->
                        <div class="modal fade" id="modalEditKas<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi Kas</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Jenis Transaksi *</label>
                                                <select name="jenis" class="form-select" required>
                                                    <option value="pemasukan" <?= $row['jenis'] === 'pemasukan' ? 'selected' : '' ?>>Pemasukan (+)</option>
                                                    <option value="pengeluaran" <?= $row['jenis'] === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran (-)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Tanggal Transaksi *</label>
                                                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d', strtotime($row['tanggal'])) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nominal Transaksi (Rp) *</label>
                                                <input type="number" name="nominal" class="form-control" value="<?= $row['nominal'] ?>" min="1" step="any" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Sumber / Keperluan *</label>
                                                <input type="text" name="sumber_keperluan" class="form-control" value="<?= e($row['sumber_keperluan']) ?>" placeholder="Contoh: Iuran Wajib / Beli Spanduk" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Keterangan Tambahan (Opsional)</label>
                                                <textarea name="keterangan" class="form-control" rows="2"><?= e($row['keterangan']) ?></textarea>
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

<!-- MODAL TAMBAH TRANSAKSI KAS -->
<div class="modal fade" id="modalTambahKas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header text-white" id="modalHeaderKas">
                    <h5 class="modal-title fw-bold" id="modalTitleKas"><i class="bi bi-cash-stack me-2"></i>Catat Transaksi Kas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jenis Transaksi *</label>
                        <select name="jenis" id="selectJenisKas" class="form-select" required>
                            <option value="pemasukan">Pemasukan (+)</option>
                            <option value="pengeluaran">Pengeluaran (-)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Transaksi *</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal (Rp) *</label>
                        <input type="number" name="nominal" class="form-control" placeholder="Contoh: 15500" min="1" step="any" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sumber / Keperluan *</label>
                        <input type="text" name="sumber_keperluan" class="form-control" placeholder="Contoh: Iuran Anggota / Pembelian Konsumsi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan Tambahan (Opsional)</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan detail jika diperlukan"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitKas"><i class="bi bi-check-circle me-1"></i> Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var modalTambah = document.getElementById('modalTambahKas');
        modalTambah.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var jenis = button ? button.getAttribute('data-jenis') : 'pemasukan';
            
            var header = document.getElementById('modalHeaderKas');
            var select = document.getElementById('selectJenisKas');
            var title = document.getElementById('modalTitleKas');
            
            select.value = jenis;

            if (jenis === 'pengeluaran') {
                header.className = 'modal-header bg-danger text-white';
                title.innerHTML = '<i class="bi bi-dash-circle me-2"></i>Catat Pengeluaran Kas';
            } else {
                header.className = 'modal-header bg-success text-white';
                title.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Catat Pemasukan Kas';
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>