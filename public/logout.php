<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requirePost();

if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$auth->logout();

header('Location: login.php');
exit;