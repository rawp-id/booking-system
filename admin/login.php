<?php
/**
 * admin/login.php — login admin.
 */
declare(strict_types=1);

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../src/Auth.php';
use App\Auth;

session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: index.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk Admin — <?= htmlspecialchars(env('APP_NAME', 'Booking Appointment')) ?></title>
<style>
:root{--ink:#1a1a1a;--accent:#252420;--line:#e5e5e5;--bg:#fafafa}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 system-ui,-apple-system,Segoe UI,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:32px;width:100%;max-width:360px;box-shadow:0 8px 30px #00000008}
h2{font-size:22px;margin:0 0 8px}
.sub{color:#6b6b6b;font-size:14px;margin-bottom:24px}
.alert{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#fff0ef;color:#b00020;font-size:14px}
.field{margin-bottom:16px}
label{display:block;font-weight:600;font-size:13px;margin-bottom:6px}
input{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:8px;font:inherit}
.btn{width:100%;padding:12px;border:0;border-radius:10px;background:var(--accent);color:#fff;font:inherit;font-weight:700;cursor:pointer}
.footer{margin-top:20px;text-align:center;font-size:12px;color:#6b6b6b}
</style>
</head>
<body>
<div class="card">
<h2>Masuk Admin</h2>
<p class="sub">Kelola booking, layanan, dan kalender bisnis.</p>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<div class="field">
<label for="username">Username</label>
<input id="username" name="username" autocomplete="username" required>
</div>
<div class="field">
<label for="password">Password</label>
<input id="password" name="password" type="password" autocomplete="current-password" required>
</div>
<button class="btn" type="submit">Masuk</button>
</form>
<div class="footer">
Default: admin / admin123 (ubah di .env)
</div>
</div>
</body>
</html>
