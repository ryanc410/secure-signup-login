<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin($auth);

$user = $auth->user();
$csrfToken = $auth->csrfToken();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-body-tertiary">

  <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
    <div class="container">
      <a class="navbar-brand" href="dashboard.php">My Application</a>
      <form action="logout.php" method="post" class="ms-auto">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <button class="btn btn-outline-light btn-sm" type="submit">
          Sign out
        </button>
      </form>
    </div>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
    <a href="admin/users.php" class="btn btn-outline-primary btn-sm">Manage Users</a>
    <?php endif; ?>
  </nav>

  <main class="container py-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3">Dashboard</h1>

        <p>
          Welcome,
          <strong><?= e($user['username']) ?></strong>.
        </p>

        <p class="mb-0">
          Email: <?= e($user['email']) ?>
        </p>
      </div>
    </div>
  </main>

</body>

</html>