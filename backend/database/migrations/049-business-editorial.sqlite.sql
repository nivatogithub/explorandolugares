-- Private versions. No live content, accounts, subscriptions or analytics are changed.
CREATE TABLE IF NOT EXISTS business_editorial_versions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 parent_id INTEGER NULL REFERENCES business_editorial_versions(id),
 status TEXT NOT NULL DEFAULT 'draft',
 plan_code TEXT NOT NULL,
 base_hash TEXT NOT NULL,
 payload_json TEXT NOT NULL,
 payload_hash TEXT NOT NULL,
 image_consent INTEGER NOT NULL DEFAULT 0,
 note TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 owner_approved_at TEXT NULL,
 published_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS business_editorial_business ON business_editorial_versions(business_id,id);
CREATE TABLE IF NOT EXISTS business_editorial_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 version_id INTEGER NOT NULL REFERENCES business_editorial_versions(id) ON DELETE CASCADE,
 actor TEXT NOT NULL,
 action TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS business_editorial_images (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 version_id INTEGER NOT NULL REFERENCES business_editorial_versions(id) ON DELETE CASCADE,
 storage_name TEXT NOT NULL,
 original_name TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL,
 width INTEGER NOT NULL,
 height INTEGER NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
