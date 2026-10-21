-- Private versions. No live content, accounts, subscriptions or analytics are changed.
CREATE TABLE IF NOT EXISTS business_editorial_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'draft',
 plan_code VARCHAR(40) NOT NULL,
 base_hash CHAR(64) NOT NULL,
 payload_json LONGTEXT NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 image_consent INTEGER NOT NULL DEFAULT 0,
 note TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 owner_approved_at DATETIME NULL,
 published_at DATETIME NULL,
 INDEX business_editorial_business(business_id,id),
 FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,
 FOREIGN KEY(account_id) REFERENCES accounts(id),
 FOREIGN KEY(parent_id) REFERENCES business_editorial_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_editorial_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 version_id BIGINT UNSIGNED NOT NULL,
 actor VARCHAR(180) NOT NULL,
 action VARCHAR(40) NOT NULL,
 FOREIGN KEY(version_id) REFERENCES business_editorial_versions(id) ON DELETE CASCADE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_editorial_images (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 version_id BIGINT UNSIGNED NOT NULL,
 storage_name VARCHAR(80) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 file_size INTEGER NOT NULL,
 width INTEGER NOT NULL,
 height INTEGER NOT NULL,
 FOREIGN KEY(version_id) REFERENCES business_editorial_versions(id) ON DELETE CASCADE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
