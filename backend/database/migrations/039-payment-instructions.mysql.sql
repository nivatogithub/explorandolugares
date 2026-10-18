-- Singleton configuration per environment. QR image lives in private storage.
CREATE TABLE payment_instructions (
 id INTEGER NOT NULL PRIMARY KEY,
 enabled INTEGER NOT NULL DEFAULT 0,
 test_mode INTEGER NOT NULL DEFAULT 0,
 holder VARCHAR(180) NOT NULL DEFAULT '',
 bank VARCHAR(120) NOT NULL DEFAULT '',
 account_number VARCHAR(80) NOT NULL DEFAULT '',
 account_type VARCHAR(16) NOT NULL DEFAULT '',
 breb_key VARCHAR(120) NOT NULL DEFAULT '',
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 qr_file VARCHAR(80) NOT NULL DEFAULT '',
 updated_at DATETIME NOT NULL,
 updated_by INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
