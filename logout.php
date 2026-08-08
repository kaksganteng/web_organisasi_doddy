<?php
require_once 'config/functions.php';

if (isset($_SESSION['user_id'])) {
    // Log Logout
    $log_stmt = $db->prepare("INSERT INTO activity_log (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
    $log_stmt->execute([$_SESSION['user_id'], 'User logout dari sistem', $_SERVER['REMOTE_ADDR']]);
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: login.php");
exit;