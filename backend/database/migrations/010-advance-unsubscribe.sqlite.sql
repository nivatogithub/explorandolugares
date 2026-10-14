ALTER TABLE advance_deliveries ADD COLUMN unsubscribe_url TEXT NOT NULL DEFAULT '';
CREATE TABLE advance_email_suppressions (
 email VARCHAR(254) PRIMARY KEY,
 reason VARCHAR(32) NOT NULL,
 created_at DATETIME NOT NULL
);
