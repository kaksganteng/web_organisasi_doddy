<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['anggota']);

$id_anggota = $_SESSION['id_anggota'];

// 1. Fetch Profile Anggota Login
$stmt_profile = $db->prepare("SELECT * FROM anggota WHERE id = ?");
$stmt_profile->execute([$id_anggota]);
$profile = $stmt_profile->fetch();

if (!$profile) {
    die("Data profil tidak ditemukan.");
}

// 2. Status Iuran Bulan Ini (Menyesuaikan dengan database: 'diterima', 'pending'/'menunggu', 'ditolak')
$bulan_ini_angka = (int)date('m');
$bulan_ini_nama = ['1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April', '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus', '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'][(string)$bulan_ini_angka];
$tahun_ini = (int)date('Y');

// Cek berdasarkan string nama bulan atau angka bulan (mendukung kedua format penyimpanan di DB)
$stmt_iuran_now = $db->prepare("SELECT * FROM iuran WHERE id_anggota = ? AND (bulan = ? OR bulan = ?) AND tahun = ? ORDER BY id DESC LIMIT 1");
$stmt_iuran_now->execute([$id_anggota, $bulan_ini_nama, $bulan_ini_angka, $tahun_ini]);
$iuran_bulan_ini = $stmt_iuran_now->fetch();

// 3. Total Saldo Kas Organisasi
$total_pemasukan = (float)$db->query("SELECT COALESCE(SUM(nominal), 0) FROM kas WHERE jenis = 'pemasukan'")->fetchColumn();
$total_pengeluaran = (float)$db->query("SELECT COALESCE(SUM(nominal), 0) FROM kas WHERE jenis = 'pengeluaran'")->fetchColumn();
$saldo_kas_organisasi = $total_pemasukan - $total_pengeluaran;

// 4. Pengumuman Terbaru
$pengumuman_list = $db->query("SELECT p.*, u.username FROM pengumuman p JOIN users u ON p.id_admin = u.id ORDER BY p.created_at DESC LIMIT 3")->fetchAll();

// 5. Chat Admin Terbaru
$chat_list = $db->query("SELECT c.*, u.username FROM chat c JOIN users u ON c.id_admin = u.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

// 6. Riwayat Pembayaran Pribadi (Terakhir 5 Transaksi)
$stmt_riwayat = $db->prepare("SELECT * FROM iuran WHERE id_anggota = ? ORDER BY tahun DESC, id DESC LIMIT 5");
$stmt_riwayat->execute([$id_anggota]);
$riwayat_iuran = $stmt_riwayat->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- ROW 1: KARTU INFORMASI UTAMA & STATUS IURAN BULAN INI -->
<div class="row g-3 mb-4">
    <!-- Card Data Diri Ringkas -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;">
                    <i class="bi bi-person-fill fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><?= e($profile['nama_lengkap']) ?></h6>
                    <small class="text-muted d-block"><?= e($profile['email']) ?></small>
                    <span class="badge bg-success mt-1"><i class="bi bi-check-circle me-1"></i>Anggota Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Status Iuran Bulan Ini -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm p-3 h-100">
            <span class="text-muted small fw-semibold">Status Iuran Bulan Ini (<?= $bulan_ini_nama ?> <?= $tahun_ini ?>)</span>
            <div class="mt-2">
                <?php if (!$iuran_bulan_ini): ?>
                    <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i>Belum Dibayar</span>
                    <a href="<?= base_url('anggota/iuran.php') ?>" class="btn btn-sm btn-outline-danger ms-2">Bayar Sekarang</a>
                <?php 
                else: 
                    $status_lower = strtolower(trim($iuran_bulan_ini['status']));
                    if ($status_lower === 'diterima' || $status_lower === 'disetujui' || $status_lower === 'lunas'): 
                ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Sudah Dibayar & Terkonfirmasi</span>
                    <small class="text-muted d-block mt-1">Terkonfirmasi lunas pada <?= !empty($iuran_bulan_ini['tanggal_bayar']) ? date('d M Y', strtotime($iuran_bulan_ini['tanggal_bayar'])) : '-' ?></small>
                <?php elseif ($status_lower === 'pending' || $status_lower === 'menunggu'): ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split me-1"></i>Menunggu Konfirmasi Admin</span>
                    <small class="text-muted d-block mt-1">Pembayaran dicatat, menunggu verifikasi.</small>
                <?php else: ?>
                    <span class="badge bg-danger fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Ditolak Admin</span>
                    <a href="<?= base_url('anggota/iuran.php') ?>" class="btn btn-sm btn-outline-primary ms-2">Upload Ulang</a>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>

    <!-- Card Saldo Kas Organisasi -->
    <div class="col-12 col-md-12 col-lg-4">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Saldo Kas Organisasi</span>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= format_rupiah($saldo_kas_organisasi) ?></h3>
                    <small class="text-muted">Transparan & Terbuka</small>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-bank fs-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 2: CHAT BROADCAST & PENGUMUMAN -->
<div class="row g-4 mb-4">
    <!-- Chat Broadcast Admin -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-chat-left-text-fill text-primary me-2"></i>Informasi & Broadcast Pengurus</h6>
                <span class="badge bg-info text-dark"><i class="bi bi-broadcast me-1"></i>Realtime Polling</span>
            </div>
            
            <div id="chatContainer" class="p-3 bg-light rounded shadow-inner" style="height: 280px; overflow-y: auto;">
                <?php if (empty($chat_list)): ?>
                    <p class="text-muted small text-center my-5">Belum ada informasi broadcast dari admin.</p>
                <?php else: ?>
                    <?php foreach ($chat_list as $chat): ?>
                        <div class="bg-white p-2 rounded shadow-sm mb-2">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                                <span class="fw-bold small text-primary"><i class="bi bi-shield-check me-1"></i><?= e($chat['username']) ?></span>
                                <small class="text-muted" style="font-size:0.75rem;"><?= date('H:i - d M Y', strtotime($chat['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small text-dark"><?= e($chat['pesan']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pengumuman Terbaru -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-megaphone-fill text-warning me-2"></i>Pengumuman Organisasi</h6>
                <a href="<?= base_url('anggota/pengumuman.php') ?>" class="btn btn-sm btn-light">Lihat Semua</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($pengumuman_list)): ?>
                    <p class="text-muted small text-center py-4">Tidak ada pengumuman terbaru.</p>
                <?php else: ?>
                    <?php foreach ($pengumuman_list as $peng): ?>
                        <div class="list-group-item border-0 px-0 mb-2">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h6 class="mb-1 fw-bold text-dark"><?= e($peng['judul']) ?></h6>
                                <small class="text-muted"><?= date('d M Y', strtotime($peng['created_at'])) ?></small>
                            </div>
                            <p class="mb-1 text-muted small"><?= e(substr(strip_tags($peng['isi']), 0, 100)) ?>...</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ROW 3: RIWAYAT PEMBAYARAN IURAN PRIBADI -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Riwayat Pembayaran Iuran Saya</h6>
                <a href="<?= base_url('anggota/iuran.php') ?>" class="btn btn-sm btn-primary">Kelola Pembayaran</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Periode Bulan / Tahun</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Tanggal Bayar</th>
                            <th>Status Dana</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat_iuran)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Belum ada riwayat pembayaran iuran.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_iuran as $r): 
                                $status_lower = strtolower(trim($r['status'] ?? ''));
                            ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?= !empty($r['bulan']) ? e($r['bulan']) : '-' ?> <?= !empty($r['tahun']) ? e($r['tahun']) : '' ?>
                                    </td>
                                    <td><?= format_rupiah($r['nominal']) ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?= e($r['metode']) ?></span></td>
                                    <td><?= !empty($r['tanggal_bayar']) ? format_tanggal($r['tanggal_bayar']) : '-' ?></td>
                                    <td>
                                        <?php if ($status_lower === 'diterima' || $status_lower === 'disetujui' || $status_lower === 'lunas'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Diterima</span>
                                        <?php elseif ($status_lower === 'pending' || $status_lower === 'menunggu'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Menunggu Konfirmasi</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>
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
</div>

<!-- AJAX POLLING SCRIPT UNTUK CHAT REALTIME ANGGOTA -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    function loadChat() {
        fetch("<?= base_url('anggota/get_chat.php') ?>")
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    let html = '';
                    if (data.chats.length === 0) {
                        html = '<p class="text-muted small text-center my-5">Belum ada informasi broadcast dari admin.</p>';
                    } else {
                        data.chats.forEach(chat => {
                            html += `
                                <div class="bg-white p-2 rounded shadow-sm mb-2">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                                        <span class="fw-bold small text-primary"><i class="bi bi-shield-check me-1"></i>${chat.username}</span>
                                        <small class="text-muted" style="font-size:0.75rem;">${chat.waktu}</small>
                                    </div>
                                    <p class="mb-0 small text-dark">${chat.pesan}</p>
                                </div>
                            `;
                        });
                    }
                    document.getElementById('chatContainer').innerHTML = html;
                }
            })
            .catch(err => console.error("Error polling chat:", err));
    }

    // Polling setiap 5 detik
    setInterval(loadChat, 5000);
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>