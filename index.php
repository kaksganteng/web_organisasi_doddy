<?php
require_once 'config/functions.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: anggota/dashboard.php");
    }
    exit;
} else {
    header("Location: login.php");
    exit;
}