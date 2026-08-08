<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ------------------------------------------------------------------
// 1. PROCESS POST ACTIONS (TAMBAH JENIS, EDIT, HAPUS, KONFIRMASI, MANUAL)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: iuran.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // -- ACTION: TAMBAH JENIS IURAN --
    if ($action === 'add_jenis') {
        $nama_iuran      = trim($_POST['nama_iuran'] ?? '');
        $tipe_periode    = trim($_POST['tipe_periode'] ?? 'bulan');
        $nominal_default = (int)($_POST['nominal_default'] ?? 0);
        $keterangan      = trim($_POST['keterangan'] ?? '');

        if (empty($nama_iuran) || $nominal_default <= 0) {
            $_SESSION['error_msg'] = "Judul iuran dan nominal wajib diisi dengan benar!";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO jenis_iuran (nama_iuran, tipe_periode, nominal_default, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama_iuran, $tipe_periode, $nominal_default, $keterangan]);
                $_SESSION['success_msg'] = "Jenis iuran baru berhasil ditambahkan!";
            } catch (Exception $ex) {
                $_SESSION['error_msg'] = "Gagal menambah jenis iuran: " . $ex->getMessage();
            }
        }
        header("Location: iuran.php#jenis");
        exit;
    }

    // -- ACTION: EDIT JENIS IURAN --
    if ($action === 'edit_jenis') {
        $id              = (int)($_POST['id'] ?? 0);
        $nama_iuran      = trim($_POST['nama_iuran'] ?? '');
        $tipe_periode    = trim($_POST['tipe_periode'] ?? 'bulan');
        $nominal_default = (int)($_POST['nominal_default'] ?? 0);
        $keterangan      = trim($_POST['keterangan'] ?? '');

        try {
            $stmt = $db->prepare("UPDATE jenis_iuran SET nama_iuran = ?, tipe_periode = ?, nominal_default = ?, keterangan = ? WHERE id = ?");
            $stmt->execute([$nama_iuran, $tipe_periode, $nominal_default, $keterangan, $id]);
            $_SESSION['success_msg'] = "Jenis iuran berhasil diperbarui!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $ex->getMessage();
        }
        header("Location: iuran.php#jenis");
        exit;
    }

    // -- ACTION: HAPUS JENIS IURAN --
    if ($action === 'delete_jenis') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM jenis_iuran WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_msg'] = "Jenis iuran berhasil dihapus!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal menghapus! Pastikan tidak ada data pembayaran yang terkait dengan jenis iuran ini. Detail: " . $ex->getMessage();
        }
        header("Location: iuran.php#jenis");
        exit;
    }

    // -- ACTION: KONFIRMASI PEMBAYARAN IURAN --
    if ($action === 'konfirmasi_pembayaran') {
        $id_iuran = (int)($_POST['id_iuran'] ?? 0);
        $status_baru = trim($_POST['status'] ?? 'diterima'); // 'diterima' atau 'ditolak'

        try {
            $stmt = $db->prepare("UPDATE iuran SET status = ? WHERE id = ?");
            $stmt->execute([$status_baru, $id_iuran]);
            
            $status_text = $status_baru === 'diterima' ? 'diterima (Sudah Membayar)' : 'ditolak';
            $_SESSION['success_msg'] = "Pembayaran berhasil dikonfirmasi sebagai $status_text!";
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal memproses konfirmasi: " . $ex->getMessage();
        }
        header("Location: iuran.php#pembayaran");
        exit;
    }

    // -- ACTION: INPUT PEMBAYARAN MANUAL OLEH ADMIN --
    if ($action === 'admin_bayar_manual') {
        $id_anggota      = (int)($_POST['id_anggota'] ?? 0);
        $id_jenis_iuran  = (int)($_POST['id_jenis_iuran'] ?? 0);
        $bulan           = trim($_POST['bulan'] ?? '');
        $tahun           = trim($_POST['tahun'] ?? date('Y'));
        $nominal         = (int)($_POST['nominal'] ?? 0);
        $metode          = trim($_POST['metode'] ?? 'Tunai / Cash');
        $tanggal_bayar   = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
        $keterangan      = trim($_POST['keterangan'] ?? 'Dibayar langsung (Cash) dan dikonfirmasi Admin.');
        $status          = 'diterima'; // Langsung berstatus diterima
        $bukti_transfer  = ''; 

        if (empty($id_anggota) || empty($id_jenis_iuran) || $nominal <= 0) {
            $_SESSION['error_msg'] = "Pilih anggota, jenis iuran, dan isi nominal dengan benar!";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO iuran (id_anggota, id_jenis_iuran, bulan, tahun, nominal, metode, bukti_transfer, tanggal_bayar, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_anggota, $id_jenis_iuran, $bulan, $tahun, $nominal, $metode, $bukti_transfer, $tanggal_bayar, $status, $keterangan]);
                $_SESSION['success_msg'] = "Pembayaran manual berhasil dicatat dan status berubah menjadi Sudah Membayar!";
            } catch (Exception $ex) {
                $_SESSION['error_msg'] = "Gagal memproses pembayaran manual: " . $ex->getMessage();
            }
        }
        header("Location: iuran.php#pembayaran");
        exit;
    }
}

// ------------------------------------------------------------------
// 2. FETCH DATA UNTUK DITAMPILKAN
// ------------------------------------------------------------------

// Mengambil Statistik Iuran (Status 'diterima' dihitung sebagai dana masuk)
$stmt_total = $db->query("SELECT SUM(nominal) as total_terkumpul FROM iuran WHERE LOWER(status) = 'diterima'");
$stats = $stmt_total->fetch();
$total_terkumpul = $stats['total_terkumpul'] ?? 0;

$stmt_pending = $db->query("SELECT COUNT(id) as total_pending FROM iuran WHERE LOWER(status) = 'pending'");
$total_pending = $stmt_pending->fetch()['total_pending'] ?? 0;

// Mengambil Data Jenis Iuran
$stmt_jenis = $db->query("SELECT * FROM jenis_iuran ORDER BY created_at DESC");
$list_jenis_iuran = $stmt_jenis->fetchAll();

// Mengambil Data Anggota (Untuk Select Box Input Manual)
$stmt_anggota = $db->query("SELECT id, nama_lengkap FROM anggota ORDER BY nama_lengkap ASC");
$list_anggota = $stmt_anggota->fetchAll();

// Mengambil Data Pembayaran Iuran + Join ke tabel anggota dan jenis_iuran
$sql_pembayaran = "
    SELECT i.*, a.nama_lengkap, j.nama_iuran, j.tipe_periode 
    FROM iuran i 
    LEFT JOIN anggota a ON i.id_anggota = a.id 
    LEFT JOIN jenis_iuran j ON i.id_jenis_iuran = j.id 
    ORDER BY CASE WHEN LOWER(i.status) = 'pending' THEN 1 ELSE 2 END, i.created_at DESC
";
$stmt_bayar = $db->query($sql_pembayaran);
$list_pembayaran = $stmt_bayar->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- DASHBOARD HERO HEADER -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-wallet2 text-primary me-2"></i>Keuangan & Iuran
        </h3>
        <p class="text-muted mb-0 small">Kelola jenis tagihan iuran dan konfirmasi penerimaan dana pembayaran dari anggota.</p>
    </div>
</div>

<!-- ALERT MESSAGES -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- STATS CARDS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-primary text-white">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-white bg-opacity-25 p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-cash-coin fs-2"></i>
                </div>
                <div>
                    <span class="text-white-50 small fw-semibold d-block text-uppercase tracking-wide">Total Dana Diterima</span>
                    <h3 class="mb-0 fw-bold">Rp <?= number_format($total_terkumpul, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-hourglass-split fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-uppercase">Menunggu Konfirmasi</span>
                    <h3 class="mb-0 fw-bold text-dark"><?= $total_pending ?> <small class="fs-6 text-muted fw-normal">Transaksi</small></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-tags-fill fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-uppercase">Jenis Iuran Aktif</span>
                    <h3 class="mb-0 fw-bold text-dark"><?= count($list_jenis_iuran) ?> <small class="fs-6 text-muted fw-normal">Kategori</small></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NAVIGATION TABS -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm" id="pills-tab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold px-4" id="pills-pembayaran-tab" data-bs-toggle="pill" data-bs-target="#pills-pembayaran" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Transaksi & Konfirmasi Dana
            <?php if($total_pending > 0): ?>
                <span class="badge bg-danger ms-1 rounded-pill"><?= $total_pending ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold px-4" id="pills-jenis-tab" data-bs-toggle="pill" data-bs-target="#pills-jenis" type="button" role="tab">
            <i class="bi bi-list-check me-1"></i> Kelola Jenis Iuran
        </button>
    </li>
</ul>

<div class="tab-content" id="pills-tabContent">

    <!-- TAB 1: KONFIRMASI PEMBAYARAN -->
    <div class="tab-pane fade show active" id="pills-pembayaran" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Daftar Transaksi & Konfirmasi Pembayaran Anggota</h6>
                
                <!-- TOMBOL INPUT MANUAL -->
                <button class="btn btn-success btn-sm shadow-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBayarManual">
                    <i class="bi bi-cash-stack me-1"></i> Input Pembayaran Tunai (Cash)
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Tanggal Bayar</th>
                            <th>Anggota</th>
                            <th>Jenis Tagihan</th>
                            <th>Nominal & Metode</th>
                            <th>Status Dana</th>
                            <th class="text-center pe-3">Aksi & Bukti Transfer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_pembayaran)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Belum ada data pembayaran iuran.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list_pembayaran as $row): 
                                $status_lower = strtolower(trim($row['status'] ?? ''));
                            ?>
                                <tr class="<?= $status_lower === 'pending' ? 'table-warning bg-opacity-10' : '' ?>">
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($row['tanggal_bayar'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?> WIB</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($row['nama_lengkap'] ?? 'Anggota Terhapus') ?></div>
                                        <small class="text-muted"><i class="bi bi-chat-text me-1"></i><?= e($row['keterangan'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="d-block text-dark fw-semibold"><?= e($row['nama_iuran']) ?></span>
                                        <small class="badge bg-light text-dark border">Periode: <?= e($row['bulan'] ?: '-') ?> <?= e($row['tahun'] ?: '') ?></small>
                                    </td>
                                    <td>
                                        <span class="d-block fw-bold text-primary">Rp <?= number_format($row['nominal'], 0, ',', '.') ?></span>
                                        <small class="text-muted text-uppercase"><i class="bi bi-wallet2 me-1"></i><?= e($row['metode']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($status_lower === 'diterima'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>diterima
                                            </span>
                                        <?php else: ?>
                                            <?php if ($status_lower === 'ditolak'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 mb-1 d-inline-block">
                                                    <i class="bi bi-x-circle-fill me-1"></i>ditolak
                                                </span><br>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalKonfirmasi<?= $row['id'] ?>">
                                                <i class="bi bi-shield-exclamation me-1"></i> Konfirmasi
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <?php if ($status_lower === 'diterima'): ?>
                                            <span class="text-muted small d-block"><i class="bi bi-shield-check me-1"></i>Terkonfirmasi</span>
                                        <?php else: ?>
                                            <span class="text-muted small d-block">Menunggu Tindakan Admin</span>
                                        <?php endif; ?>

                                        <!-- Lihat Bukti Transfer / Foto -->
                                        <?php if(!empty($row['bukti_transfer'])): ?>
                                            <a href="../assets/uploads/<?= e($row['bukti_transfer']) ?>" target="_blank" class="d-inline-block mt-2 small text-decoration-none bg-light border px-2 py-1 rounded text-primary">
                                                <i class="bi bi-image me-1"></i>Lihat Bukti Foto
                                            </a>
                                        <?php else: ?>
                                            <small class="d-block mt-1 text-muted fst-italic">Tanpa Lampiran Foto</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- MODAL KONFIRMASI PEMBAYARAN (DENGAN BUKTI & PERTANYAAN AKHIR) -->
                                <?php if ($status_lower !== 'diterima'): ?>
                                <div class="modal fade" id="modalKonfirmasi<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                        <div class="modal-content border-0 shadow-lg">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Konfirmasi Pembayaran - <?= e($row['nama_lengkap']) ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Informasi Singkat Transaksi -->
                                                <div class="row g-3 mb-3 bg-light p-3 rounded mx-0 align-items-center">
                                                    <div class="col-md-6">
                                                        <span class="text-muted small d-block">Nominal Tagihan:</span>
                                                        <h5 class="fw-bold text-primary mb-0">Rp <?= number_format($row['nominal'], 0, ',', '.') ?></h5>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <span class="text-muted small d-block">Kategori & Periode:</span>
                                                        <span class="fw-semibold text-dark"><?= e($row['nama_iuran']) ?> (<?= e($row['bulan'] ?: '-') ?> <?= e($row['tahun'] ?: '') ?>)</span>
                                                    </div>
                                                </div>

                                                <!-- Bukti Transfer / Foto -->
                                                <div class="mb-4">
                                                    <label class="form-label small fw-semibold text-secondary d-block">Bukti Transfer / Foto Pembayaran:</label>
                                                    <?php if(!empty($row['bukti_transfer'])): ?>
                                                        <div class="text-center bg-light p-2 rounded border">
                                                            <a href="../assets/uploads/<?= e($row['bukti_transfer']) ?>" target="_blank">
                                                                <img src="../assets/uploads/<?= e($row['bukti_transfer']) ?>" alt="Bukti Transfer" class="img-fluid rounded shadow-sm" style="max-height: 280px; object-fit: contain;">
                                                            </a>
                                                            <small class="d-block text-muted mt-1">Klik gambar untuk memperbesar di tab baru</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning py-2 small mb-0">
                                                            <i class="bi bi-exclamation-triangle me-1"></i> Tidak ada lampiran foto bukti transfer dari anggota.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Bagian Pilihan Awal: Diterima atau Ditolak -->
                                                <div id="stepChoice<?= $row['id'] ?>">
                                                    <label class="form-label small fw-semibold text-secondary d-block mb-2">Tentukan Status Pembayaran:</label>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-success flex-grow-1 fw-semibold py-2" onclick="showFinalConfirm(<?= $row['id'] ?>, 'diterima')">
                                                            <i class="bi bi-check-lg me-1"></i> Diterima
                                                        </button>
                                                        <button type="button" class="btn btn-danger flex-grow-1 fw-semibold py-2" onclick="showFinalConfirm(<?= $row['id'] ?>, 'ditolak')">
                                                            <i class="bi bi-x-lg me-1"></i> Ditolak
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Pertanyaan Konfirmasi Akhir Jika Diterima (Beserta Bukti Foto yang Tetap Tampil) -->
                                                <div id="stepFinalDiterima<?= $row['id'] ?>" class="d-none">
                                                    <div class="alert alert-success border-success-subtle mb-3">
                                                        <div class="fw-bold text-success mb-1"><i class="bi bi-question-circle-fill me-1"></i> Pertanyaan Konfirmasi Terakhir:</div>
                                                        <small class="text-dark">Apakah Anda yakin ingin menyatakan dana ini <b>DITERIMA</b>? Pastikan foto bukti transfer di atas sudah valid dan sesuai sehingga admin lebih yakin!</small>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="action" value="konfirmasi_pembayaran">
                                                        <input type="hidden" name="id_iuran" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="diterima">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="resetChoice(<?= $row['id'] ?>)">Kembali</button>
                                                            <button type="submit" class="btn btn-success fw-semibold">
                                                                <i class="bi bi-check-circle-fill me-1"></i> Ya, Yakin & Konfirmasi Diterima
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>

                                                <!-- Konfirmasi Akhir Jika Ditolak -->
                                                <div id="stepFinalDitolak<?= $row['id'] ?>" class="d-none">
                                                    <div class="alert alert-danger border-danger-subtle mb-3">
                                                        <div class="fw-bold text-danger mb-1"><i class="bi bi-question-circle-fill me-1"></i> Konfirmasi Penolakan:</div>
                                                        <small class="text-dark">Apakah Anda yakin ingin menolak pembayaran ini?</small>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="action" value="konfirmasi_pembayaran">
                                                        <input type="hidden" name="id_iuran" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="ditolak">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="resetChoice(<?= $row['id'] ?>)">Kembali</button>
                                                            <button type="submit" class="btn btn-danger fw-semibold">
                                                                <i class="bi bi-x-circle-fill me-1"></i> Ya, Tolak Pembayaran
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: KELOLA JENIS IURAN -->
    <div class="tab-pane fade" id="pills-jenis" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-tags text-primary me-2"></i>Daftar Jenis & Periode Tagihan Iuran</h6>
                <button class="btn btn-primary btn-sm shadow-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahJenis">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Kategori Baru
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#</th>
                            <th>Judul Iuran</th>
                            <th>Periode Tagihan & Perhitungan</th>
                            <th>Besaran Biaya (Rp)</th>
                            <th>Keterangan</th>
                            <th class="text-center pe-3" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_jenis_iuran)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">Belum ada jenis iuran yang dibuat.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($list_jenis_iuran as $jenis): 
                                $tipe = strtolower($jenis['tipe_periode']);
                                $label_periode = 'Per Bulan';
                                $badge_color = 'bg-info-subtle text-info border-info-subtle';
                                
                                if ($tipe === 'hari') {
                                    $label_periode = 'Tagihan Harian (Per Hari)';
                                    $badge_color = 'bg-primary-subtle text-primary border-primary-subtle';
                                } elseif ($tipe === 'minggu') {
                                    $label_periode = 'Tagihan Mingguan (Per Minggu)';
                                    $badge_color = 'bg-warning-subtle text-warning border-warning-subtle';
                                } elseif ($tipe === 'bulan') {
                                    $label_periode = 'Tagihan Bulanan (Per Bulan)';
                                    $badge_color = 'bg-info-subtle text-info border-info-subtle';
                                } elseif ($tipe === 'tahun') {
                                    $label_periode = 'Tagihan Tahunan (Per Tahun)';
                                    $badge_color = 'bg-success-subtle text-success border-success-subtle';
                                } elseif ($tipe === 'insidental') {
                                    $label_periode = 'Insidental (Sekali Bayar)';
                                    $badge_color = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                }
                            ?>
                                <tr>
                                    <td class="ps-3 text-muted fw-semibold"><?= $no++ ?></td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= e($jenis['nama_iuran']) ?></span>
                                        <small class="text-muted"><i class="bi bi-calendar-event me-1"></i>Dibuat tgl: <?= date('d M Y', strtotime($jenis['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_color ?> border px-2 py-1 text-uppercase fw-semibold">
                                            <i class="bi bi-clock-history me-1"></i><?= $label_periode ?>
                                        </span>
                                        <small class="d-block mt-1 text-muted">Dihitung aktif sejak tanggal pembuatan.</small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        Rp <?= number_format($jenis['nominal_default'], 0, ',', '.') ?>
                                    </td>
                                    <td><small class="text-muted"><?= e($jenis['keterangan'] ?: '-') ?></small></td>
                                    <td class="text-center pe-3">
                                        <div class="btn-group btn-group-sm shadow-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditJenis<?= $jenis['id'] ?>" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus jenis iuran ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_jenis">
                                                <input type="hidden" name="id" value="<?= $jenis['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT JENIS IURAN -->
                                <div class="modal fade" id="modalEditJenis<?= $jenis['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="edit_jenis">
                                                <input type="hidden" name="id" value="<?= $jenis['id'] ?>">

                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Kategori & Periode Iuran</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Judul Iuran *</label>
                                                        <input type="text" name="nama_iuran" class="form-control" value="<?= e($jenis['nama_iuran']) ?>" required>
                                                    </div>
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Periode Tagihan *</label>
                                                            <select name="tipe_periode" class="form-select" required>
                                                                <option value="hari" <?= $jenis['tipe_periode'] == 'hari' ? 'selected' : '' ?>>Per Hari</option>
                                                                <option value="minggu" <?= $jenis['tipe_periode'] == 'minggu' ? 'selected' : '' ?>>Per Minggu</option>
                                                                <option value="bulan" <?= $jenis['tipe_periode'] == 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                                                                <option value="tahun" <?= $jenis['tipe_periode'] == 'tahun' ? 'selected' : '' ?>>Per Tahun</option>
                                                                <option value="insidental" <?= $jenis['tipe_periode'] == 'insidental' ? 'selected' : '' ?>>Insidental (Sekali Bayar)</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Biaya Iuran (Rp) *</label>
                                                            <input type="number" name="nominal_default" class="form-control" value="<?= $jenis['nominal_default'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Keterangan / Deskripsi</label>
                                                        <textarea name="keterangan" class="form-control" rows="2"><?= e($jenis['keterangan']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
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
    </div>
</div>

<!-- MODAL TAMBAH JENIS IURAN -->
<div class="modal fade" id="modalTambahJenis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_jenis">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Buat Jenis Iuran Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Iuran *</label>
                        <input type="text" name="nama_iuran" class="form-control" placeholder="Contoh: Kas Rutin, Uang Gedung..." required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Periode Tagihan *</label>
                            <select name="tipe_periode" class="form-select" required>
                                <option value="hari">Per Hari</option>
                                <option value="minggu">Per Minggu</option>
                                <option value="bulan" selected>Per Bulan</option>
                                <option value="tahun">Per Tahun</option>
                                <option value="insidental">Insidental (Sekali Bayar)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Biaya Iuran (Rp) *</label>
                            <input type="number" name="nominal_default" class="form-control" placeholder="10000" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan / Deskripsi</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Penjelasan mengenai tujuan iuran ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-check-circle me-1"></i> Simpan Jenis Iuran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL INPUT PEMBAYARAN MANUAL (CASH/TUNAI) -->
<div class="modal fade" id="modalBayarManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="admin_bayar_manual">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Input Pembayaran Tunai (Cash) Secara Langsung</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-success bg-success-subtle border-success-subtle d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill me-3 fs-4 text-success"></i>
                        <small>Gunakan form ini jika anggota membayar tunai secara langsung. Sistem akan langsung mencatat dana masuk dan mengubah status menjadi <b>Sudah Membayar (Diterima)</b>.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Nama Anggota *</label>
                            <select name="id_anggota" class="form-select" required>
                                <option value="" disabled selected>-- Pilih Anggota --</option>
                                <?php foreach($list_anggota as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= e($a['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Pilih Jenis Iuran *</label>
                            <select name="id_jenis_iuran" id="selectJenisIuranManual" class="form-select" required onchange="updateNominalManual()">
                                <option value="" disabled selected>-- Pilih Jenis Tagihan --</option>
                                <?php foreach($list_jenis_iuran as $j): ?>
                                    <option value="<?= $j['id'] ?>" data-nominal="<?= $j['nominal_default'] ?>">
                                        <?= e($j['nama_iuran']) ?> (Rp <?= number_format($j['nominal_default'], 0, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nominal Diterima (Rp) *</label>
                            <input type="number" name="nominal" id="inputNominalManual" class="form-control" required placeholder="0">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Untuk Periode Bulan (Opsional)</label>
                            <select name="bulan" class="form-select">
                                <option value="">-- Pilih Bulan --</option>
                                <?php 
                                $bulans = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                                $current_month = date('n') - 1;
                                foreach($bulans as $index => $b): ?>
                                    <option value="<?= $b ?>" <?= $index === $current_month ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tahun *</label>
                            <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Pembayaran *</label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Metode Pembayaran</label>
                            <input type="text" name="metode" class="form-control" value="Tunai / Cash" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan / Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2">Dibayar langsung secara tunai (Cash) dan dikonfirmasi oleh Admin.</textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-semibold"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script untuk auto-select Tab, Auto-Nominal & Logika Modal Konfirmasi -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            var triggerEl = document.querySelector('button[data-bs-target="' + window.location.hash + '"]');
            if(triggerEl) {
                var tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }
        }
    });

    function updateNominalManual() {
        var select = document.getElementById("selectJenisIuranManual");
        var nominalInput = document.getElementById("inputNominalManual");
        var selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption.value !== "") {
            var defaultNominal = selectedOption.getAttribute("data-nominal");
            nominalInput.value = defaultNominal;
        } else {
            nominalInput.value = "";
        }
    }

    function showFinalConfirm(id, status) {
        document.getElementById('stepChoice' + id).classList.add('d-none');
        if (status === 'diterima') {
            document.getElementById('stepFinalDiterima' + id).classList.remove('d-none');
        } else {
            document.getElementById('stepFinalDitolak' + id).classList.remove('d-none');
        }
    }

    function resetChoice(id) {
        document.getElementById('stepFinalDiterima' + id).classList.add('d-none');
        document.getElementById('stepFinalDitolak' + id).classList.add('d-none');
        document.getElementById('stepChoice' + id).classList.remove('d-none');
    }
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>