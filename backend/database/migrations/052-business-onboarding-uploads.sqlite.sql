CREATE TABLE IF NOT EXISTS business_onboarding_uploads (
 id TEXT NOT NULL PRIMARY KEY,
 draft_token TEXT NOT NULL,
 upload_kind TEXT NOT NULL,
 slot_code TEXT NOT NULL DEFAULT '',
 storage_name TEXT NOT NULL UNIQUE,
 original_name TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL,
 width INTEGER NULL,
 height INTEGER NULL,
 consumed_at TEXT NULL,
 expires_at TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS business_onboarding_draft ON business_onboarding_uploads(draft_token,expires_at);
