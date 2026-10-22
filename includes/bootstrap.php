<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

$isHttps = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$auth = new Auth($pdo);

/**
 * Require an authenticated session.
 */
function requireLogin(Auth $auth): void
{
    if (!$auth->check()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Escape values before rendering HTML.
 */
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Ensure a request uses POST.
 */
function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

/**
 * Send a JSON response and terminate the request.
 */
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data);
    exit;
}

/**
 * Admin User Access
 */
function requireAdmin(Auth $auth): void
{
    requireLogin($auth);

    $user = $auth->user();

    if (($user['role'] ?? null) !== 'admin') {
        http_response_code(403);
        exit('403 Forbidden: Administrator access required.');
    }
}