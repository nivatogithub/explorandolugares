CREATE TABLE leads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_key VARCHAR(48) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL, business VARCHAR(160) NOT NULL,
 municipality VARCHAR(120) NOT NULL, category VARCHAR(32) NOT NULL,
 email VARCHAR(254) NOT NULL DEFAULT '', phone VARCHAR(40) NOT NULL DEFAULT '', phone_type VARCHAR(16) NOT NULL DEFAULT '',
 participation_version VARCHAR(48) NOT NULL DEFAULT '',
 has_brand VARCHAR(16) NOT NULL, brand_name VARCHAR(160) NOT NULL DEFAULT '',
 description TEXT NOT NULL, interest VARCHAR(24) NOT NULL,
 wants_contact TINYINT NOT NULL DEFAULT 0,
 consent_version VARCHAR(40) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'nuevo',
 created_at DATETIME NOT NULL,
 INDEX leads_status_created (status,created_at), INDEX leads_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rate_limits (bucket_key CHAR(64) PRIMARY KEY, hits INT NOT NULL, expires_at BIGINT NOT NULL) ENGINE=InnoDB;
