<?php
declare(strict_types=1);
require __DIR__ . '/_header.php';

use App\Booking;

$statusFilter = (string)($_GET['status'] ?? 'confirmed');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!hash_equals($_SESSION['admin_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $message = 'Sesi tidak valid.';
    } else {
        try {
            Booking::cancel((int)$_POST['cancel_id']);
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            $message = 'Booking dibatalkan.';
            header('Location: bookings.php?status=' . urlencode($statusFilter));
            exit;
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }
    }
}

$bookings = Booking::all($statusFilter === 'all' ? null : $statusFilter);
?>
<h2>Data Booking</h2>
<p class="sub">Kelola semua janji temu pelanggan.</p>

<?php if ($message): ?><div class="alert <?= str_starts_with($message, 'Sesi') ? 'err' : 'ok' ?>"><?= h($message) ?></div><?php endif; ?>

<div class="card" style="overflow:auto">
<table>
<thead>
<tr>
<th style="width:60px">#</th><th style="width:110px">Tanggal</th><th style="width:80px">Waktu</th><th style="width:170px">Pelanggan</th><th style="width:150px">Email</th><th>Layanan</th><th style="width:130px">Telepon</th><th style="width:110px">Status</th><th style="width:150px">Dibuat</th><th style="width:120px">Aksi</th>
</tr>
</thead>
<tbody>
<?php if (!$bookings): ?>
<tr><td colspan="10" class="empty">Tidak ada data booking.</td></tr>
<?php else: ?>
<?php foreach ($bookings as $b): ?>
<tr>
<td><?= (int)$b['id'] ?></td>
<td><?= h($b['date']) ?></td>
<td><?= h($b['time']) ?></td>
<td><?= h($b['customer_name']) ?></td>
<td><?= h($b['customer_email']) ?></td>
<td><?= h($b['service_name'] ?? $b['service_db_name']) ?></td>
<td><?= h($b['customer_phone']) ?></td>
<td><span class="badge badge<?= ucfirst((string)$b['status']) ?>"><?= h((string)$b['status']) ?></span></td>
<td class="muted"><?= h($b['created_at']) ?></td>
<td>
<?php if ((string)$b['status'] === 'confirmed'): ?>
<form method="post" style="display:inline" onsubmit="return confirm('Batalkan booking ini?')">
<?= adminCsrfInput() ?>
<input type="hidden" name="cancel_id" value="<?= (int)$b['id'] ?>">
<button class="btn danger small" type="submit">Batalkan</button>
</form>
<?php else: ?>
<span class="btn small" style="opacity:.6">-</span>
<?php endif; ?>
<?php if (!empty($b['google_admin_link'])): ?>
<a href="<?= h($b['google_admin_link']) ?>" target="_blank" rel="noopener" class="btn small" style="margin-left:6px">Google</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<div style="display:flex;gap:8px">
<?php foreach (['all', 'confirmed', 'cancelled'] as $s): ?>
<a href="bookings.php?status=<?= $s ?>" class="btn <?= $s === $statusFilter ? 'primary' : '' ?>" style="padding:6px 12px"><?= ucfirst($s) ?></a>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
