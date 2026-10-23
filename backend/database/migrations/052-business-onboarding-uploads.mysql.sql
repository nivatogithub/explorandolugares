CREATE TABLE IF NOT EXISTS business_onboarding_uploads (
 id CHAR(48) NOT NULL PRIMARY KEY,
 draft_token CHAR(48) NOT NULL,
 upload_kind VARCHAR(24) NOT NULL,
 slot_code VARCHAR(40) NOT NULL DEFAULT '',
 storage_name VARCHAR(80) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 width SMALLINT UNSIGNED NULL,
 height SMALLINT UNSIGNED NULL,
 consumed_at DATETIME NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX business_onboarding_draft(draft_token,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
