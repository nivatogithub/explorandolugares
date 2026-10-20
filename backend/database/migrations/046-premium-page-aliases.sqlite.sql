CREATE TABLE IF NOT EXISTS business_page_aliases (
 alias VARCHAR(70) PRIMARY KEY,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 is_current INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS business_page_alias_business ON business_page_aliases(business_id,is_current);
