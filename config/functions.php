<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Global PDO Connection Instance
$db = (new Database())->getConnection();

/**
 * Escaping XSS Helper
 */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF Token
 */
function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Auth Guard Check
 */
function check_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . base_url('login.php'));
        exit;
    }
}

/**
 * Role Access Middleware
 */
function check_role(array $allowed_roles): void {
    check_login();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: " . base_url($_SESSION['role'] . '/dashboard.php'));
        exit;
    }
}

/**
 * Dynamic Base URL
 */
function base_url(string $path = ''): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_dir = rtrim(dirname($script_name), '/');
    return $protocol . "://" . $host . $base_dir . '/' . ltrim($path, '/');
}

/**
 * Generator Password Random (6 Karakter Alfanumerik)
 */
function generate_random_password(int $length = 6): string {
    $chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($chars, 5)), 0, $length);
}

/**
 * Format Rupiah
 */
function format_rupiah(float $nominal): string {
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

/**
 * Format Tanggal Indonesia
 */
function format_tanggal(string $date): string {
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    return "$day $month $year";
}