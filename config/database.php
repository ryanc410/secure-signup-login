<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'auth_app';
$user = getenv('DB_USER') ?: 'db_admin';
$pass = getenv('DB_PASS') ?: '01R30D88C';

if ($pass === false) {
    throw new RuntimeException('DB_PASS is not configured.');
}

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);