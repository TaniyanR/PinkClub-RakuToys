<?php

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf'];
}

function verify_csrf(): void
{
    start_secure_session();
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
}

function admin_logged_in(): bool
{
    start_secure_session();
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function login_admin(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id,password_hash FROM admins WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, (string)$admin['password_hash'])) return false;
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    return true;
}

function setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function visitor_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return hash('sha256', date('Y-m-d') . '|' . $ip . '|' . $ua . '|' . (string)app_config('app.name'));
}

function record_page_view(PDO $pdo): void
{
    if (PHP_SAPI === 'cli') return;
    $stmt = $pdo->prepare('INSERT INTO page_views(path,visitor_hash,referrer,user_agent) VALUES(?,?,?,?)');
    $stmt->execute([
        mb_substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 2048),
        visitor_hash(),
        mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 2048),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
    ]);
}
