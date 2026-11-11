<?php
/**
 * public/index.php — Halaman booking pelanggan ala Cal.com
 * - Multi-step / Split layout (Profile & Info | Calendar | Slots | Booking Form Modal/Step)
 * - Cal.com look & feel (Clean minimalist, modern typography, crisp borders, slick time picker)
 * - Full backward-compatible backend integration (POST create, AJAX ?ajax=slots)
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
$todayObj = new DateTimeImmutable('today', $tz);
$today = $todayObj->format('Y-m-d');
$maxDays = (int)env('MAX_DAYS_AHEAD', 60);
$maxDateObj = $todayObj->modify('+' . $maxDays . ' days');
$maxDate = $maxDateObj->format('Y-m-d');

$services = Booking::services();
$selectedServiceId = (int)($_GET['service_id'] ?? 0);
$currentService = null;

if ($selectedServiceId > 0) {
    foreach ($services as $s) {
        if ((int)$s['id'] === $selectedServiceId) {
            $currentService = $s;
            break;
        }
    }
}

if (!$currentService && !empty($_GET['service'])) {
    $currentService = Booking::serviceByName((string)$_GET['service']);
}

if (!$currentService && !empty($services)) {
    $currentService = $services[0];
}

$selectedServiceName = $currentService['name'] ?? '';
$selectedServiceDuration = (int)($currentService['duration'] ?? 30);
$selectedDate = (string)($_GET['date'] ?? $_POST['date'] ?? $today);

$error = '';
$notice = '';
$available = [];
$confirmation = $_SESSION['booking_confirmation'] ?? null;
unset($_SESSION['booking_confirmation']);

try {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'slots') {
        $date = (string)($_GET['date'] ?? '');
        $serviceName = (string)($_GET['service'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['slots' => Booking::slotsByName($date, $serviceName)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['booking_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sesi tidak valid. Silakan muat ulang halaman.');
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $time = (string)($_POST['time'] ?? '');
        $service = (string)($_POST['service'] ?? '');
        $postDate = (string)($_POST['date'] ?? $selectedDate);

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
            'date' => $postDate,
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
            'duration' => (int)$svc['duration'],
            'google_admin_link' => $booking['google_admin_link'] ?? '',
        ];
        header('Location: index.php?booked=1');
        exit;
    }

    if ($currentService) {
        $available = Booking::slots($selectedDate, (int)$currentService['id']);
    }
    if (isset($_GET['booked'])) {
        $notice = 'Booking berhasil! Silakan periksa detail konfirmasi Anda.';
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $currentService ? h($currentService['name']) . ' · ' : '' ?><?= h(env('APP_NAME', 'Booking Appointment')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --bg: #0b0f17;
  --page-bg: #f8fafc;
  --card-bg: #ffffff;
  --card-border: #e2e8f0;
  --divider: #f1f5f9;
  --text-main: #0f172a;
  --text-muted: #64748b;
  --text-subtle: #94a3b8;
  --brand-black: #111827;
  --brand-black-hover: #1f2937;
  --accent: #2563eb;
  --accent-light: #eff6ff;
  --accent-border: #bfdbfe;
  --surface-hover: #f1f5f9;
  --surface-active: #e2e8f0;
  --focus-ring: rgba(37, 99, 235, 0.2);
  --radius-lg: 18px;
  --radius-md: 10px;
  --radius-sm: 6px;
  --shadow-cal: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-tap-highlight-color: transparent; }
body {
  font-family: var(--font-sans);
  background-color: var(--page-bg);
  color: var(--text-main);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

/* Wrapper Card */
.cal-container {
  width: 100%;
  max-width: 1040px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-cal);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
}

/* Top alerts */
.alert {
  padding: 12px 18px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid transparent;
}
.alert.err { background: #fef2f2; color: #b91c1c; border-color: #fee2e2; }
.alert.ok { background: #f0fdf4; color: #15803d; border-color: #dcfce7; }

/* Cal Split Layout */
.cal-grid {
  display: grid;
  grid-template-columns: 310px 1fr 300px;
  min-height: 520px;
}
.cal-grid.no-slots {
  grid-template-columns: 320px 1fr;
}
.cal-grid.view-form {
  grid-template-columns: 340px 1fr;
}

/* Sidebar / Left Column */
.cal-sidebar {
  padding: 32px 28px;
  border-right: 1px solid var(--card-border);
  display: flex;
  flex-direction: column;
  background: #ffffff;
}
.brand-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}
.brand-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #0f172a;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
  letter-spacing: -0.5px;
}
.brand-info {
  display: flex;
  flex-direction: column;
}
.brand-org {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-muted);
}
.brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-main);
  letter-spacing: -0.2px;
}

.event-details {
  margin-bottom: 24px;
}
.event-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-main);
  letter-spacing: -0.4px;
  line-height: 1.3;
  margin-bottom: 14px;
}
.event-meta {
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 500;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 10px;
}
.meta-icon {
  width: 18px;
  height: 18px;
  color: var(--text-muted);
  flex-shrink: 0;
}

/* Service picker dropdown in sidebar */
.service-selector {
  margin-top: 10px;
  margin-bottom: 20px;
}
.service-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-subtle);
  margin-bottom: 6px;
}
.select-styled {
  width: 100%;
  padding: 9px 12px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-main);
  border: 1px solid var(--card-border);
  border-radius: var(--radius-md);
  background: #fff;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 16px;
  outline: none;
  transition: border-color .15s;
}
.select-styled:focus {
  border-color: var(--brand-black);
}

.event-desc {
  font-size: 13.5px;
  color: var(--text-muted);
  line-height: 1.5;
  margin-top: auto;
  padding-top: 20px;
  border-top: 1px solid var(--divider);
}

/* Center Column / Interactive Calendar */
.cal-main {
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
}
.cal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}
.cal-month-title {
  font-size: 17px;
  font-weight: 600;
  color: var(--text-main);
  letter-spacing: -0.3px;
}
.cal-nav-buttons {
  display: flex;
  gap: 4px;
}
.cal-nav-btn {
  background: transparent;
  border: 1px solid var(--card-border);
  border-radius: var(--radius-md);
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-muted);
  transition: all 0.15s;
}
.cal-nav-btn:hover:not(:disabled) {
  background: var(--surface-hover);
  color: var(--text-main);
}
.cal-nav-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.cal-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
  text-align: center;
}
.cal-day-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-subtle);
  padding-bottom: 8px;
}
.cal-day-cell {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--text-main);
  cursor: pointer;
  position: relative;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
}
.cal-day-cell:hover:not(:disabled):not(.is-selected) {
  background: var(--surface-hover);
}
.cal-day-cell:disabled {
  color: #cbd5e1;
  cursor: not-allowed;
}
.cal-day-cell.is-selected {
  background: #111827 !important;
  color: #ffffff !important;
  font-weight: 600;
}
.cal-day-cell.is-today:not(.is-selected)::after {
  content: '';
  position: absolute;
  bottom: 4px;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--text-main);
}

.cal-timezone-footer {
  margin-top: auto;
  padding-top: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-muted);
}
.cal-timezone-footer svg {
  width: 15px;
  height: 15px;
  color: var(--text-subtle);
}

/* Right Column / Slot Time Picker */
.cal-slots {
  padding: 32px 24px;
  border-left: 1px solid var(--card-border);
  background: #ffffff;
  display: flex;
  flex-direction: column;
  max-height: 580px;
  overflow-y: auto;
}
.slots-header {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 16px;
  letter-spacing: -0.2px;
}
.slots-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
  padding-right: 4px;
}
.slots-list::-webkit-scrollbar {
  width: 4px;
}
.slots-list::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.slot-item {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--card-border);
  background: #ffffff;
  border-radius: var(--radius-md);
  font-size: 14.5px;
  font-weight: 600;
  color: var(--text-main);
  cursor: pointer;
  text-align: center;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}
.slot-item:hover {
  border-color: var(--brand-black);
  background: var(--surface-hover);
}
.slot-item.is-selected {
  background: var(--brand-black);
  color: #ffffff;
  border-color: var(--brand-black);
}

.slots-empty {
  text-align: center;
  padding: 40px 16px;
  color: var(--text-muted);
  font-size: 13.5px;
  background: #f8fafc;
  border-radius: var(--radius-md);
  border: 1px dashed var(--card-border);
}

/* Form View (Step 2 Booking Details) */
.cal-form-pane {
  padding: 36px 36px;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}
.form-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--text-muted);
  background: transparent;
  border: none;
  cursor: pointer;
  margin-bottom: 20px;
  padding: 4px 0;
  transition: color 0.15s;
}
.form-back-btn:hover {
  color: var(--text-main);
}
.form-heading {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.3px;
  color: var(--text-main);
  margin-bottom: 20px;
}
.cal-field {
  margin-bottom: 18px;
}
.cal-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 6px;
}
.cal-label .req {
  color: #ef4444;
}
.cal-input, .cal-textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--card-border);
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 14.5px;
  color: var(--text-main);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  background: #fff;
}
.cal-input:focus, .cal-textarea:focus {
  border-color: var(--brand-black);
  box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
}
.cal-textarea {
  resize: vertical;
  min-height: 80px;
}
.cal-btn-submit {
  width: 100%;
  padding: 12px 18px;
  border-radius: var(--radius-md);
  background: var(--brand-black);
  color: #ffffff;
  border: none;
  font-family: inherit;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, transform 0.05s;
  margin-top: 8px;
}
.cal-btn-submit:hover {
  background: var(--brand-black-hover);
}
.cal-btn-submit:active {
  transform: scale(0.99);
}

/* Confirmation Card */
.cal-confirm-card {
  padding: 56px 32px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 540px;
  margin: 0 auto;
}
.confirm-icon-wrap {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #dcfce7;
  color: #15803d;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}
.confirm-title {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -0.5px;
  color: var(--text-main);
  margin-bottom: 8px;
}
.confirm-sub {
  font-size: 15px;
  color: var(--text-muted);
  margin-bottom: 28px;
}
.confirm-box {
  width: 100%;
  border: 1px solid var(--card-border);
  border-radius: var(--radius-md);
  padding: 20px;
  text-align: left;
  background: #f8fafc;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-size: 14px;
}
.confirm-row {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--text-main);
}
.confirm-row svg {
  color: var(--text-muted);
  flex-shrink: 0;
}
.confirm-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--accent);
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
  margin-top: 6px;
}
.confirm-link:hover {
  text-decoration: underline;
}
.btn-reset {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  border: 1px solid var(--card-border);
  background: #ffffff;
  color: var(--text-main);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.15s;
}
.btn-reset:hover {
  background: var(--surface-hover);
}

/* Footer note */
.cal-page-footer {
  margin-top: 24px;
  font-size: 13px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 8px;
}
.cal-page-footer a {
  color: var(--text-main);
  text-decoration: none;
  font-weight: 600;
}

/* Responsive */
@media (max-width: 900px) {
  body { padding: 16px 8px; }
  .cal-container { border-radius: 14px; }
  .cal-grid {
    grid-template-columns: 1fr;
    min-height: auto;
  }
  .cal-sidebar {
    border-right: none;
    border-bottom: 1px solid var(--card-border);
    padding: 24px 20px;
  }
  .cal-main {
    padding: 24px 20px;
    border-bottom: 1px solid var(--card-border);
  }
  .cal-slots {
    border-left: none;
    padding: 24px 20px;
    max-height: 380px;
  }
  .cal-form-pane {
    padding: 24px 20px;
  }
}
</style>
</head>
<body>

<div class="cal-container">
  <?php if ($notice): ?>
    <div class="alert ok">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
      <span><?= h($notice) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert err">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <span><?= h($error) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($confirmation): ?>
    <!-- SUCCESS CONFIRMATION (CAL.COM STYLE) -->
    <div class="cal-confirm-card">
      <div class="confirm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
      <h2 class="confirm-title">Jadwal Anda Telah Dikonfirmasi!</h2>
      <p class="confirm-sub">Kami telah mengirimkan undangan ke <strong><?= h($confirmation['email']) ?></strong>.</p>

      <div class="confirm-box">
        <div class="confirm-row">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span><strong><?= h($confirmation['name']) ?></strong> (<?= h($confirmation['phone']) ?>)</span>
        </div>
        <div class="confirm-row">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
          <span><?= h($confirmation['service']) ?> (<?= (int)$confirmation['duration'] ?> menit)</span>
        </div>
        <div class="confirm-row">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <span><?= h($confirmation['date']) ?> · <strong><?= h($confirmation['time']) ?> WIB</strong></span>
        </div>
        <?php if ($confirmation['google_admin_link']): ?>
          <a class="confirm-link" href="<?= h($confirmation['google_admin_link']) ?>" target="_blank" rel="noopener">
            Buka di Google Calendar
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          </a>
        <?php endif; ?>
      </div>

      <a href="index.php" class="btn-reset">Jadwalkan Sesi Lain</a>
    </div>

  <?php else: ?>

    <!-- MAIN BOOKING WIDGET -->
    <div class="cal-grid" id="calGrid">
      
      <!-- LEFT SIDEBAR -->
      <aside class="cal-sidebar">
        <div class="brand-badge">
          <div class="brand-avatar">
            <?= strtoupper(substr(env('APP_NAME', 'Booking'), 0, 1)) ?>
          </div>
          <div class="brand-info">
            <span class="brand-org"><?= h(env('APP_NAME', 'Booking Appointment')) ?></span>
            <span class="brand-name" id="sidebarOrgName">Konsultasi Privat</span>
          </div>
        </div>

        <div class="event-details">
          <h1 class="event-title" id="displayServiceName"><?= h($selectedServiceName) ?></h1>
          
          <div class="service-selector">
            <label class="service-label" for="serviceSelect">Pilih Layanan</label>
            <select id="serviceSelect" class="select-styled">
              <?php foreach ($services as $s): ?>
                <option value="<?= h($s['name']) ?>" data-duration="<?= (int)$s['duration'] ?>" <?= ($s['name'] === $selectedServiceName) ? 'selected' : '' ?>>
                  <?= h($s['name']) ?> (<?= (int)$s['duration'] ?>m)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="event-meta">
            <div class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              <span id="displayDuration"><?= $selectedServiceDuration ?> Menit</span>
            </div>
            <div class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 10l5 5-5 5"></path><path d="M4 4v7a4 4 0 0 0 4 4h12"></path></svg>
              <span>Google Meet / Calendar Sync</span>
            </div>
            <div class="meta-item">
              <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
              <span><?= h($tzName) ?></span>
            </div>
          </div>
        </div>

        <div class="event-desc">
          Pilih waktu yang paling sesuai untuk Anda. Undangan kalender dan konfirmasi akan dikirimkan otomatis setelah reservasi.
        </div>
      </aside>

      <!-- CENTER STEP: DATE PICKER -->
      <section class="cal-main" id="calDatePane">
        <div class="cal-header">
          <div class="cal-month-title" id="calMonthTitle">Bulan Tahun</div>
          <div class="cal-nav-buttons">
            <button type="button" class="cal-nav-btn" id="prevMonthBtn" aria-label="Bulan sebelumnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button type="button" class="cal-nav-btn" id="nextMonthBtn" aria-label="Bulan berikutnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </div>
        </div>

        <div class="cal-days-grid" id="calDaysGrid">
          <!-- Calendar cells populated by JavaScript -->
        </div>

        <div class="cal-timezone-footer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
          <span>Zona Waktu: <strong><?= h($tzName) ?></strong></span>
        </div>
      </section>

      <!-- RIGHT STEP: SLOTS TIME PICKER -->
      <section class="cal-slots" id="calSlotsPane">
        <div class="slots-header" id="slotsHeaderDate">Pilih Jam</div>
        <div class="slots-list" id="slotsContainer">
          <!-- Slot buttons rendered via JS -->
          <div class="slots-empty">Memuat slot...</div>
        </div>
      </section>

      <!-- STEP 3: DETAILS FORM (Shown when slot is clicked) -->
      <section class="cal-form-pane" id="calFormPane" style="display:none;">
        <button type="button" class="form-back-btn" id="formBackBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          Kembali pilih jadwal
        </button>

        <h2 class="form-heading">Masukkan Detail Anda</h2>

        <form method="post" id="calBookingForm" novalidate>
          <input type="hidden" name="csrf" value="<?= h($_SESSION['booking_csrf']) ?>">
          <input type="hidden" name="service" id="formInputService" value="<?= h($selectedServiceName) ?>">
          <input type="hidden" name="date" id="formInputDate" value="<?= h($selectedDate) ?>">
          <input type="hidden" name="time" id="formInputTime" value="">

          <div class="cal-field">
            <label class="cal-label" for="userName">Nama Lengkap <span class="req">*</span></label>
            <input class="cal-input" id="userName" name="name" placeholder="John Doe" maxlength="100" autocomplete="name" required>
          </div>

          <div class="cal-field">
            <label class="cal-label" for="userEmail">Alamat Email <span class="req">*</span></label>
            <input class="cal-input" id="userEmail" name="email" type="email" placeholder="john@example.com" maxlength="190" autocomplete="email" required>
          </div>

          <div class="cal-field">
            <label class="cal-label" for="userPhone">Nomor WhatsApp <span class="req">*</span></label>
            <input class="cal-input" id="userPhone" name="phone" type="tel" placeholder="08xxxxxxxxxx" maxlength="25" autocomplete="tel" required>
          </div>

          <div class="cal-field">
            <label class="cal-label" for="userNotes">Catatan Tambahan <span style="font-weight:400;color:var(--text-muted);">(opsional)</span></label>
            <textarea class="cal-textarea" id="userNotes" name="notes" placeholder="Beritahukan hal yang perlu dipersiapkan sebelum sesi..." maxlength="1000"></textarea>
          </div>

          <button type="submit" class="cal-btn-submit" id="submitBookingBtn">Konfirmasi Jadwal</button>
        </form>
      </section>

    </div>
  <?php endif; ?>
</div>

<div class="cal-page-footer">
  <span>Powered by <strong>SAYA</strong></span>
</div>

<script>
(function() {
  const todayStr = "<?= $today ?>";
  const maxDateStr = "<?= $maxDate ?>";
  const tzName = "<?= h($tzName) ?>";
  
  const todayDate = new Date(todayStr + "T00:00:00");
  const maxDateObj = new Date(maxDateStr + "T00:00:00");
  
  let selectedDate = "<?= h($selectedDate) ?>";
  let selectedTime = "";
  let viewDate = new Date(selectedDate + "T00:00:00");
  if (isNaN(viewDate.getTime())) {
    viewDate = new Date(todayDate);
  }

  // DOM elements
  const gridEl = document.getElementById('calGrid');
  const serviceSelect = document.getElementById('serviceSelect');
  const displayServiceName = document.getElementById('displayServiceName');
  const displayDuration = document.getElementById('displayDuration');
  const monthTitleEl = document.getElementById('calMonthTitle');
  const daysGridEl = document.getElementById('calDaysGrid');
  const prevMonthBtn = document.getElementById('prevMonthBtn');
  const nextMonthBtn = document.getElementById('nextMonthBtn');
  const slotsPane = document.getElementById('calSlotsPane');
  const slotsHeaderDate = document.getElementById('slotsHeaderDate');
  const slotsContainer = document.getElementById('slotsContainer');
  const datePane = document.getElementById('calDatePane');
  const formPane = document.getElementById('calFormPane');
  const formBackBtn = document.getElementById('formBackBtn');
  const bookingForm = document.getElementById('calBookingForm');
  const inputService = document.getElementById('formInputService');
  const inputDate = document.getElementById('formInputDate');
  const inputTime = document.getElementById('formInputTime');

  if (!gridEl) return;

  const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  function formatYMD(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function formatDisplayDate(dateStr) {
    const [y, m, d] = dateStr.split('-');
    const dt = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
    return `${dayNames[dt.getDay()]}, ${dt.getDate()} ${monthNames[dt.getMonth()]}`;
  }

  function renderCalendar() {
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    monthTitleEl.textContent = `${monthNames[month]} ${year}`;

    // Prev button disable if current month <= today's month
    const isCurrentOrPastMonth = (year < todayDate.getFullYear()) || 
      (year === todayDate.getFullYear() && month <= todayDate.getMonth());
    prevMonthBtn.disabled = isCurrentOrPastMonth;

    // Next button disable if current month >= maxDate month
    const isMaxMonth = (year > maxDateObj.getFullYear()) ||
      (year === maxDateObj.getFullYear() && month >= maxDateObj.getMonth());
    nextMonthBtn.disabled = isMaxMonth;

    daysGridEl.innerHTML = '';

    // Day labels
    dayNames.forEach(name => {
      const el = document.createElement('div');
      el.className = 'cal-day-label';
      el.textContent = name;
      daysGridEl.appendChild(el);
    });

    const firstDayIndex = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();

    // Blank cells before first day
    for (let i = 0; i < firstDayIndex; i++) {
      const blank = document.createElement('div');
      daysGridEl.appendChild(blank);
    }

    for (let d = 1; d <= totalDays; d++) {
      const cellDate = new Date(year, month, d);
      const cellYMD = formatYMD(cellDate);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cal-day-cell';
      btn.textContent = d;

      if (cellYMD === todayStr) {
        btn.classList.add('is-today');
      }

      if (cellYMD === selectedDate) {
        btn.classList.add('is-selected');
      }

      // Check disable (past or beyond max)
      if (cellDate < todayDate || cellDate > maxDateObj) {
        btn.disabled = true;
      } else {
        btn.addEventListener('click', () => {
          selectedDate = cellYMD;
          document.querySelectorAll('.cal-day-cell').forEach(c => c.classList.remove('is-selected'));
          btn.classList.add('is-selected');
          loadSlots();
        });
      }

      daysGridEl.appendChild(btn);
    }
  }

  async function loadSlots() {
    slotsHeaderDate.textContent = formatDisplayDate(selectedDate);
    slotsContainer.innerHTML = '<div class="slots-empty">Memeriksa ketersediaan...</div>';

    try {
      const serviceName = serviceSelect.value;
      const url = new URL(location.href);
      url.search = '';
      url.searchParams.set('ajax', 'slots');
      url.searchParams.set('date', selectedDate);
      url.searchParams.set('service', serviceName);

      const res = await fetch(url.toString());
      const data = await res.json();

      if (!res.ok) throw new Error(data.error || 'Gagal memuat slot.');

      slotsContainer.innerHTML = '';
      if (!data.slots || data.slots.length === 0) {
        slotsContainer.innerHTML = '<div class="slots-empty">Tidak ada slot tersedia pada tanggal ini. Silakan pilih hari lain.</div>';
        return;
      }

      data.slots.forEach(slotTime => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'slot-item';
        item.textContent = slotTime;
        item.addEventListener('click', () => {
          selectedTime = slotTime;
          showFormView();
        });
        slotsContainer.appendChild(item);
      });

    } catch (err) {
      slotsContainer.innerHTML = `<div class="slots-empty">${err.message || 'Gagal memuat jadwal.'}</div>`;
    }
  }

  function showFormView() {
    inputService.value = serviceSelect.value;
    inputDate.value = selectedDate;
    inputTime.value = selectedTime;

    datePane.style.display = 'none';
    slotsPane.style.display = 'none';
    formPane.style.display = 'flex';
    gridEl.classList.add('view-form');
  }

  function hideFormView() {
    formPane.style.display = 'none';
    datePane.style.display = 'flex';
    slotsPane.style.display = 'flex';
    gridEl.classList.remove('view-form');
  }

  // Events
  prevMonthBtn.addEventListener('click', () => {
    viewDate.setMonth(viewDate.getMonth() - 1);
    renderCalendar();
  });

  nextMonthBtn.addEventListener('click', () => {
    viewDate.setMonth(viewDate.getMonth() + 1);
    renderCalendar();
  });

  serviceSelect.addEventListener('change', () => {
    const selectedOpt = serviceSelect.options[serviceSelect.selectedIndex];
    displayServiceName.textContent = selectedOpt.value;
    displayDuration.textContent = selectedOpt.dataset.duration + ' Menit';
    loadSlots();
  });

  formBackBtn.addEventListener('click', hideFormView);

  bookingForm.addEventListener('submit', (e) => {
    const name = document.getElementById('userName').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const phone = document.getElementById('userPhone').value.trim();

    if (!name || !email || !phone) {
      e.preventDefault();
      alert('Mohon isi semua kolom yang diperlukan.');
      return;
    }
    if (!inputTime.value) {
      e.preventDefault();
      alert('Jam belum dipilih. Silakan kembali.');
      hideFormView();
      return;
    }
  });

  // Initial load
  renderCalendar();
  loadSlots();

})();
</script>
</body>
</html>
