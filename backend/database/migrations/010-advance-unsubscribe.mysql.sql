ALTER TABLE advance_deliveries ADD COLUMN unsubscribe_url VARCHAR(2048) NOT NULL DEFAULT '';
CREATE TABLE advance_email_suppressions (
 email VARCHAR(254) PRIMARY KEY,
 reason VARCHAR(32) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
