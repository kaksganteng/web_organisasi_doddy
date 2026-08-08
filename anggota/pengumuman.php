<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['anggota']);

// Fetch announcements from database, joined with users table for admin username
$sql = "
    SELECT p.*, u.username 
    FROM pengumuman p 
    LEFT JOIN users u ON p.id_admin = u.id 
    ORDER BY p.created_at DESC
";
$stmt = $db->query($sql);
$list_pengumuman = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-megaphone-fill text-primary me-2"></i>Pengumuman Organisasi</h4>
        <p class="text-muted small mb-0">Informasi, instruksi, dan berita penting yang dibagikan oleh pengurus atau admin.</p>
    </div>
</div>

<!-- ANNOUNCEMENTS LIST -->
<div class="row g-4">
    <?php if (empty($list_pengumuman)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-bell-slash display-4 mb-3 text-secondary"></i>
                <h5>Belum ada pengumuman</h5>
                <p class="small mb-0">Pengumuman terbaru dari admin akan muncul di halaman ini.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($list_pengumuman as $row): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><?= e($row['judul']) ?></h5>
                                <div class="d-flex align-items-center gap-3 text-muted small">
                                    <span><i class="bi bi-person-fill me-1"></i><?= e($row['username'] ?? 'Admin') ?></span>
                                    <span><i class="bi bi-clock-fill me-1"></i><?= date('d M Y, H:i', strtotime($row['created_at'])) ?> WIB</span>
                                </div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fw-semibold">
                                <i class="bi bi-megaphone me-1"></i> Pengumuman
                            </span>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="text-secondary mb-3" style="white-space: pre-line; line-height: 1.6;">
                            <?= nl2br(e($row['isi'])) ?>
                        </div>

                        <?php if (!empty($row['lampiran'])): ?>
                            <div class="mt-3 p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark-text-fill text-primary fs-4"></i>
                                    <div>
                                        <span class="fw-semibold small d-block">Lampiran Berkas</span>
                                        <span class="text-muted small"><?= e($row['lampiran']) ?></span>
                                    </div>
                                </div>
                                <a href="../uploads/pengumuman/<?= e($row['lampiran']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download me-1"></i> Unduh / Lihat
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>