CREATE TABLE business_bulk_update_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 batch_id TEXT NOT NULL,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 actor TEXT NOT NULL, before_json TEXT NOT NULL, after_json TEXT NOT NULL, changes_json TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX business_bulk_audit_batch ON business_bulk_update_audit(batch_id);
CREATE INDEX business_bulk_audit_business ON business_bulk_update_audit(business_id,created_at);
