-- schema.sql — database booking (SQLite)
-- Jalankan: sqlite3 database/bookings.sqlite < schema.sql

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS services (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL UNIQUE,
    duration     INTEGER NOT NULL,   -- menit
    price        INTEGER NOT NULL DEFAULT 0, -- dalam APP_CURRENCY (idr)
    is_active    INTEGER NOT NULL DEFAULT 1,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id      INTEGER NOT NULL,
    service_name    TEXT NOT NULL,
    customer_name   TEXT NOT NULL,
    customer_email  TEXT NOT NULL,
    customer_phone  TEXT NOT NULL,
    notes           TEXT DEFAULT '',
    start_at        TEXT NOT NULL,   -- ISO8601 dengan zona waktu
    end_at          TEXT NOT NULL,
    date            TEXT NOT NULL,   -- YYYY-MM-DD (zona setempat)
    time            TEXT NOT NULL,   -- HH:II
    status          TEXT NOT NULL DEFAULT 'confirmed', -- confirmed, cancelled, completed
    google_event_id TEXT,
    google_admin_link TEXT,
    google_customer_link TEXT,
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(date);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
CREATE INDEX IF NOT EXISTS idx_bookings_created_at ON bookings(created_at);

-- Data layanan awal
INSERT OR IGNORE INTO services (name, duration, price, sort_order) VALUES
    ('Konsultasi', 60, 0, 1),
    ('Layanan 30 Menit', 30, 0, 2),
    ('Layanan 90 Menit', 90, 0, 3);
