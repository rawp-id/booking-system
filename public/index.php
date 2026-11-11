<?php
/**
 * public/index.php — halaman booking pelanggan (publik), cal.com style.
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
<title>Booking — <?= h(env('APP_NAME', 'Booking Appointment')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#0f172a;
  --muted:#64748b;
  --line:#e2e8f0;
  --bg:#f8fafc;
  --card:#ffffff;
  --accent:#6366f1;
  --accent-hover:#4f46e5;
  --ok:#e0f2fe;
  --danger:#fecaca;
}
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-tap-highlight-color:transparent}
body{
  margin:0;background:var(--bg);color:var(--ink);
  font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  font-size:16px;line-height:1.5;-webkit-font-smoothing:antialiased;
}
.wrap{
  max-width:480px;margin:0 auto;padding:24px 16px 60px;
  min-height:100vh;display:flex;flex-direction:column;
}
.card{
  background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;
  box-shadow:0 1px 3px rgba(0,0,0,0.06),0 4px 12px rgba(0,0,0,0.04);
}
header{
  display:flex;align-items:center;gap:12px;margin-bottom:8px;
}
.logo{
  width:32px;height:32px;border-radius:10px;background:var(--accent);color:#fff;
  display:grid;place-items:center;font-weight:700;font-size:14px;
}
.brand{font-weight:700;font-size:18px;letter-spacing:-0.4px}
.brand .t{color:var(--accent)}
.sub{color:var(--muted);font-size:14px;margin-bottom:20px}
.alert{
  padding:12px 14px;border-radius:12px;margin-bottom:16px;font-size:14px;
  border:1px solid transparent;
}
.alert.ok{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.alert.err{background:#fff1f2;color:#b91c1c;border-color:#fecaca}
.field{margin-bottom:18px}
.label{
  display:block;font-size:13px;font-weight:600;color:var(--ink);margin-bottom:7px;
  letter-spacing:-0.2px
}
label span.req{color:#ef4444}
input,select,textarea{
  width:100%;padding:11px 14px;border:1px solid var(--line);border-radius:12px;
  background:#fff;color:var(--ink);font:inherit;font-size:15px;outline:none;
  transition:border-color .15s,box-shadow .15s;
}
input:focus,select:focus,textarea:focus{
  border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,0.12);
}
select{appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 12px center;background-size:18px;padding-right:36px}
textarea{resize:vertical;min-height:96px}
.small{font-size:13px;color:var(--muted);margin-top:4px}
.btn{
  width:100%;padding:12px 16px;border:0;border-radius:12px;background:var(--accent);color:#fff;
  font:inherit;font-weight:600;font-size:15px;cursor:pointer;margin-top:8px;
  transition:background .15s,transform .05s;
}
.btn:hover{background:var(--accent-hover)}
.btn:active{transform:scale(0.995)}
.btn:disabled{opacity:.55;cursor:not-allowed;background:var(--accent)}
.btn.light{
  background:#fff;color:var(--ink);border:1px solid var(--line);
  margin-top:12px;
}
.btn.light:hover{background:#f1f5f9}
/* Slots grid */
.slots{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:4px}
.slot{
  position:relative;border:2px solid var(--line);border-radius:12px;
  background:#fff;cursor:pointer;transition:border-color .15s,background .15s,box-shadow .15s;
}
.slot:hover{border-color:var(--accent)}
.slot input{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
.slot .t{
  display:block;padding:10px 6px;font-size:14px;font-weight:600;color:var(--ink);
  text-align:center;border-radius:10px;
}
.slot input:checked + .t{
  background:var(--accent);border-color:var(--accent);color:#fff;
  box-shadow:0 0 0 3px rgba(99,102,241,0.18);
}
.empty{grid-column:1/-1;text-align:center;padding:16px;color:var(--muted);font-size:14px;border-radius:12px;background:#f1f5f9}
.statusText{font-size:13px;color:var(--muted);margin-bottom:14px}
.confirm{
  background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:16px;border-radius:14px;
}
.confirm p{margin:6px 0;font-size:14px;line-height:1.5}
.confirm strong{color:#1d4ed8}
.footer{
  margin-top:auto;padding-top:20px;color:var(--muted);font-size:12px;text-align:center;
}
/* mobile tweaks */
@media(max-width:480px){
  .wrap{padding:16px 16px 80px}
  .card{padding:20px}
  .slots{grid-template-columns:repeat(4,1fr);gap:8px}
}
</style>
</head>
<body>
<div class="wrap">
<main class="card">
<header>
<div class="logo">B</div>
<div class="brand">Booking <span class="t">App</span></div>
</header>
<p class="sub">Pilih layanan, tanggal, dan waktu yang tersedia.</p>

<?php if ($notice): ?>
<div class="alert ok"><?= h($notice) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert err"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($confirmation): ?>
<div class="confirm">
<strong>Booking berhasil, <?= h($confirmation['name']) ?>!</strong>
<p>Layanan: <?= h($confirmation['service']) ?></p>
<p>Tanggal: <?= h($confirmation['date']) ?> · <?= h($confirmation['time']) ?> WIB</p>
<p>Email: <?= h($confirmation['email']) ?></p>
<p>WhatsApp: <?= h($confirmation['phone']) ?></p>
<?php if ($confirmation['google_admin_link']): ?>
<p><a href="<?= h($confirmation['google_admin_link']) ?>" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:underline;font-weight:600">Lihat event di Google Calendar →</a></p>
<?php endif; ?>
</div>
<a class="btn light" href="index.php">Buat booking lain</a>
<?php else: ?>
<form method="post" id="bookingForm" novalidate>
<input type="hidden" name="csrf" value="<?= h($_SESSION['booking_csrf']) ?>">

<div class="field">
<label class="label">Layanan<span class="req">*</span></label>
<select id="service" name="service" required>
<?php foreach ($services as $s): ?>
<option value="<?= h($s['name']) ?>" <?= $selectedService === $s['name'] ? 'selected' : '' ?>>
<?= h($s['name']) ?> — <?= (int)$s['duration'] ?> menit
</option>
<?php endforeach; ?>
</select>
</div>

<div class="field">
<label class="label">Tanggal<span class="req">*</span></label>
<input type="date" id="date" name="date" value="<?= h($selectedDate) ?>" min="<?= h($today) ?>" max="<?= h($maxDate) ?>" required>
</div>

<div class="field">
<label class="label">Pilih jam<span class="req">*</span></label>
<div class="slots" id="slots">
<?php foreach ($available as $s): ?>
<label class="slot">
<input type="radio" name="time" value="<?= h($s) ?>" required>
<span class="t"><?= h($s) ?></span>
</label>
<?php endforeach; ?>
<?php if (!$available): ?>
<div class="empty">Tidak ada slot tersedia pada tanggal ini.</div>
<?php endif; ?>
</div>
<p class="statusText" id="slotStatus"><?= count($available) ?> slot tersedia · <?= h($tzName) ?></p>
</div>

<div class="field">
<label class="label">Nama lengkap<span class="req">*</span></label>
<input id="name" name="name" maxlength="100" autocomplete="name" required>
</div>
<div class="field">
<label class="label">Email<span class="req">*</span></label>
<input id="email" name="email" type="email" maxlength="190" autocomplete="email" required>
</div>
<div class="field">
<label class="label">Nomor WhatsApp<span class="req">*</span></label>
<input id="phone" name="phone" type="tel" maxlength="25" placeholder="08xxxxxxxxxx" autocomplete="tel" required>
</div>
<div class="field">
<label class="label">Catatan <span class="small">(opsional)</span></label>
<textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Informasi tambahan..."></textarea>
</div>

<button class="btn" id="submitBtn" type="submit" <?= !$available ? 'disabled' : '' ?>>Konfirmasi Booking</button>
<p class="small">Dengan melanjutkan, event akan dibuat langsung di kalender Google.</p>
</form>
<?php endif; ?>
</main>

<div class="footer">
Powered by Google Calendar · <?= h(env('APP_NAME', 'Booking Appointment')) ?>
</div>
</div>

<script>
const dateEl = document.getElementById('date');
const serviceEl = document.getElementById('service');
const slotsEl = document.getElementById('slots');
const statusEl = document.getElementById('slotStatus');
const submitEl = document.getElementById('submitBtn');

async function refreshSlots(){
  if(!slotsEl) return;
  slotsEl.innerHTML = '<div class="empty">Memuat jadwal...</div>';
  if(statusEl) statusEl.textContent = 'Memeriksa ketersediaan...';
  if(submitEl) submitEl.disabled = true;
  try{
    const u = new URL(location.href);
    u.search = '';
    u.searchParams.set('ajax','slots');
    u.searchParams.set('date', dateEl.value);
    u.searchParams.set('service', serviceEl.value);
    const r = await fetch(u);
    const d = await r.json();
    if(!r.ok) throw new Error(d.error || 'Gagal memuat slot');
    slotsEl.innerHTML = '';
    if(!d.slots.length){
      slotsEl.innerHTML = '<div class="empty">Tidak ada slot tersedia.</div>';
      if(statusEl) statusEl.textContent = 'Pilih tanggal atau layanan lain.';
      return;
    }
    d.slots.forEach((t)=>{
      const l = document.createElement('label');
      l.className = 'slot';
      const i = document.createElement('input');
      i.type = 'radio'; i.name = 'time'; i.value = t; i.required = true;
      const s = document.createElement('span');
      s.className = 't'; s.textContent = t;
      l.append(i, s);
      slotsEl.append(l);
    });
    if(statusEl) statusEl.textContent = d.slots.length + ' slot tersedia · <?= h($tzName) ?>';
    if(submitEl) submitEl.disabled = false;
  }catch(e){
    slotsEl.innerHTML = '<div class="empty">' + (e.message||'Gagal memuat slot') + '</div>';
    if(statusEl) statusEl.textContent = '';
  }
}

if(dateEl && serviceEl){
  dateEl.addEventListener('change', refreshSlots);
  serviceEl.addEventListener('change', refreshSlots);
}
const form = document.getElementById('bookingForm');
if(form){
  form.addEventListener('submit', (e)=>{
    if(!document.querySelector('input[name="time"]:checked')){
      e.preventDefault();
      alert('Pilih jam booking terlebih dahulu.');
    }
  });
}
</script>
</body>
</html>
