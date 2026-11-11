<?php
declare(strict_types=1);
require __DIR__ . '/_header.php';

use App\Booking;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $message = 'Sesi tidak valid.';
    } else {
        try {
            if (isset($_POST['add'])) {
                $name = trim((string)($_POST['name'] ?? ''));
                $duration = (int)($_POST['duration'] ?? 30);
                $price = (int)($_POST['price'] ?? 0);
                $active = (int)($_POST['is_active'] ?? 1);
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($name === '' || $duration <= 0) throw new RuntimeException('Nama dan durasi wajib diisi.');
                Booking::upsertService($name, $duration, $price, $active, $sort);
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: services.php');
                exit;
            } elseif (isset($_POST['update_id'])) {
                $id = (int)$_POST['update_id'];
                $name = trim((string)($_POST['name'] ?? ''));
                $duration = (int)($_POST['duration'] ?? 30);
                $price = (int)($_POST['price'] ?? 0);
                $active = (int)($_POST['is_active'] ?? 1);
                $sort = (int)($_POST['sort_order'] ?? 0);
                Booking::upsertService($name, $duration, $price, $active, $sort, $id);
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: services.php');
                exit;
            } elseif (isset($_POST['delete_id'])) {
                $id = (int)$_POST['delete_id'];
                Booking::deleteService($id);
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: services.php');
                exit;
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }
    }
}

$services = Booking::allServices();
$edit = null;
if (isset($_GET['edit'])) {
    $edit = Booking::service((int)$_GET['edit']);
}
?>
<h2>Layanan</h2>
<p class="sub">Kelola daftar layanan yang bisa dipesan pelanggan.</p>

<?php if ($message): ?><div class="alert err"><?= h($message) ?></div><?php endif; ?>

<div class="card">
<h3 style="margin:0 0 12px"><?= $edit ? 'Edit Layanan' : 'Tambah Layanan' ?></h3>
<form method="post" class="form-grid" style="gap:12px 20px">
<?= adminCsrfInput() ?>
<?php if ($edit): ?>
<input type="hidden" name="update_id" value="<?= (int)$edit['id'] ?>">
<?php else: ?>
<input type="hidden" name="add" value="1">
<?php endif; ?>
<div>
<label>Nama layanan</label>
<input name="name" value="<?= h($edit['name'] ?? '') ?>" required>
</div>
<div>
<label>Durasi (menit)</label>
<input name="duration" type="number" min="5" value="<?= h($edit['duration'] ?? '') ?>" required>
</div>
<div>
<label>Harga (<?= h(env('APP_CURRENCY', 'IDR')) ?>)</label>
<input name="price" type="number" min="0" value="<?= h($edit['price'] ?? '') ?>">
</div>
<div>
<label>Urutan tampilan</label>
<input name="sort_order" type="number" min="0" value="<?= h($edit['sort_order'] ?? 0) ?>">
</div>
<div>
<label>Status</label>
<select name="is_active">
<option value="1" <?= (!isset($edit['is_active']) || $edit['is_active']) ? 'selected' : '' ?>>Aktif</option>
<option value="0" <?= isset($edit['is_active']) && !$edit['is_active'] ? 'selected' : '' ?>>Tidak aktif</option>
</select>
</div>
<div style="align-self:end">
<?php if ($edit): ?>
<a href="services.php" class="btn">Batal</a>
<?php else: ?>
<a href="services.php" class="btn">Batal</a>
<?php endif; ?>
<button class="btn primary" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Layanan' ?></button>
</div>
</form>
</div>

<div class="card">
<h3 style="margin:0 0 12px">Daftar Layanan</h3>
<?php if (!$services): ?>
<div class="empty">Belum ada layanan.</div>
<?php else: ?>
<table>
<thead><tr><th style="width:60px">#</th><th>Nama</th><th style="width:120px">Durasi</th><th style="width:130px">Harga</th><th style="width:100px">Urutan</th><th style="width:110px">Status</th><th style="width:160px">Aksi</th></tr></thead>
<tbody>
<?php foreach ($services as $s): ?>
<tr>
<td><?= (int)$s['id'] ?></td>
<td><?= h($s['name']) ?></td>
<td><?= (int)$s['duration'] ?> menit</td>
<td><?= h(env('APP_CURRENCY', 'IDR')) ?> <?= (int)$s['price'] ?></td>
<td><?= (int)$s['sort_order'] ?></td>
<td><span class="badge badge<?= $s['is_active'] ? 'Confirmed' : 'Cancelled' ?>"><?= $s['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
<td>
<a href="services.php?edit=<?= (int)$s['id'] ?>" class="btn small" style="margin-right:6px">Edit</a>
<form method="post" style="display:inline" onsubmit="return confirm('Hapus layanan ini?')">
<?= adminCsrfInput() ?>
<input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
<button class="btn danger small" type="submit">Hapus</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
