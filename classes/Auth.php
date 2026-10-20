<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Generate or retrieve the CSRF token.
     */
    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted CSRF token.
     */
    public function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check whether a username or email already exists.
     */
    public function userExists(
        string $username,
        string $email
    ): bool {
        $sql = 'SELECT id FROM users
                WHERE username = :username
                   OR email = :email
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'email' => $email,
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Register a new user.
     */
    public function register(
        string $username,
        string $email,
        string $password
    ): array {
        $username = trim($username);
        $email = trim($email);

        $errors = [];

        if (!preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', $username)) {
            $errors['username'] =
                'Use 3–50 letters, numbers, or underscores.';
        }

        if (
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (
            strlen($password) < 15 ||
            strlen($password) > 72
        ) {
            $errors['password'] =
                'Password must be 15–72 bytes long.';
        }

        if ($errors) {
            return [
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $errors,
            ];
        }

        try {
            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sql = 'INSERT INTO users
                    (username, email, password_hash)
                    VALUES (:username, :email, :password_hash)';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $hash,
            ]);

            return [
                'success' => true,
                'message' => 'Account created. You can now sign in.',
            ];
        } catch (PDOException $e) {
            // MySQL duplicate entry error.
            if (
                $e->getCode() === '23000' &&
                (int) ($e->errorInfo[1] ?? 0) === 1062
            ) {
                return [
                    'success' => false,
                    'message' => 'Username or email is already registered.',
                ];
            }

            throw $e;
        }
    }

    /**
     * Authenticate a user and establish a session.
     */
    public function login(
        string $username,
        string $password
    ): array {
        $username = trim($username);

        if (
            $username === '' ||
            strlen($username) > 254 ||
            $password === '' ||
            strlen($password) > 4096
        ) {
            return [
                'success' => false,
                'message' => 'Invalid username/email or password.',
            ];
        }

        $sql = 'SELECT id, username, email, password_hash, role
                FROM users
                WHERE username = :username
                   OR email = :email
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'email' => $username,
        ]);

        $user = $stmt->fetch();

        if (
            !$user ||
            !password_verify($password, $user['password_hash'])
        ) {
            return [
                'success' => false,
                'message' => 'Invalid username/email or password.',
            ];
        }

        // Prevent session fixation after authentication.
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        // Rotate the CSRF token after login.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return [
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => 'dashboard.php',
        ];
    }

    /**
     * Return the currently authenticated user.
     */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check authentication status.
     */
    public function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /**
     * Log out the current user.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}