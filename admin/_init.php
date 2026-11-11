<?php
/**
 * admin/_init.php — bootstrap untuk halaman admin.
 */
declare(strict_types=1);

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Booking.php';
require __DIR__ . '/../src/Calendar.php';

use App\{Auth, Db, Booking};

session_start();

if (!isset($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminCsrfInput(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['admin_csrf'] ?? '') . '">';
}

function requireAdmin(): void
{
    Auth::requireLogin();
}
