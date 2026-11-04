ALTER TABLE media_assets ADD COLUMN responsive_srcset TEXT NULL;
ALTER TABLE media_assets ADD COLUMN responsive_sizes TEXT NULL;
CREATE TABLE destination_content_migrations (
 migration_key VARCHAR(100) PRIMARY KEY,
 plan_sha256 CHAR(64) NOT NULL,
 changes_json LONGTEXT NOT NULL,
 applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
