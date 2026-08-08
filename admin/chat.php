<?php
require_once __DIR__ . '/../config/functions.php';
check_role(['admin']);

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg   = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

$current_admin_id = $_SESSION['user_id'] ?? 1;

// ==================================================================
// 1. PROSES POST (KIRIM PESAN & HAPUS PESAN)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error_msg'] = "Sesi keamanan tidak valid, silakan coba lagi.";
        header("Location: chat.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. KIRIM PESAN BARU ---
    if ($action === 'send') {
        $pesan = trim($_POST['pesan'] ?? '');

        if (empty($pesan)) {
            $_SESSION['error_msg'] = "Pesan tidak boleh kosong!";
            header("Location: chat.php");
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO chat (id_admin, pesan, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$current_admin_id, $pesan]);
        } catch (Exception $ex) {
            $_SESSION['error_msg'] = "Gagal mengirim pesan: " . $ex->getMessage();
        }

        header("Location: chat.php");
        exit;
    }

    // --- B. HAPUS PESAN ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM chat WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "Pesan berhasil dihapus.";
            } catch (Exception $ex) {
                $_SESSION['error_msg'] = "Gagal menghapus pesan: " . $ex->getMessage();
            }
        }

        header("Location: chat.php");
        exit;
    }
}

// ==================================================================
// 2. FETCH DATA PESAN CHAT
// ==================================================================
try {
    // Mencoba JOIN ke tabel admin / users jika ada kolom nama
    $sql = "
        SELECT c.*, COALESCE(a.nama_lengkap, a.username, 'Admin') AS nama_pengirim 
        FROM chat c 
        LEFT JOIN admin a ON c.id_admin = a.id 
        ORDER BY c.created_at ASC
    ";
    $stmt = $db->query($sql);
    $list_chat = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback jika struktur tabel admin tidak terdeteksi
    $stmt = $db->query("SELECT c.*, 'Admin' AS nama_pengirim FROM chat c ORDER BY c.created_at ASC");
    $list_chat = $stmt->fetchAll();
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-chat-dots-fill text-primary me-2"></i>Diskusi Internal Admin</h4>
        <p class="text-muted small mb-0">Ruang obrolan langsung antar pengelola dan pengurus sistem.</p>
    </div>
</div>

<!-- NOTIFIKASI ALERTS -->
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

<!-- CARD CHAT WRAPPER -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
    <!-- CHAT HEADER -->
    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-white text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-people-fill fs-5"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold">Grup Diskusi Admin</h6>
                <small class="text-white-50"><i class="bi bi-circle-fill text-success fs-6 me-1" style="font-size: 8px !important;"></i>Aktif</small>
            </div>
        </div>
        <span class="badge bg-white text-primary fw-semibold"><?= count($list_chat) ?> Pesan</span>
    </div>

    <!-- CHAT BODY (SCROLLABLE AREA) -->
    <div class="card-body p-4 bg-light" id="chatContainer" style="height: 480px; overflow-y: auto;">
        <?php if (empty($list_chat)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-text display-3 d-block mb-3 text-secondary opacity-50"></i>
                <p class="mb-0">Belum ada obrolan. Mulailah pesan pertama di bawah ini!</p>
            </div>
        <?php else: ?>
            <?php foreach ($list_chat as $msg): ?>
                <?php 
                    $is_me = ($msg['id_admin'] == $current_admin_id); 
                ?>
                <div class="d-flex mb-3 <?= $is_me ? 'justify-content-end' : 'justify-content-start' ?>">
                    <div style="max-width: 75%;">
                        <!-- Sender Name & Time -->
                        <div class="d-flex align-items-center mb-1 <?= $is_me ? 'justify-content-end' : 'justify-content-start' ?>">
                            <small class="fw-bold text-dark me-2"><?= $is_me ? 'Anda' : e($msg['nama_pengirim']) ?></small>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                            </small>
                        </div>

                        <!-- Chat Bubble -->
                        <div class="position-relative p-3 rounded-3 shadow-sm <?= $is_me ? 'bg-primary text-white rounded-bottom-right-0' : 'bg-white text-dark border rounded-bottom-left-0' ?>">
                            <div class="lh-base" style="white-space: pre-line; word-break: break-word;">
                                <?= e($msg['pesan']) ?>
                            </div>

                            <!-- Delete Option -->
                            <?php if ($is_me): ?>
                                <div class="text-end mt-1">
                                    <button type="button" class="btn btn-link btn-sm text-white-50 p-0 text-decoration-none border-0" data-bs-toggle="modal" data-bs-target="#modalHapusMsg<?= $msg['id'] ?>" title="Hapus pesan">
                                        <i class="bi bi-trash small"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- MODAL HAPUS PESAN -->
                <?php if ($is_me): ?>
                    <div class="modal fade" id="modalHapusMsg<?= $msg['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $msg['id'] ?>">

                                    <div class="modal-body text-center p-3">
                                        <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>
                                        <p class="small mb-3">Hapus pesan ini?</p>
                                        <div class="d-flex justify-content-center gap-2">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- CHAT FOOTER (INPUT PESAN) -->
    <div class="card-footer bg-white p-3 border-top">
        <form method="POST" action="" id="formSendChat">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="send">

            <div class="input-group">
                <textarea name="pesan" id="inputPesan" class="form-control border-end-0" rows="1" placeholder="Tulis pesan diskusi..." required style="resize: none;"></textarea>
                <button type="submit" class="btn btn-primary px-4 fw-semibold d-flex align-items-center gap-1">
                    <i class="bi bi-send-fill"></i> <span class="d-none d-sm-inline">Kirim</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================== -->
<!-- SCRIPT AUTO-SCROLL KE PESAN TERBARU                                 -->
<!-- ================================================================== -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    var chatContainer = document.getElementById("chatContainer");
    if (chatContainer) {
        // Scroll otomatis ke bagian paling bawah
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Submit dengan kombinasi tombol Enter (Shift+Enter untuk baris baru)
    var inputPesan = document.getElementById("inputPesan");
    var formSendChat = document.getElementById("formSendChat");

    if (inputPesan && formSendChat) {
        inputPesan.addEventListener("keydown", function (e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                if (inputPesan.value.trim() !== "") {
                    formSendChat.submit();
                }
            }
        });
    }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>