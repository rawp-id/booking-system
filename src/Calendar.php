<?php
/**
 * src/Calendar.php — klien Google Calendar (OAuth admin/pemilik bisnis).
 * Hanya admin yang menghubungkan Google Calendar-nya sekali. Token disimpan
 * ke file (di luar web root jika memungkinkan) dan difresh otomatis.
 * PHP 7.4+, cURL, OpenSSL. Tanpa Composer.
 */
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class Calendar
{
    private array $cfg;
    private string $tokenFile;

    public function __construct(array $cfg = [])
    {
        $this->cfg = array_merge([
            'google_client_id'     => env('GOOGLE_CLIENT_ID', ''),
            'google_client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri'         => env('GOOGLE_REDIRECT_URI', ''),
            'calendar_id'          => env('GOOGLE_CALENDAR_ID', 'primary'),
            'admin_token_file'     => env('GOOGLE_ADMIN_TOKEN_FILE', APP_ROOT . '/storage/booking-admin-token.json'),
            'timezone'             => env('APP_TIMEZONE', 'Asia/Jakarta'),
        ], $cfg);
        $this->tokenFile = (string)$this->cfg['admin_token_file'];
    }

    /* ===================== OAuth: connect admin ===================== */

    public function connectUrl(string $state): string
    {
        $params = [
            'client_id'     => $this->cfg['google_client_id'],
            'redirect_uri'  => $this->cfg['redirect_uri'],
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.freebusy',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function exchangeCode(string $code): array
    {
        $r = httpRequest('POST', 'https://oauth2.googleapis.com/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'code'          => $code,
            'client_id'     => $this->cfg['google_client_id'],
            'client_secret' => $this->cfg['google_client_secret'],
            'redirect_uri'  => $this->cfg['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]));

        $token = json_decode($r['body'], true) ?: [];
        if ($r['status'] !== 200 || empty($token['access_token'])) {
            throw new RuntimeException('OAuth gagal: ' . ($token['error_description'] ?? $token['error'] ?? 'HTTP ' . $r['status']));
        }
        $token['expires_at'] = time() + (int)($token['expires_in'] ?? 3600);
        return $token;
    }

    public function saveToken(array $token): void
    {
        $dir = dirname($this->tokenFile);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Gagal membuat folder token: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Folder token tidak writable: ' . $dir);
        }
        $old = is_file($this->tokenFile) ? (json_decode((string)file_get_contents($this->tokenFile), true) ?: []) : [];
        if (empty($token['refresh_token']) && !empty($old['refresh_token'])) {
            $token['refresh_token'] = $old['refresh_token'];
        }
        $token['expires_at'] = time() + (int)($token['expires_in'] ?? 3600);
        if (file_put_contents($this->tokenFile, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Gagal menyimpan token admin.');
        }
        @chmod($this->tokenFile, 0600);
    }

    public function isConnected(): bool
    {
        return is_file($this->tokenFile);
    }

    public function accessToken(): string
    {
        if (!is_file($this->tokenFile)) {
            throw new RuntimeException('Google Calendar admin belum terhubung.');
        }
        $token = json_decode((string)file_get_contents($this->tokenFile), true);
        if (!$token || empty($token['refresh_token'])) {
            throw new RuntimeException('Token admin rusak. Hubungkan ulang Google Calendar.');
        }
        $token = $this->refreshIfNeeded($token);
        $this->saveToken($token);
        return (string)$token['access_token'];
    }

    public function disconnect(): void
    {
        if (is_file($this->tokenFile)) {
            @unlink($this->tokenFile);
        }
    }

    /* ===================== Google Calendar API ===================== */

    /**
     * Cek kesibukan admin pada rentang waktu.
     * @return array of ['start'=>..., 'end'=>...]
     */
    public function freeBusy(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $data = $this->api('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
            'timeMin' => $start->format(DateTimeInterface::RFC3339),
            'timeMax' => $end->format(DateTimeInterface::RFC3339),
            'timeZone' => $this->cfg['timezone'],
            'items' => [['id' => (string)$this->cfg['calendar_id']]],
        ]);
        $cal = $data['calendars'][(string)$this->cfg['calendar_id']] ?? [];
        if (!empty($cal['errors'])) {
            throw new RuntimeException('Tidak dapat membaca kalender admin. Periksa izin akun Google.');
        }
        return $cal['busy'] ?? [];
    }

    public function createEvent(array $payload): array
    {
        return $this->api('POST', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$this->cfg['calendar_id']) . '/events', $payload);
    }

    public function deleteEvent(string $eventId): void
    {
        $this->api('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$this->cfg['calendar_id']) . '/events/' . rawurlencode($eventId));
    }

    /* ===================== internal ===================== */

    private function refreshIfNeeded(array $token): array
    {
        if (!empty($token['access_token']) && !empty($token['expires_at']) && (int)$token['expires_at'] > time() + 90) {
            return $token;
        }
        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Refresh token tidak tersedia. Hubungkan ulang akun Google.');
        }
        $r = httpRequest('POST', 'https://oauth2.googleapis.com/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'client_id'     => $this->cfg['google_client_id'],
            'client_secret' => $this->cfg['google_client_secret'],
            'refresh_token' => $token['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]));
        $d = json_decode($r['body'], true) ?: [];
        if ($r['status'] !== 200 || empty($d['access_token'])) {
            throw new RuntimeException('Refresh token gagal: ' . ($d['error_description'] ?? $d['error'] ?? 'HTTP ' . $r['status']));
        }
        $token['access_token'] = $d['access_token'];
        $token['expires_at'] = time() + (int)($d['expires_in'] ?? 3600);
        return $token;
    }

    private function api(string $method, string $url, ?array $payload = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken(),
            'Content-Type: application/json',
        ];
        $r = httpRequest($method, $url, $headers, $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $d = json_decode($r['body'], true) ?: [];
        if ($r['status'] < 200 || $r['status'] >= 300) {
            throw new RuntimeException('Google Calendar API: ' . ($d['error']['message'] ?? ('HTTP ' . $r['status'])));
        }
        return $d;
    }
}

function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Aktifkan ekstensi PHP cURL.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 35,
    ]);
    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Koneksi gagal: ' . $err);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $out];
}
