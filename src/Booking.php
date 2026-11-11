<?php
/**
 * src/Booking.php — logika bisnis booking (jadwal, slot, pembuatan janji).
 */
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use DateTimeInterface;

class Booking
{
    public static function services(): array
    {
        return Db::query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
    }

    public static function service(int $id): ?array
    {
        return Db::find('SELECT * FROM services WHERE id = :id AND is_active = 1', [':id' => $id]);
    }

    public static function serviceByName(string $name): ?array
    {
        return Db::find('SELECT * FROM services WHERE name = :name AND is_active = 1', [':name' => $name]);
    }

    public static function allServices(): array
    {
        return Db::query('SELECT * FROM services ORDER BY sort_order ASC, name ASC');
    }

    public static function upsertService(string $name, int $duration, int $price, int $isActive, int $sortOrder, ?int $id = null): void
    {
        if ($id) {
            Db::affect('UPDATE services SET name = :name, duration = :duration, price = :price, is_active = :is_active, sort_order = :sort_order WHERE id = :id', [
                ':name' => $name, ':duration' => $duration, ':price' => $price,
                ':is_active' => $isActive, ':sort_order' => $sortOrder, ':id' => $id,
            ]);
        } else {
            Db::execute('INSERT INTO services (name, duration, price, is_active, sort_order) VALUES (:name, :duration, :price, :is_active, :sort_order)', [
                ':name' => $name, ':duration' => $duration, ':price' => $price,
                ':is_active' => $isActive, ':sort_order' => $sortOrder,
            ]);
        }
    }

    public static function deleteService(int $id): void
    {
        Db::affect('DELETE FROM services WHERE id = :id', [':id' => $id]);
    }

    /**
     * Dapatkan slot kosong untuk tanggal + layanan.
     */
    public static function slots(string $date, int $serviceId): array
    {
        $service = self::service($serviceId);
        if (!$service) {
            throw new RuntimeException('Layanan tidak valid.');
        }
        $duration = (int)$service['duration'];
        return self::availableSlots($date, $duration);
    }

    public static function slotsByName(string $date, string $name): array
    {
        $service = self::serviceByName($name);
        if (!$service) {
            throw new RuntimeException('Layanan tidak valid.');
        }
        return self::availableSlots($date, (int)$service['duration']);
    }

    /**
     * Buat booking baru. Kembalikan data booking yang disisipkan.
     * Jika Google Calendar terhubung, event juga dibuat di kalender admin.
     */
    public static function create(array $input): array
    {
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'Asia/Jakarta'));
        $duration = (int)$input['duration'];
        $date = (string)$input['date'];
        $time = (string)$input['time'];

        self::validateDate($date);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new RuntimeException('Jam booking tidak valid.');
        }

        $start = new DateTimeImmutable($date . ' ' . $time, $tz);
        $end = (clone $start)->modify('+' . $duration . ' minutes');
        $open = new DateTimeImmutable($date . ' ' . env('OPENING_TIME', '09:00'), $tz);
        $close = new DateTimeImmutable($date . ' ' . env('CLOSING_TIME', '17:00'), $tz);
        $limit = (new DateTimeImmutable('now', $tz))->modify('+' . (int)env('MIN_NOTICE_MINUTES', 60) . ' minutes');

        if ($start < $open || $end > $close || $start <= $limit) {
            throw new RuntimeException('Jam booking di luar ketentuan operasional.');
        }

        // Cek tabrakan di database lokal terlebih dahulu.
        $clash = Db::find(
            'SELECT id FROM bookings WHERE date = :date AND status != "cancelled" AND start_at < :end AND end_at > :start',
            [':date' => $date, ':start' => $start->format(DateTimeInterface::RFC3339), ':end' => $end->format(DateTimeInterface::RFC3339)]
        );
        if ($clash) {
            throw new RuntimeException('Slot baru saja dipesan. Silakan pilih jam lain.');
        }

        // Re-check kalender Google jika admin telah terhubung.
        $googleEventId = null;
        $googleAdminLink = '';
        $calendar = new Calendar();
        if ($calendar->isConnected()) {
            $busy = $calendar->freeBusy($start, $end);
            $isFree = true;
            foreach ($busy as $p) {
                $a = new DateTimeImmutable($p['start']);
                $b = new DateTimeImmutable($p['end']);
                if ($start < $b && $end > $a) {
                    $isFree = false;
                    break;
                }
            }
            if (!$isFree) {
                throw new RuntimeException('Maaf, slot baru saja dipesan. Silakan pilih jam lain.');
            }

            $service = self::serviceByName((string)$input['service']) ?? self::service((int)$input['service_id']);
            $details = "Booking {$input['business_name']}\n"
                . "Nama: {$input['name']}\nEmail: {$input['email']}\nWhatsApp: {$input['phone']}\n"
                . "Layanan: {$service['name']}\nDurasi: {$duration} menit\n"
                . "Catatan: " . (($input['notes'] ?? '') !== '' ? $input['notes'] : '-') . "\n"
                . "Zona waktu: " . env('APP_TIMEZONE', 'Asia/Jakarta');

            $eventBody = [
                'summary' => env('APP_NAME', 'Booking Appointment') . ' — ' . $service['name'] . ' — ' . $input['name'],
                'description' => $details,
                'start' => ['dateTime' => $start->format(DateTimeInterface::RFC3339), 'timeZone' => env('APP_TIMEZONE', 'Asia/Jakarta')],
                'end' => ['dateTime' => $end->format(DateTimeInterface::RFC3339), 'timeZone' => env('APP_TIMEZONE', 'Asia/Jakarta')],
                'attendees' => [['email' => $input['email'], 'displayName' => $input['name'], 'responseStatus' => 'needsAction']],
                'guestsCanInviteOthers' => false,
                'guestsCanModify' => false,
                'guestsCanSeeOtherGuests' => false,
            ];
            try {
                $ev = $calendar->createEvent($eventBody);
                $googleEventId = $ev['id'] ?? null;
                $googleAdminLink = $ev['htmlLink'] ?? '';
            } catch (Throwable $e) {
                throw new RuntimeException('Gagal membuat event di Google Calendar: ' . $e->getMessage());
            }
        }

        $id = Db::execute(
            'INSERT INTO bookings (service_id, service_name, customer_name, customer_email, customer_phone, notes, start_at, end_at, date, time, google_event_id, google_admin_link)
             VALUES (:service_id, :service_name, :customer_name, :customer_email, :customer_phone, :notes, :start_at, :end_at, :date, :time, :google_event_id, :google_admin_link)',
            [
                ':service_id' => (int)($input['service_id'] ?? 0),
                ':service_name' => (string)($input['service'] ?? ''),
                ':customer_name' => trim($input['name']),
                ':customer_email' => trim($input['email']),
                ':customer_phone' => trim($input['phone']),
                ':notes' => trim($input['notes'] ?? ''),
                ':start_at' => $start->format(DateTimeInterface::RFC3339),
                ':end_at' => $end->format(DateTimeInterface::RFC3339),
                ':date' => $date,
                ':time' => $time,
                ':google_event_id' => $googleEventId,
                ':google_admin_link' => $googleAdminLink,
            ]
        );

        return self::find($id);
    }

    public static function find(int $id): ?array
    {
        $row = Db::find('SELECT b.*, s.name AS service_db_name FROM bookings b LEFT JOIN services s ON s.id = b.service_id WHERE b.id = :id', [':id' => $id]);
        return $row;
    }

    public static function all(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        if ($status) {
            return Db::query(
                'SELECT b.*, s.name AS service_db_name FROM bookings b LEFT JOIN services s ON s.id = b.service_id WHERE b.status = :status ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset',
                [':status' => $status, ':limit' => $limit, ':offset' => $offset]
            );
        }
        return Db::query(
            'SELECT b.*, s.name AS service_db_name FROM bookings b LEFT JOIN services s ON s.id = b.service_id ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        );
    }

    public static function countByStatus(string $status): int
    {
        return Db::count('SELECT COUNT(*) FROM bookings WHERE status = :status', [':status' => $status]);
    }

    public static function cancel(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            throw new RuntimeException('Booking tidak ditemukan.');
        }
        if ($row['google_event_id'] && (new Calendar())->isConnected()) {
            try {
                (new Calendar())->deleteEvent($row['google_event_id']);
            } catch (Throwable $e) {
                // gagal menghapus event Google tetap lanjutkan status lokal
            }
        }
        Db::affect('UPDATE bookings SET status = "cancelled" WHERE id = :id', [':id' => $id]);
    }

    /* ===================== internal ===================== */

    private static function availableSlots(string $date, int $duration): array
    {
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'Asia/Jakarta'));
        self::validateDate($date);
        $open = new DateTimeImmutable($date . ' ' . env('OPENING_TIME', '09:00'), $tz);
        $close = new DateTimeImmutable($date . ' ' . env('CLOSING_TIME', '17:00'), $tz);
        $interval = max(5, (int)env('SLOT_INTERVAL_MINUTES', 30));
        $nowLimit = (new DateTimeImmutable('now', $tz))->modify('+' . (int)env('MIN_NOTICE_MINUTES', 60) . ' minutes');

        // Busy dari database lokal
        $busyDb = Db::query(
            'SELECT start_at AS start, end_at AS end FROM bookings WHERE date = :date AND status != "cancelled"',
            [':date' => $date]
        );

        // Busy dari Google Calendar jika terhubung
        $busyGoogle = [];
        try {
            if ((new Calendar())->isConnected()) {
                $busyGoogle = (new Calendar())->freeBusy($open, $close);
            }
        } catch (Throwable $e) {
            // abaikan error Google, gunakan data lokal
        }

        $busy = array_merge($busyDb, $busyGoogle);
        $slots = [];
        for ($slot = $open; $slot->modify('+' . $duration . ' minutes') <= $close; $slot = $slot->modify('+' . $interval . ' minutes')) {
            $end = $slot->modify('+' . $duration . ' minutes');
            if ($slot < $nowLimit) {
                continue;
            }
            $free = true;
            foreach ($busy as $p) {
                if (empty($p['start']) || empty($p['end'])) {
                    continue;
                }
                $a = new DateTimeImmutable($p['start']);
                $b = new DateTimeImmutable($p['end']);
                if ($slot < $b && $end > $a) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                $slots[] = $slot->format('H:i');
            }
        }
        return $slots;
    }

    private static function validateDate(string $date): void
    {
        $tz = new DateTimeZone(env('APP_TIMEZONE', 'Asia/Jakarta'));
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new RuntimeException('Tanggal tidak valid.');
        }
        $today = new DateTimeImmutable('today', $tz);
        if ($d < $today || $d > $today->modify('+' . (int)env('MAX_DAYS_AHEAD', 60) . ' days')) {
            throw new RuntimeException('Tanggal di luar rentang booking.');
        }
        if (!in_array((int)$d->format('N'), self::workingDays(), true)) {
            throw new RuntimeException('Tanggal tersebut bukan hari operasional.');
        }
    }

    private static function workingDays(): array
    {
        $raw = env('WORKING_DAYS', '1,2,3,4,5,6');
        $days = array_map('intval', explode(',', $raw));
        return array_filter($days, fn($d) => $d >= 1 && $d <= 7);
    }
}
