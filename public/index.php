<?php
/**
 * public/index.php — halaman booking pelanggan (publik).
 * - GET  : tampilkan form + slot tersedia
 * - POST : buat booking
 * - GET ?ajax=slots : daftar slot JSON
 */
declare(strict_types=1);

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/Booking.php';
require __DIR__ . '/../src/Calendar.php';

use App\{Db, Booking};

session_start();
if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
}

$tzName = env('APP_TIMEZONE', 'Asia/Jakarta');
$tz = new DateTimeZone($tzName);
$today = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$maxDate = (new DateTimeImmutable('today', $tz))->modify('+' . (int)env('MAX_DAYS_AHEAD', 60) . ' days')->format('Y-m-d');

$selectedDate = (string)($_GET['date'] ?? $_POST['date'] ?? $today);
$selectedService = (string)($_GET['service'] ?? $_POST['service'] ?? '');
$services = Booking::services();
if ($selectedService === '' && $services) {
    $selectedService = $services[0]['name'];
}

$error = '';
$notice = '';
$available = [];
$confirmation = $_SESSION['booking_confirmation'] ?? null;
unset($_SESSION['booking_confirmation']);

try {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'slots') {
        $date = (string)($_GET['date'] ?? '');
        $name = (string)($_GET['service'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['slots' => Booking::slotsByName($date, $name)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['booking_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sesi tidak valid. Muat ulang halaman.');
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $time = (string)($_POST['time'] ?? '');
        $service = (string)($_POST['service'] ?? '');

        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new RuntimeException('Nama harus 2–100 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email tidak valid.');
        }
        if (strlen($phone) < 8 || strlen($phone) > 25 || !preg_match('/^[0-9+().\s-]+$/', $phone)) {
            throw new RuntimeException('Nomor WhatsApp tidak valid.');
        }
        if (strlen($notes) > 1000) {
            throw new RuntimeException('Catatan maksimal 1000 karakter.');
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new RuntimeException('Jam booking tidak valid.');
        }
        $svc = Booking::serviceByName($service);
        if (!$svc) {
            throw new RuntimeException('Layanan tidak valid.');
        }

        $booking = Booking::create([
            'service_id' => $svc['id'],
            'service' => $svc['name'],
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes,
            'date' => $selectedDate,
            'time' => $time,
            'duration' => (int)$svc['duration'],
            'business_name' => env('APP_NAME', 'Booking Appointment'),
        ]);

        $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['booking_confirmation'] = [
            'id' => $booking['id'],
            'name' => $booking['customer_name'],
            'email' => $booking['customer_email'],
            'phone' => $booking['customer_phone'],
            'service' => $booking['service_name'],
            'date' => $booking['date'],
            'time' => $booking['time'],
            'google_admin_link' => $booking['google_admin_link'] ?? '',
        ];
        header('Location: index.php?booked=1');
        exit;
    }

    $available = Booking::slots($selectedDate, (int)(Booking::serviceByName($selectedService)['id'] ?? 0));
    if (isset($_GET['booked'])) {
        $notice = 'Booking berhasil! Silakan periksa email untuk konfirmasi.';
    }
} catch (Throwable $e) {
    if (isset($_GET['ajax'])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    $error = $e->getMessage();
}

function h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(env('APP_NAME', 'Booking Appointment')) ?></title>
<style>
:root{--ink:#252420;--muted:#77736e;--line:#e9e6e1;--bg:#f6f5f2;--accent:#252420;--ok:#edf7ee}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 system-ui,-apple-system,Segoe UI,sans-serif}
.wrap{max-width:700px;margin:36px auto;padding:0 17px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:28px;box-shadow:0 8px 30px #00000008}
h1{font-size:27px;line-height:1.2;margin:0 0 7px;letter-spacing:-.5px}
.sub{color:var(--muted);margin:0 0 23px}
.field{margin:16px 0}
label{display:block;font-weight:650;margin-bottom:7px}
input,select,textarea{width:100%;padding:12px 13px;border:1px solid #ddd9d4;border-radius:10px;background:#fff;color:var(--ink);font:inherit}
.slots{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.slot{position:relative}
.slot input{position:absolute;opacity:0;pointer-events:none}
.slot span{display:block;text-align:center;padding:10px 4px;border:1px solid #ddd9d4;border-radius:9px;cursor:pointer}
.slot input:checked+span{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn{display:block;width:100%;padding:13px 15px;border:0;border-radius:10px;background:var(--accent);color:#fff;text-align:center;text-decoration:none;font:inherit;font-weight:700;cursor:pointer;margin-top:10px}
.btn.light{background:#fff;color:var(--ink);border:1px solid var(--line)}
.btn:disabled{opacity:.45;cursor:not-allowed}
.alert{padding:13px 15px;border-radius:10px;margin:0 0 17px;background:var(--ok);color:#235b2b}
.alert.err{background:#fff0ef;color:#9b2d25}
.panel{padding:15px;border:1px solid var(--line);border-radius:12px;background:#faf9f7;margin:15px 0}
.small{font-size:13px;color:var(--muted)}
.empty{padding:14px;background:#f7f6f4;border-radius:10px;color:var(--muted)}
.confirm{padding:16px;background:#f1f7f0;border-radius:12px}
.confirm p{margin:5px 0}
.footer{text-align:center;color:#888;font-size:12px;margin:16px 0 28px}
@media(max-width:480px){.wrap{margin:18px auto}.card{padding:21px 17px}.slots{grid-template-columns:repeat(3,1fr)}}
</style>
</head>
<body>
<main class="wrap"><section class="card">
<h1><?= h(env('APP_NAME', 'Booking Appointment')) ?></h1>
<p class="sub">Pilih layanan, tanggal, dan waktu yang tersedia.</p>

<?php if ($notice): ?><div class="alert"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>

<?php if ($confirmation): ?>
<div class="confirm">
<strong>Booking berhasil, <?= h($confirmation['name']) ?>!</strong>
<p>Layanan: <?= h($confirmation['service']) ?></p>
<p>Tanggal: <?= h($confirmation['date']) ?> · <?= h($confirmation['time']) ?> WIB</p>
<p>Email: <?= h($confirmation['email']) ?></p>
<p>WhatsApp: <?= h($confirmation['phone']) ?></p>
<?php if ($confirmation['google_admin_link']): ?>
<p><a href="<?= h($confirmation['google_admin_link']) ?>" target="_blank" rel="noopener">Lihat event di Google Calendar</a></p>
<?php endif; ?>
</div>
<a class="btn light" href="index.php">Buat booking lain</a>
<?php else: ?>
<form method="post" id="bookingForm" novalidate>
<input type="hidden" name="csrf" value="<?= h($_SESSION['booking_csrf']) ?>">
<div class="field">
<label for="service">Layanan</label>
<select id="service" name="service" required>
<?php foreach ($services as $s): ?>
<option value="<?= h($s['name']) ?>" <?= $selectedService === $s['name'] ? 'selected' : '' ?>>
<?= h($s['name']) ?> — <?= (int)$s['duration'] ?> menit
</option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label for="date">Tanggal</label>
<input type="date" id="date" name="date" value="<?= h($selectedDate) ?>" min="<?= h($today) ?>" max="<?= h($maxDate) ?>" required>
</div>
<div class="field">
<label>Pilih jam</label>
<div class="slots" id="slots">
<?php foreach ($available as $s): ?>
<label class="slot"><input type="radio" name="time" value="<?= h($s) ?>" required><span><?= h($s) ?></span></label>
<?php endforeach; ?>
<?php if (!$available): ?>
<div class="empty" style="grid-column:1/-1">Tidak ada slot tersedia pada tanggal ini.</div>
<?php endif; ?>
</div>
<p class="small" id="slotStatus">Waktu mengikuti <?= h($tzName) ?>.</p>
</div>
<div class="field">
<label for="name">Nama lengkap</label>
<input id="name" name="name" maxlength="100" autocomplete="name" required>
</div>
<div class="field">
<label for="email">Email</label>
<input id="email" name="email" type="email" maxlength="190" autocomplete="email" required>
</div>
<div class="field">
<label for="phone">Nomor WhatsApp</label>
<input id="phone" name="phone" type="tel" maxlength="25" placeholder="08xxxxxxxxxx" autocomplete="tel" required>
</div>
<div class="field">
<label for="notes">Catatan <span class="small">(opsional)</span></label>
<textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Informasi tambahan..."></textarea>
</div>
<button class="btn" id="submitBtn" type="submit" <?= !$available ? 'disabled' : '' ?>>Konfirmasi Booking</button>
<p class="small">Jadwal akan dikonfirmasi setelah booking berhasil.</p>
</form>
<?php endif; ?>
</section><div class="footer">Powered by Google Calendar · <?= h(env('APP_NAME', 'Booking Appointment')) ?></div></main>

<script>
const dateEl = document.getElementById('date');
const serviceEl = document.getElementById('service');
const slotsEl = document.getElementById('slots');
const statusEl = document.getElementById('slotStatus');
const submitEl = document.getElementById('submitBtn');

async function refreshSlots() {
    if (!slotsEl) return;
    slotsEl.innerHTML = '<div class="empty" style="grid-column:1/-1">Memuat jadwal...</div>';
    if (statusEl) statusEl.textContent = 'Memeriksa ketersediaan...';
    if (submitEl) submitEl.disabled = true;
    try {
        const u = new URL(location.href);
        u.search = '';
        u.searchParams.set('ajax', 'slots');
        u.searchParams.set('date', dateEl.value);
        u.searchParams.set('service', serviceEl.value);
        const r = await fetch(u);
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || 'Gagal memuat slot');
        slotsEl.innerHTML = '';
        if (!d.slots.length) {
            slotsEl.innerHTML = '<div class="empty" style="grid-column:1/-1">Tidak ada slot tersedia.</div>';
            if (statusEl) statusEl.textContent = 'Pilih tanggal atau layanan lain.';
            return;
        }
        d.slots.forEach((t) => {
            const l = document.createElement('label');
            l.className = 'slot';
            const i = document.createElement('input');
            i.type = 'radio';
            i.name = 'time';
            i.value = t;
            i.required = true;
            const s = document.createElement('span');
            s.textContent = t;
            l.append(i, s);
            slotsEl.append(l);
        });
        if (statusEl) statusEl.textContent = d.slots.length + ' slot tersedia · <?= h($tzName) ?>';
        if (submitEl) submitEl.disabled = false;
    } catch (e) {
        slotsEl.innerHTML = '<div class="empty" style="grid-column:1/-1"></div>';
        if (slotsEl.firstChild) slotsEl.firstChild.textContent = e.message;
        if (statusEl) statusEl.textContent = '';
    }
}

if (dateEl && serviceEl) {
    dateEl.addEventListener('change', refreshSlots);
    serviceEl.addEventListener('change', refreshSlots);
}
const form = document.getElementById('bookingForm');
if (form) {
    form.addEventListener('submit', (e) => {
        if (!document.querySelector('input[name="time"]:checked')) {
            e.preventDefault();
            alert('Pilih jam booking terlebih dahulu.');
        }
    });
}
</script>
</body>
</html>
