<?php
declare(strict_types=1);
require __DIR__ . '/_header.php';

use App\{Db, Booking};

$totalToday = Db::count('SELECT COUNT(*) FROM bookings WHERE date = :date AND status != "cancelled"', [':date' => (new DateTimeImmutable('today', new DateTimeZone(env('APP_TIMEZONE', 'Asia/Jakarta'))))->format('Y-m-d')]);
$totalConfirmed = Booking::countByStatus('confirmed');
$totalCancelled = Booking::countByStatus('cancelled');
$upcoming = Db::query(
    'SELECT b.*, s.name AS service_db_name FROM bookings b LEFT JOIN services s ON s.id = b.service_id
     WHERE b.status != "cancelled" ORDER BY b.date ASC, b.time ASC LIMIT 8'
);
$calendar = new App\Calendar();
$googleConnected = $calendar->isConnected();
?>
<h2>Dashboard</h2>
<p class="sub">Ringkasan booking dan jadwal yang akan datang.</p>

<div class="grid">
<div class="stat"><div class="v"><?= (int)$totalConfirmed ?></div><div class="l">Booking aktif</div></div>
<div class="stat"><div class="v"><?= (int)$totalToday ?></div><div class="l">Booking hari ini</div></div>
<div class="stat"><div class="v"><?= (int)$totalCancelled ?></div><div class="l">Dibatalkan</div></div>
<div class="stat"><div class="v"><?= $googleConnected ? 'Terhubung' : 'Belum' ?></div><div class="l">Google Calendar</div></div>
</div>

<div class="card">
<h3 style="margin:0 0 10px">Jadwal akan datang</h3>
<?php if (!$upcoming): ?>
<div class="empty">Belum ada booking yang akan datang.</div>
<?php else: ?>
<table>
<thead><tr><th style="width:130px">Tanggal</th><th style="width:90px">Waktu</th><th>Pelanggan</th><th style="width:170px">Layanan</th><th style="width:150px">Kontak</th><th style="width:140px">Aksi</th></tr></thead>
<tbody>
<?php foreach ($upcoming as $b): ?>
<tr>
<td><?= h($b['date']) ?></td>
<td><?= h($b['time']) ?></td>
<td><?= h($b['customer_name']) ?></td>
<td><?= h($b['service_name'] ?? $b['service_db_name']) ?></td>
<td><?= h($b['customer_phone']) ?></td>
<td><a href="bookings.php" class="btn small">Lihat semua</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<?php if (!$googleConnected): ?>
<div class="card">
<h3 style="margin:0 0 10px">Google Calendar belum terhubung</h3>
<p class="muted">Hubungkan Google Calendar bisnis agar ketersediaan slot otomatis terdeteksi dan event booking langsung muncul di kalender admin.</p>
<a href="calendar.php" class="btn primary">Hubungkan Google Calendar</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
