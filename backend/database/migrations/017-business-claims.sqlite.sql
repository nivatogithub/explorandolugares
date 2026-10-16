CREATE TABLE business_claim_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 claimant_name TEXT NOT NULL,
 email TEXT NOT NULL DEFAULT '',
 phone TEXT NOT NULL DEFAULT '',
 relationship_code TEXT NOT NULL,
 message TEXT NOT NULL DEFAULT '',
 consent_version TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending',
 admin_note TEXT NOT NULL DEFAULT '',
 reviewed_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX business_claim_queue ON business_claim_requests(status,created_at);
CREATE INDEX business_claim_target ON business_claim_requests(business_id,status);
