<div id="top" style="display: none;">

# PHP PDO Authentication System

A reusable PHP authentication system built with PHP, PDO, MySQL/MariaDB, Bootstrap 5, and the Fetch API. Includes user registration, login, logout, session management, CSRF protection, password hashing, server-side validation, and protected dashboard pages.

## Table of Contents

1. [Features](#features)
2. [Project Structure](#project_structure)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Authentication Workflow](#authentication_workflow)
6. [Class Reference](#class_reference)
7. [Troubleshooting](#troubleshooting)

## Features

- User registration with username, email, and password validation.
- User login using either username or email address.
- Secure password storage using PHP's password_hash() and password_verify().
- PDO database access using prepared statements.
- CSRF protection for registration, login, and logout requests.
- Session security with session ID regeneration after login and HTTP-only cookies.
- Bootstrap 5 forms with responsive layouts and inline validation feedback.
- Fetch API integration for asynchronous form submissions using FormData.
- JSON responses for consistent frontend/backend communication.
- Protected dashboard requiring an authenticated session.
- Role field for future role-based access control.
- Environment-based configuration for database credentials.

[Back to Top](#top)

## Project Structure

auth-app/
├── config/
│ └── database.php
├── classes/
│ └── Auth.php
├── includes/
│ └── bootstrap.php
├── public/
│ ├── index.php
│ ├── signup.php
│ ├── login.php
│ ├── dashboard.php
│ ├── logout.php
│ └── assets/
│ └── auth.js
└── database.sql

### Directories

- `config/` — PDO connection and database configuration.
- `classes/` — Reusable authentication methods.
- `includes/` — Shared initialization, session handling, and helper functions.
- `public/` — Public entry points, authentication forms, and dashboard.
- `public/assets/` — Frontend JavaScript.
- `database.sql` — Database and user table schema.

[Back to Top](#top)

## Requirements

- PHP 8.1 or later.
- MySQL 8.0+ or a compatible MariaDB version.
- PHP PDO extension.
- PHP PDO MySQL driver (pdo_mysql).
- A web server such as Apache or Nginx, or PHP's built-in development server.
- A modern web browser with JavaScript enabled.
- Internet access to load Bootstrap from the CDN, unless Bootstrap is hosted locally.

Check the installed PHP version and extensions:

```bash
php -v
php -m | grep -E 'PDO|pdo_mysql'
```

[Back to Top](#top)

## Installation

1. Clone the Repository

```bash
git clone https://github.com/ryanc410/secure-signup-login.git
cd secure-signup-login
```

2. Create the Database
   Import the Database schema:

```bash
mysql -u root -p < database.sql
```

This creates the auth_app database and the users table.

The table includes:

| Column        | Description                 |
| ------------- | --------------------------- |
| id            | Auto-incrementing user ID   |
| username      | Unique username             |
| email         | Unique Email Address        |
| password_hash | Hashed Password             |
| role          | User role, defaults to user |
| created_at    | Account creation timestamp  |
| updated_at    | Last update timestamp       |

3. Create Database User

Connect to MySQL/MariaDB as an administrator:

```sql
CREATE USER 'auth_app_user'@'localhost'
IDENTIFIED BY 'replace-with-a-strong-password';

GRANT SELECT, INSERT, UPDATE, DELETE
ON auth_app.*
TO 'auth_app_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

Use a unique, strong password. Store it securely outside the public web directory. To create a strong password use this snip:

```bash
openssl rand -base64 32
```

4. Configure Environment Variables

The application reads the following variables:

| Variable | Example                |
| -------- | ---------------------- |
| DB_HOST  | 127.0.0.1              |
| DB_NAME  | auth_app               |
| DB_USER  | auth_app_user          |
| DB_PASS  | Your database password |

For local development on linux:

```bash
export DB_HOST="127.0.0.1"
export DB_NAME="auth_app"
export DB_USER="auth_app_user"
export DB_PASS="your-database-password"
```

These exports apply to the current shell and processes started from it. Configure the variables in your web server or PHP-FPM service environment when deploying the application.

**Do not commit database passwords to version control.**

4. Start the Development Server

From the Project Root, run:

```php
php -S 127.0.0.1:8000 -t public
```

Open the application:

- Application: http://127.0.0.1:8000/
- Login: http://127.0.0.1:8000/login.php
- Signup: http://127.0.0.1:8000/signup.php
- Dashboard: http://127.0.0.1:8000/dashboard.php

[Back to Top](#top)

## Authentication Workflow

1. Registration

- The user submits the signup form.
- JavaScript performs basic browser-side validation.
- Fetch submits the form using FormData.
- PHP validates the CSRF token.
- The authentication class validates the username, email, and password.
- The password is hashed.
- PDO inserts the new account into the database.
- PHP returns a JSON response.
- JavaScript displays success or validation feedback.

2. Login

- The user enters a username or email address and password.
- JavaScript submits the form asynchronously.
- PHP validates the CSRF token.
- PDO retrieves the matching user account.
- PHP verifies the submitted password against the stored hash.
- PHP regenerates the session ID.
- The authenticated user's ID and permitted session data are stored in the session.
- A new CSRF token is generated.
- The browser redirects to the dashboard.

3. Logout

- The user submits the logout form using POST.
- PHP validates the CSRF token.
- The authentication class clears session data and expires the session cookie.
- The session is destroyed.
- The user is redirected to the login page.

[Back to Top](#top)

## Class Reference

The `Auth` class provides the following methods:

| Method                                   | Description                                     |
| ---------------------------------------- | ----------------------------------------------- |
| `csrfToken()`                            | Generates or retrieves a CSRF token.            |
| `verifyCsrf($token)`                     | Validates a submitted CSRF token.               |
| `userExists($username, $email)`          | Checks whether a username or email exists.      |
| `register($username, $email, $password)` | Validates and registers a new user.             |
| `login($username, $password)`            | Authenticates a user and establishes a session. |
| `user()`                                 | Returns the authenticated user's session data.  |
| `check()`                                | Checks whether the user is authenticated.       |
| `logout()`                               | Clears and destroys the current session.        |

[Back to Top](#top)

## Troubleshooting

### Database connection errors

Check that:

- MySQL/MariaDB is running.
- The database exists.
- The configured username and password are correct.
- The database user has the necessary privileges.
- `pdo_mysql` is installed and enabled.
- The environment variables are available to PHP.

### Fetch requests fail

Check the browser's developer tools, especially the Network and Console tabs. Verify the form action, HTTP status code, response body, and PHP logs.

### CSRF validation fails

Ensure the session cookie is being sent and that the submitted form contains the current CSRF token. Check for mismatched hostnames, session expiration, or incorrect cookie settings.

### Login succeeds but the dashboard redirects

Verify that session cookies are enabled and that PHP is using the same session configuration across requests. When running behind a reverse proxy, ensure HTTPS and cookie settings are configured correctly.

[Back to Top](#top)

## Authors

Ryan Cook
[Send Me an Email](ryanhtown713@outlook.com)

[Back to Top](#top)
