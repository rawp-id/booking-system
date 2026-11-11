# Booking Appointment — Admin Dashboard + Google Calendar

Sistem booking janji temu sederhana (PHP murni, tanpa Composer/framework) dengan **admin dashboard**, konfigurasi melalui **`.env`**, dan integrasi **Google Calendar** (OAuth admin).

## Struktur project

```
.
├── .env                  # konfigurasi lokal (jangan di-commit)
├── .env.example          # template konfigurasi
├── .htaccess             # blokir akses folder sensitif
├── README.md
├── schema.sql            # skema database SQLite
├── config/
│   └── env.php           # loader .env + helper env()
├── src/
│   ├── Db.php            # PDO SQLite wrapper
│   ├── Auth.php          # login admin (sesi)
│   ├── Booking.php       # logika bisnis booking & jadwal
│   └── Calendar.php      # klien Google Calendar OAuth
├── public/
│   ├── index.php         # halaman booking pelanggan (publik)
│   └── .htaccess
├── admin/
│   ├── _init.php         # bootstrap admin + require login
│   ├── _header.php
│   ├── _footer.php
│   ├── login.php
│   ├── logout.php
│   ├── index.php         # dashboard
│   ├── bookings.php      # kelola booking
│   ├── services.php      # kelola layanan
│   └── calendar.php      # koneksi Google Calendar
├── database/
│   └── bookings.sqlite
└── storage/              # token Google Calendar admin
```

## Instalasi

1. Salin konfigurasi:
   ```bash
   cp .env.example .env
   ```
2. Sesuaikan `.env` (Google Client ID/Secret, redirect URI, path token, dll).
3. Buat database:
   ```bash
   sqlite3 database/bookings.sqlite < schema.sql
   ```
4. Jalankan server:
   ```bash
   php -S 127.0.0.1:8080 -t public
   ```
   Buka `http://127.0.0.1:8080/index.php` (pelanggan) atau `http://127.0.0.1:8080/admin/login.php` (admin).

   > Catatan: `-t public` hanya untuk development. Untuk production, arahkan document root ke `public/` dan pastikan `config/`, `src/`, `database/`, `storage/` tidak dapat diakses dari web (sudah diatur oleh `.htaccess`).

## Login admin (default)

- Username: `admin`
- Password: `admin123`

**Ubah password segera di production:** generate hash baru dan simpan di `ADMIN_PASS_HASH` di `.env`:
```bash
php -r 'echo password_hash("password-baru", PASSWORD_DEFAULT), PHP_EOL;'
```

## Google Calendar

1. Buat project di [Google Cloud Console](https://console.cloud.google.com/), aktifkan **Google Calendar API**.
2. Buat OAuth Client ID (tipe Web Application), tambahkan redirect URI sesuai `GOOGLE_REDIRECT_URI`.
3. Masukkan Client ID & Secret ke `.env`.
4. Di admin → Kalender → "Hubungkan Google Calendar", berikan izin akses kalender.
5. Token disimpan di file yang ditetapkan `GOOGLE_ADMIN_TOKEN_FILE` (biar di luar web root).

Fitur yang terhubung Google Calendar:
- Cek ketersediaan slot otomatis (terdeteksi event padat di kalender admin).
- Setiap booking sukses membuat event langsung di kalender admin.
- Pelanggan ditambahkan sebagai attendee (dikirim undangan Google Calendar).

## Persyaratan server

- PHP 7.4+ (disarankan 8.1+)
- Ekstensi: `pdo_sqlite`, `openssl`, `curl`, `mbstring`, `sqlite3`

## Catatan keamanan

- Jangan commit `.env` (berisi secret) ke repositori publik.
- Token Google disimpan sebagai file; pastikan folder `storage/` tidak dapat diakses dari browser.
- Untuk production, gunakan HTTPS dan letakkan `database/` serta `storage/` di luar document root.
