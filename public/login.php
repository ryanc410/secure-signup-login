<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();

    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        jsonResponse([
            'success' => false,
            'message' => 'Your session has expired. Refresh and try again.',
        ], 403);
    }

    $result = $auth->login(
        (string) ($_POST['username'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );

    jsonResponse($result, $result['success'] ? 200 : 401);
}

if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$csrfToken = $auth->csrfToken();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-body-tertiary">

  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 p-md-5">
            <h1 class="h3 text-center mb-2">Welcome back</h1>
            <p class="text-secondary text-center mb-4">
              Sign in to your account.
            </p>

            <div id="formMessage" role="alert" aria-live="polite"></div>

            <form id="loginForm" action="login.php" method="post" novalidate>

              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

              <div class="mb-3">
                <label for="username" class="form-label">
                  Username or email
                </label>
                <input type="text" class="form-control" id="username" name="username" maxlength="254"
                  autocomplete="username" required>
                <div class="invalid-feedback">
                  Enter your username or email.
                </div>
              </div>

              <div class="mb-4">
                <label for="password" class="form-label">
                  Password
                </label>
                <input type="password" class="form-control" id="password" name="password"
                  autocomplete="current-password" required>
                <div class="invalid-feedback">
                  Enter your password.
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                Sign in
              </button>
            </form>

            <p class="text-center mt-4 mb-0">
              Need an account?
              <a href="signup.php">Create one</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="assets/auth.js" defer></script>
</body>

</html>