<?php
/**
 * config/env.php — sederhana .env loader + config helper.
 * Panggil sekali di awal setiap bootstrap (index.php / admin/*).
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!function_exists('env')) {
    /**
     * Ambil nilai env dengan fallback default.
     * env('APP_NAME', 'Booking Appointment')
     * env('SLOT_INTERVAL_MINUTES', 30, true)  => integer
     * env('APP_DEBUG', 0, true)               => boolean
     */
    function env(string $key, mixed $default = null, bool $cast = false): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($value === null || $value === false) {
            return $default;
        }
        if (!$cast) {
            return $value;
        }
        if (is_bool($default)) {
            return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
        }
        if (is_int($default)) {
            return (int)$value;
        }
        if (is_float($default)) {
            return (float)$value;
        }
        return $value;
    }
}

/**
 * Muat file .env dari APP_ROOT ke $_ENV / getenv (tidak menimpa env yang sudah ada).
 */
if (!function_exists('load_env')) {
    function load_env(?string $path = null): void
    {
        $path = $path ?? (APP_ROOT . '/.env');
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Hilangkan kurung petikOpsional
            if (preg_match('/^"(.*)"$/s', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = $m[1];
            }
            if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

load_env();

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));
