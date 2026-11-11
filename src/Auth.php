<?php
/**
 * src/Auth.php — autentikasi admin sederhana (sesi PHP).
 */
declare(strict_types=1);

namespace App;

class Auth
{
    public static function user(): string
    {
        return (string)($_SESSION['admin_user'] ?? '');
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            $login = (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '/admin/login.php', 'login.php'))
                ? 'login.php' : 'admin/login.php';
            header('Location: ' . $login);
            exit;
        }
    }

    public static function login(string $username, string $password): bool
    {
        $expectedUser = env('ADMIN_USER', 'admin');
        $expectedHash = env('ADMIN_PASS_HASH', '');
        if ($username !== $expectedUser || $expectedHash === '') {
            return false;
        }
        if (!password_verify($password, $expectedHash)) {
            return false;
        }
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        $_SESSION['admin_login_at'] = time();
        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
}
