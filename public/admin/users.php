<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

requireAdmin($auth);

$currentUser = $auth->user();

// Confirm the administrator's role directly against the database.
$stmt = $pdo->prepare(
    'SELECT role FROM users WHERE id = :id'
);
$stmt->execute(['id' => $currentUser['id']]);

if ($stmt->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('403 Forbidden');
}

if (!isset($_SESSION['admin_flash'])) {
    $_SESSION['admin_flash'] = null;
}

/**
 * Redirect after processing a POST request.
 */
function adminRedirect(): never
{
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $targetId = filter_var(
        $_POST['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$targetId) {
        $_SESSION['admin_flash'] = [
            'type' => 'danger',
            'message' => 'Invalid user ID.',
        ];
        adminRedirect();
    }

    if ((int) $targetId === (int) $currentUser['id']) {
        $_SESSION['admin_flash'] = [
            'type' => 'warning',
            'message' => 'You cannot change or delete your own account here.',
        ];
        adminRedirect();
    }

    try {
        if ($action === 'change_role') {
            $role = $_POST['role'] ?? '';

            if (!in_array($role, ['user', 'admin'], true)) {
                throw new InvalidArgumentException('Invalid role.');
            }

            $pdo->beginTransaction();

            // Lock the user table's existing rows while checking
            // the number of administrators. Requires InnoDB.
            $pdo->query(
                'SELECT id FROM users ORDER BY id FOR UPDATE'
            )->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT id FROM users WHERE id = :id'
            );
            $stmt->execute(['id' => $targetId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('User not found.');
            }

            $stmt = $pdo->prepare(
                'SELECT role FROM users WHERE id = :id'
            );
            $stmt->execute(['id' => $targetId]);
            $oldRole = $stmt->fetchColumn();

            if ($oldRole === 'admin' && $role !== 'admin') {
                $adminCount = (int) $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
                )->fetchColumn();

                if ($adminCount <= 1) {
                    throw new RuntimeException(
                        'The last administrator cannot be demoted.'
                    );
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE users SET role = :role WHERE id = :id'
            );
            $stmt->execute([
                'role' => $role,
                'id' => $targetId,
            ]);

            $pdo->commit();

            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'User role updated.',
            ];

        } elseif ($action === 'delete_user') {
            $pdo->beginTransaction();

            $pdo->query(
                'SELECT id FROM users ORDER BY id FOR UPDATE'
            )->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT role FROM users WHERE id = :id'
            );
            $stmt->execute(['id' => $targetId]);
            $targetRole = $stmt->fetchColumn();

            if ($targetRole === false) {
                throw new RuntimeException('User not found.');
            }

            if ($targetRole === 'admin') {
                $adminCount = (int) $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
                )->fetchColumn();

                if ($adminCount <= 1) {
                    throw new RuntimeException(
                        'The last administrator cannot be deleted.'
                    );
                }
            }

            $stmt = $pdo->prepare(
                'DELETE FROM users WHERE id = :id'
            );
            $stmt->execute(['id' => $targetId]);

            $pdo->commit();

            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'User account deleted.',
            ];

        } else {
            throw new InvalidArgumentException('Unknown action.');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Admin user management error: ' . $e->getMessage());

        $_SESSION['admin_flash'] = [
            'type' => 'danger',
            'message' => $e instanceof InvalidArgumentException
                || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The operation could not be completed.',
        ];
    }

    adminRedirect();
}

/**
 * Search and Pagination  
 */
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE username LIKE :username
              OR email LIKE :email';

    $term = '%' . $search . '%';
    $params = [
        'username' => $term,
        'email' => $term,
    ];
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users {$where}"
);
$countStmt->execute($params);

$totalUsers = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
  "SELECT id, username, email, role, created_at FROM users {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset"
);

foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}

$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();

$users = $listStmt->fetchAll();

$flash = $_SESSION['admin_flash'];
$_SESSION['admin_flash'] = null;

$csrfToken = $auth->csrfToken();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-body-tertiary">

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="../dashboard.php">Application Admin</a>

      <div class="d-flex gap-2">
        <a href="../dashboard.php" class="btn btn-outline-light btn-sm">
          Dashboard
        </a>
        <!-- Logout Button -->
        <form action="../logout.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <button class="btn btn-outline-light btn-sm">Sign out</button>
        </form>
      </div>
    </div>
  </nav>

  <main class="container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">User Management</h1>
        <p class="text-secondary mb-0">Search accounts and manage user permissions.</p>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body py-3">
          <span class="text-secondary">Registered users</span>
          <div class="fs-4 fw-semibold">
            <?= number_format($totalUsers) ?>
          </div>
        </div>
      </div>
    </div>

    <?php 
    /**
     * Div Container for flash messages
     */
    if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
      <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <!-- User Search Box -->
        <form method="get" action="users.php" class="row g-2 mb-4">
          <div class="col-sm-8 col-md-5">
            <label for="search" class="visually-hidden">Search users</label>
            <input type="search" id="search" name="search" class="form-control" placeholder="Search username or email"
              value="<?= e($search) ?>">
          </div>
          <div class="col-auto">
            <button class="btn btn-primary">Search</button>
            <a href="users.php" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registered</th>
                <th style="min-width: 260px">Actions</th>
              </tr>
            </thead>

            <tbody>

              <?php 
              /**
               * If the database contains no Users show a message
               */
              if (!$users): ?>
              <tr>
                <td colspan="6" class="text-center text-secondary py-4">No users found.</td>
              </tr>
              <?php endif; ?>

              <?php foreach ($users as $user): ?>
              <tr>
                <td><?= (int) $user['id'] ?></td>
                <td><?= e($user['username']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td>
                  <span class="badge text-bg-<?= 
                                    $user['role'] === 'admin'
                                        ? 'primary'
                                        : 'secondary'
                                ?>">
                    <?= e($user['role']) ?>
                  </span>
                </td>
                <td>
                  <?= e($user['created_at']) ?>
                </td>
                <td>
                  <?php if (
                                    (int) $user['id']
                                    === (int) $currentUser['id']
                                ): ?>
                  <span class="text-secondary small">
                    Current account
                  </span>
                  <?php else: ?>
                  <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="users.php" class="d-flex gap-1">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="action" value="change_role">
                      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">

                      <select name="role" class="form-select form-select-sm"
                        aria-label="Role for <?= e($user['username']) ?>">
                        <option value="user" <?= $user['role'] === 'user'
                                                        ? 'selected' : '' ?>>
                          User
                        </option>
                        <option value="admin" <?= $user['role'] === 'admin'
                                                        ? 'selected' : '' ?>>
                          Admin
                        </option>
                      </select>

                      <button type="submit" class="btn btn-sm btn-outline-primary">
                        Save
                      </button>
                    </form>

                    <form method="post" action="users.php"
                      onsubmit="return confirm('Permanently delete this user account?');">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">

                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        Delete
                      </button>
                    </form>
                  </div>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mt-3">
          <span class="text-secondary small">
            Page <?= $page ?> of <?= $totalPages ?>
          </span>

          <nav aria-label="User list pagination">
            <ul class="pagination pagination-sm mb-0">
              <?php
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                        ?>

              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query([
                                   'search' => $search,
                                   'page' => $prev,
                               ])) ?>">
                  Previous
                </a>
              </li>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query([
                                   'search' => $search,
                                   'page' => $next,
                               ])) ?>">
                  Next
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </main>

</body>

</html>