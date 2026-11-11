<?php
declare(strict_types=1);
require __DIR__ . '/_header.php';

use App\Calendar;

$calendar = new Calendar();
$connected = $calendar->isConnected();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['admin_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sesi tidak valid.';
    } else {
        try {
            if ($_POST['action'] === 'connect') {
                $state = bin2hex(random_bytes(32));
                $_SESSION['oauth_state'] = $state;
                header('Location: ' . $calendar->connectUrl($state));
                exit;
            } elseif ($_POST['action'] === 'disconnect') {
                $calendar->disconnect();
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: calendar.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// OAuth callback dari Google
if (isset($_GET['code'])) {
    $state = (string)($_GET['state'] ?? '');
    if (!hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
        $error = 'OAuth state tidak valid. Silakan coba hubungkan ulang.';
    } else {
        try {
            unset($_SESSION['oauth_state']);
            $code = (string)($_GET['code'] ?? '');
            if ($code === '') {
                throw new RuntimeException('Kode otorisasi tidak diterima dari Google.');
            }
            $token = $calendar->exchangeCode($code);
            $calendar->saveToken($token);
            header('Location: calendar.php?connected=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan token: ' . $e->getMessage();
        }
    }
}
if (isset($_GET['error'])) {
    $error = 'Google OAuth dibatalkan: ' . h((string)$_GET['error']);
}
if (isset($_GET['connected'])) {
    $message = 'Google Calendar berhasil terhubung.';
}
?>
<h2>Google Calendar</h2>
<p class="sub">Sinkronisasi ketersediaan dan event booking dengan kalender bisnis Google.</p>

<div class="card">
<h3 style="margin:0 0 10px">Status</h3>
<?php if ($connected): ?>
<div class="alert ok">Google Calendar <strong>terhubung</strong> sebagai kalender admin.</div>
<form method="post" style="display:inline">
<?= adminCsrfInput() ?>
<input type="hidden" name="action" value="disconnect">
<button class="btn danger small" type="submit" onclick="return confirm('Putuskan koneksi Google Calendar?')">Putuskan Koneksi</button>
</form>
<?php else: ?>
<div class="alert err">Google Calendar <strong>belum terhubung</strong>.</div>
<form method="post" style="margin-top:10px">
<?= adminCsrfInput() ?>
<input type="hidden" name="action" value="connect">
<button class="btn primary" type="submit">Hubungkan Google Calendar</button>
</form>
<p class="muted" style="margin-top:8px">Setelah klik, Anda akan diarahkan ke Google untuk memberikan izin akses kalender.</p>
<?php endif; ?>

<?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="alert ok"><?= h($message) ?></div><?php endif; ?>

<div class="card">
<h3 style="margin:0 0 10px">Pengaturan kalender</h3>
<p class="muted">ID kalender: <code><?= h(env('GOOGLE_CALENDAR_ID', 'primary')) ?></code></p>
<p class="muted">Token disimpan di: <code><?= h(env('GOOGLE_ADMIN_TOKEN_FILE', APP_ROOT . '/storage/booking-admin-token.json')) ?></code></p>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
