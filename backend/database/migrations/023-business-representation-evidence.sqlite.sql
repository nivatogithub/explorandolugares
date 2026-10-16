CREATE TABLE business_account_application_evidence (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 application_id INTEGER NOT NULL REFERENCES business_account_applications(id) ON DELETE CASCADE,
 evidence_type TEXT NOT NULL,
 storage_name TEXT NOT NULL,
 original_name TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL,
 verified_by TEXT NULL,
 verified_at TEXT NULL,
 submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(application_id,evidence_type)
);
CREATE INDEX business_app_evidence_submitted ON business_account_application_evidence(submitted_at);
