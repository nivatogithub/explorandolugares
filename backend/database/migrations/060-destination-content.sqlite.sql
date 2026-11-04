ALTER TABLE media_assets ADD COLUMN responsive_srcset TEXT NULL;
ALTER TABLE media_assets ADD COLUMN responsive_sizes TEXT NULL;
CREATE TABLE destination_content_migrations (
 migration_key TEXT PRIMARY KEY,
 plan_sha256 TEXT NOT NULL,
 changes_json TEXT NOT NULL,
 applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
