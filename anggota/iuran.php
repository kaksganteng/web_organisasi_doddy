<?php
require_once __DIR__ . '/../config/functions.php';
// Pastikan hanya anggota yang bisa mengakses halaman ini
check_role(['anggota']); 

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// PENTING: Mencoba mengambil ID dari beberapa kemungkinan nama session yang sering digunakan
$id_anggota = $_SESSION['id'] ?? $_SESSION['id_anggota'] ?? $_SESSION['user_id'] ?? 0; 

// ------------------------------------------------------------------
// 1. PROCESS POST ACTIONS (BAYAR IURAN)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: iuran.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'bayar_iuran') {
        $id_jenis_iuran = (int)($_POST['id_jenis_iuran'] ?? 0);
        $bulan          = trim($_POST['bulan'] ?? '');
        $tahun          = trim($_POST['tahun'] ?? date('Y'));
        $nominal        = (int)($_POST['nominal'] ?? 0);
        $metode         = trim($_POST['metode'] ?? 'Transfer Bank');
        $tanggal_bayar  = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
        $keterangan     = trim($_POST['keterangan'] ?? '');
        $status         = 'pending'; // Selalu pending sampai dikonfirmasi admin

        // Handle File Upload Bukti Transfer
        $bukti_transfer = '';
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['bukti_transfer']['tmp_name'];
            $file_name = $_FILES['bukti_transfer']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = 'bukti_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                $upload_dir = __DIR__ . '/../assets/uploads/';
                
                // Buat folder jika belum ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $bukti_transfer = $new_file_name;
                } else {
                    $_SESSION['error_msg'] = "Gagal mengunggah file bukti transfer.";
                    header("Location: iuran.php");
                    exit;
                }
            } else {
                $_SESSION['error_msg'] = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
                header("Location: iuran.php");
                exit;
            }
        }

        // PENGECEKAN KEAMANAN TAMBAHAN UNTUK MENCEGAH ERROR 1452
        if (empty($id_anggota)) {
            $_SESSION['error_msg'] = "Gagal: Sesi login tidak terbaca (ID Anggota 0). Silakan pastikan nama session ID di file login sudah benar.";
            header("Location: iuran.php");
            exit;
        }

        if (empty($id_jenis_iuran) || $nominal <= 0) {
            $_SESSION['error_msg'] = "Pilih jenis iuran dan masukkan nominal yang benar.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO iuran (id_anggota, id_jenis_iuran, bulan, tahun, nominal, metode, bukti_transfer, tanggal_bayar, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_anggota, $id_jenis_iuran, $bulan, $tahun, $nominal, $metode, $bukti_transfer, $tanggal_bayar, $status, $keterangan]);
                $_SESSION['success_msg'] = "Data pembayaran berhasil dikirim dan sedang menunggu konfirmasi Admin!";
            } catch (Exception $ex) {
                $_SESSION['error_msg'] = "Gagal mengirim data pembayaran: " . $ex->getMessage();
            }
        }
        header("Location: iuran.php");
        exit;
    }
}

// ------------------------------------------------------------------
// 2. FETCH DATA UNTUK DITAMPILKAN
// ------------------------------------------------------------------

// Mengambil Statistik Iuran Anggota Ini (Status 'diterima')
$stmt_total = $db->prepare("SELECT SUM(nominal) as total_dibayar FROM iuran WHERE id_anggota = ? AND LOWER(status) = 'diterima'");
$stmt_total->execute([$id_anggota]);
$total_dibayar = $stmt_total->fetch()['total_dibayar'] ?? 0;

$stmt_pending = $db->prepare("SELECT COUNT(id) as total_pending FROM iuran WHERE id_anggota = ? AND LOWER(status) = 'pending'");
$stmt_pending->execute([$id_anggota]);
$total_pending = $stmt_pending->fetch()['total_pending'] ?? 0;

// Logika Pengecekan Tenggat Waktu / Peringatan Belum Bayar Bulan Ini
$current_month_name = '';
$bulans_arr = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$current_month_index = (int)date('n') - 1;
$current_month_name = $bulans_arr[$current_month_index];
$current_year = date('Y');

// Cek apakah anggota sudah memiliki iuran berstatus 'diterima' untuk bulan & tahun ini pada jenis iuran bulanan (misal Kas Rutin)
$stmt_cek_tenggat = $db->prepare("
    SELECT COUNT(i.id) as jml 
    FROM iuran i 
    JOIN jenis_iuran j ON i.id_jenis_iuran = j.id 
    WHERE i.id_anggota = ? 
      AND LOWER(i.status) = 'diterima' 
      AND i.bulan = ? 
      AND i.tahun = ? 
      AND (LOWER(j.tipe_periode) = 'bulan' OR LOWER(j.nama_iuran) LIKE '%kas%' OR LOWER(j.nama_iuran) LIKE '%iuran%')
");
$stmt_cek_tenggat->execute([$id_anggota, $current_month_name, $current_year]);
$sudah_bayar_bulan_ini = $stmt_cek_tenggat->fetch()['jml'] > 0;

// Mengambil Daftar Jenis Iuran (Untuk form modal pembayaran)
$stmt_jenis = $db->query("SELECT * FROM jenis_iuran ORDER BY nama_iuran ASC");
$list_jenis = $stmt_jenis->fetchAll();

// Mengambil Riwayat Pembayaran Anggota Ini (Pribadi)
$sql_riwayat = "
    SELECT i.*, j.nama_iuran, j.tipe_periode 
    FROM iuran i 
    LEFT JOIN jenis_iuran j ON i.id_jenis_iuran = j.id 
    WHERE i.id_anggota = ? 
    ORDER BY i.created_at DESC
";
$stmt_riwayat = $db->prepare($sql_riwayat);
$stmt_riwayat->execute([$id_anggota]);
$list_riwayat = $stmt_riwayat->fetchAll();

// Mengambil Semua Data Pembayaran Iuran Dari Seluruh Anggota (Untuk Transparansi Publik/Anggota)
$sql_semua = "
    SELECT i.*, a.nama_lengkap, j.nama_iuran, j.tipe_periode 
    FROM iuran i 
    LEFT JOIN anggota a ON i.id_anggota = a.id 
    LEFT JOIN jenis_iuran j ON i.id_jenis_iuran = j.id 
    ORDER BY CASE WHEN LOWER(i.status) = 'pending' THEN 1 ELSE 2 END, i.created_at DESC
";
$stmt_semua = $db->query($sql_semua);
$list_semua_pembayaran = $stmt_semua->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- DASHBOARD HERO HEADER -->
<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-wallet2 text-primary me-2"></i>Keuangan & Iuran Anggota
        </h3>
        <p class="text-muted mb-0 small">Lihat riwayat pribadi, transparansi pembayaran seluruh anggota, dan laporkan iuran Anda di sini.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBayarIuran">
            <i class="bi bi-send-plus-fill me-1"></i> Lapor Pembayaran
        </button>
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

<!-- NOTIFIKASI TENGGAT PEMBAYARAN BULAN INI -->
<?php if (!$sudah_bayar_bulan_ini): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center" role="alert">
        <div class="bg-warning text-white p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Peringatan / Pengingat Pembayaran!</h6>
            <p class="mb-0 small text-secondary">Anda belum memiliki catatan iuran yang berstatus <b>diterima</b> untuk periode bulan <b><?= $current_month_name ?> <?= $current_year ?></b>. Segera lakukan pembayaran agar terhindar dari keterlambatan!</p>
        </div>
    </div>
<?php endif; ?>

<!-- STATS CARDS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-success text-white">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-white bg-opacity-25 p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-check-all fs-2"></i>
                </div>
                <div>
                    <span class="text-white-50 small fw-semibold d-block text-uppercase tracking-wide">Total Iuran Saya (Diterima)</span>
                    <h3 class="mb-0 fw-bold">Rp <?= number_format($total_dibayar, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-hourglass-split fs-3"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-uppercase">Menunggu Konfirmasi Admin</span>
                    <h3 class="mb-0 fw-bold text-dark"><?= $total_pending ?> <small class="fs-6 text-muted fw-normal">Transaksi</small></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NAVIGATION TABS FOR TRANSPARENCY -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm" id="pills-tab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold px-4" id="pills-semua-tab" data-bs-toggle="pill" data-bs-target="#pills-semua" type="button" role="tab">
            <i class="bi bi-people-fill me-1"></i> Transparansi Seluruh Anggota
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold px-4" id="pills-pribadi-tab" data-bs-toggle="pill" data-bs-target="#pills-pribadi" type="button" role="tab">
            <i class="bi bi-person-badge me-1"></i> Riwayat Pembayaran Saya
        </button>
    </li>
</ul>

<div class="tab-content" id="pills-tabContent">

    <!-- TAB 1: TRANSPARANSI SEMUA ANGGOTA -->
    <div class="tab-pane fade show active" id="pills-semua" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-shield-check text-primary me-2"></i>Daftar Seluruh Pembayaran Iuran Anggota (Transparan)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Tanggal Bayar</th>
                            <th>Nama Anggota</th>
                            <th>Jenis Tagihan</th>
                            <th>Nominal & Metode</th>
                            <th>Status Dana</th>
                            <th class="pe-3">Keterangan / Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_semua_pembayaran)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Belum ada data pembayaran iuran dari anggota.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list_semua_pembayaran as $row): 
                                $status_lower = strtolower(trim($row['status'] ?? ''));
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= !empty($row['tanggal_bayar']) ? date('d M Y', strtotime($row['tanggal_bayar'])) : '-' ?></div>
                                        <small class="text-muted"><?= !empty($row['created_at']) ? date('H:i', strtotime($row['created_at'])) . ' WIB' : '' ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($row['nama_lengkap'] ?? 'Anggota Terhapus') ?></div>
                                    </td>
                                    <td>
                                        <span class="d-block text-dark fw-semibold"><?= e($row['nama_iuran'] ?? '-') ?></span>
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
                                        <?php elseif ($status_lower === 'ditolak'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="bi bi-x-circle-fill me-1"></i>ditolak
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                <i class="bi bi-hourglass-split me-1"></i>pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <small class="text-muted d-block"><?= e($row['keterangan'] ?: '-') ?></small>
                                        <?php if(!empty($row['bukti_transfer'])): ?>
                                            <a href="../assets/uploads/<?= e($row['bukti_transfer']) ?>" target="_blank" class="small text-decoration-none mt-1 d-inline-block">
                                                <i class="bi bi-image me-1"></i>Lihat Bukti
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: RIWAYAT PEMBAYARAN PRIBADI -->
    <div class="tab-pane fade" id="pills-pribadi" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pembayaran Saya Sendiri</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Tanggal Bayar</th>
                            <th>Jenis Iuran</th>
                            <th>Periode</th>
                            <th>Nominal</th>
                            <th>Status Dana</th>
                            <th class="pe-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_riwayat)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Anda belum memiliki riwayat pembayaran iuran.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list_riwayat as $row): 
                                $status_lower = strtolower(trim($row['status'] ?? ''));
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= !empty($row['tanggal_bayar']) ? date('d M Y', strtotime($row['tanggal_bayar'])) : '-' ?></div>
                                        <?php if(!empty($row['bukti_transfer'])): ?>
                                            <a href="../assets/uploads/<?= e($row['bukti_transfer']) ?>" target="_blank" class="small text-decoration-none">
                                                <i class="bi bi-image"></i> Lihat Bukti
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="d-block fw-bold text-dark"><?= e($row['nama_iuran'] ?? 'Iuran Terhapus') ?></span>
                                        <small class="text-muted">Per <?= e($row['tipe_periode'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if($row['bulan'] && $row['tahun']): ?>
                                            <span class="badge bg-light text-dark border"><?= e($row['bulan']) ?> <?= e($row['tahun']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="d-block fw-bold text-primary">Rp <?= number_format($row['nominal'], 0, ',', '.') ?></span>
                                        <small class="text-muted text-uppercase"><i class="bi bi-bank me-1"></i><?= e($row['metode']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($status_lower === 'diterima'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>diterima</span>
                                        <?php elseif ($status_lower === 'pending'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i>ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <small class="text-muted"><?= e($row['keterangan'] ?: '-') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL LAPOR PEMBAYARAN IURAN -->
<div class="modal fade" id="modalBayarIuran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="bayar_iuran">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send-plus me-2"></i>Formulir Pembayaran Iuran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-info bg-info-subtle border-info-subtle d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill me-3 fs-4 text-info"></i>
                        <small>Pastikan Anda sudah melakukan transfer sesuai nominal sebelum mengisi form ini. Data yang disubmit akan dikonfirmasi terlebih dahulu oleh admin.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Pilih Jenis Iuran *</label>
                            <select name="id_jenis_iuran" id="selectJenisIuran" class="form-select" required onchange="updateNominal()">
                                <option value="" disabled selected>-- Pilih Iuran --</option>
                                <?php foreach($list_jenis as $j): ?>
                                    <option value="<?= $j['id'] ?>" data-nominal="<?= $j['nominal_default'] ?>">
                                        <?= e($j['nama_iuran']) ?> (Rp <?= number_format($j['nominal_default'], 0, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nominal Bayar (Rp) *</label>
                            <input type="number" name="nominal" id="inputNominal" class="form-control" required placeholder="0">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Untuk Bulan (Opsional)</label>
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
                            <label class="form-label small fw-semibold">Tahun</label>
                            <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tanggal Bayar *</label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Metode Pembayaran *</label>
                            <select name="metode" class="form-select" required>
                                <option value="Transfer Bank">Transfer Bank</option>
                                <option value="E-Wallet">E-Wallet (Dana, Ovo, dll)</option>
                                <option value="Tunai">Tunai / Cash</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bukti Transfer (Opsional)</label>
                            <input type="file" name="bukti_transfer" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted" style="font-size: 0.7rem;">Format: JPG, PNG, PDF</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan Tambahan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Pembayaran iuran kas bulan Januari dan Februari..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-send-check me-1"></i> Kirim Laporan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateNominal() {
    var select = document.getElementById("selectJenisIuran");
    var nominalInput = document.getElementById("inputNominal");
    var selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value !== "") {
        var defaultNominal = selectedOption.getAttribute("data-nominal");
        nominalInput.value = defaultNominal;
    } else {
        nominalInput.value = "";
    }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>