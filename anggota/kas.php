<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['anggota']);

// ------------------------------------------------------------------
// 1. HITUNG AKUMULASI SALDO OTOMATIS & RINGKASAN
// ------------------------------------------------------------------
$total_pemasukan_all   = (float)($db->query("SELECT SUM(nominal) FROM kas WHERE jenis = 'pemasukan'")->fetchColumn() ?: 0);
$total_pengeluaran_all = (float)($db->query("SELECT SUM(nominal) FROM kas WHERE jenis = 'pengeluaran'")->fetchColumn() ?: 0);
$saldo_kas_saat_ini    = $total_pemasukan_all - $total_pengeluaran_all;

// ------------------------------------------------------------------
// 2. FETCH DATA TRANSAKSI & FILTER
// ------------------------------------------------------------------
$filter_tgl_mulai   = $_GET['tgl_mulai'] ?? '';
$filter_tgl_selesai = $_GET['tgl_selesai'] ?? '';
$filter_jenis       = $_GET['jenis'] ?? 'all';
$search             = trim($_GET['search'] ?? '');

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
$filtered_pemasukan   = 0;
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
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Laporan Buku Kas Organisasi</h4>
        <p class="text-muted small mb-0">Transparansi arus kas masuk, kas keluar, dan akumulasi saldo organisasi secara real-time.</p>
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

<!-- TRANSACTIONS TABLE (READ-ONLY) -->
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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_kas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi kas yang tercatat.</td>
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
                            <td><small class="text-muted font-monospace"><i class="bi bi-person me-1"></i><?= e($row['username'] ?? 'Admin') ?></small></td>
                            <td class="text-end fw-bold <?= $row['jenis'] === 'pemasukan' ? 'text-success' : 'text-danger' ?>">
                                <?= $row['jenis'] === 'pemasukan' ? '+' : '-' ?> Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>