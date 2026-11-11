<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
requireAdmin();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — <?= h(env('APP_NAME', 'Booking Appointment')) ?></title>
<style>
:root{--ink:#1a1a1a;--muted:#6b6b6b;--line:#e5e5e5;--bg:#fafafa;--accent:#252420;--ok:#edf7ee;--danger:#fff0ef}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}
.topbar{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
.topbar .inner{max-width:1100px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:24px}
.topbar .brand{font-weight:700;font-size:18px;text-decoration:none;color:var(--ink)}
.topbar .brand span{color:var(--accent)}
.topbar .nav{margin-left:auto;display:flex;gap:16px;align-items:center}
.topbar a{padding:8px 12px;border-radius:8px;text-decoration:none;color:var(--ink);font-weight:600;font-size:14px}
.topbar a:hover{background:#f0f0f0}
.topbar a.active{background:var(--accent);color:#fff}
.topbar a.logout{color:#b00020}
.container{max-width:1100px;margin:24px auto;padding:0 20px}
h2{font-size:22px;margin:0 0 8px}
.sub{color:var(--muted);margin:0 0 20px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:20px;box-shadow:0 4px 16px #00000006}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.stat{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px}
.stat .v{font-size:30px;font-weight:700;color:var(--accent)}
.stat .l{color:var(--muted);font-size:13px;margin-top:4px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
th{font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);background:#f7f7f7}
tr:hover{background:#fcfcfc}
.btn{display:inline-block;padding:8px 14px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}
.btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn.danger{color:#b00020;border-color:#b00020}
.btn.small{padding:6px 10px;font-size:12px}
.btn:disabled{opacity:.5;cursor:not-allowed}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
label{display:block;font-size:13px;font-weight:600;margin-bottom:5px;color:var(--ink)}
input,select,textarea{width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:8px;font:inherit}
.muted{color:var(--muted);font-size:13px}
.alert{padding:12px 14px;border-radius:10px;margin-bottom:16px}
.alert.ok{background:var(--ok);color:#235b2b}
.alert.err{background:var(--danger);color:#b00020}
.empty{padding:18px;color:var(--muted);text-align:center;border:1px dashed var(--line);border-radius:10px}
.badge{display:inline-block;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700;text-transform:uppercase}
.badge.confirmed{background:#e3f2e3;color:#235b2b}
.badge.cancelled{background:#fdecea;color:#b00020}
</style>
</head>
<body>
<div class="topbar"><div class="inner">
<a class="brand" href="index.php"><?= h(env('APP_NAME', 'Booking Appointment')) ?> <span>Admin</span></a>
<div class="nav">
<a href="index.php" class="navlink <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : '' ?>">Dashboard</a>
<a href="bookings.php" class="navlink <?= basename($_SERVER['SCRIPT_NAME']) === 'bookings.php' ? 'active' : '' ?>">Booking</a>
<a href="services.php" class="navlink <?= basename($_SERVER['SCRIPT_NAME']) === 'services.php' ? 'active' : '' ?>">Layanan</a>
<a href="calendar.php" class="navlink <?= basename($_SERVER['SCRIPT_NAME']) === 'calendar.php' ? 'active' : '' ?>">Kalender</a>
<a href="logout.php" class="navlink logout">Keluar</a>
</div>
</div></div>
<div class="container">
