<?php

declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function requireAdmin(): void
{
    startAdminSession();

    $lastActivity = $_SESSION['admin_last_activity'] ?? 0;
    if (
        ($_SESSION['admin_authenticated'] ?? false) !== true ||
        !is_int($lastActivity) ||
        time() - $lastActivity > 1800
    ) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin_login.php');
        exit;
    }

    $_SESSION['admin_last_activity'] = time();
}

function adminCsrfToken(): string
{
    startAdminSession();

    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}
