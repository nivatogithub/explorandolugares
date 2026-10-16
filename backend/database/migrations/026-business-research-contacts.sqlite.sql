CREATE TABLE business_research_contacts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 channel TEXT NOT NULL, contact_value TEXT NOT NULL, source_url TEXT NOT NULL,
 source_type TEXT NOT NULL DEFAULT '', source_field TEXT NOT NULL DEFAULT '', source_period TEXT NOT NULL DEFAULT '',
 consulted_at TEXT NOT NULL, evidence_status TEXT NOT NULL, source_note TEXT NOT NULL,
 authorization_status TEXT NOT NULL DEFAULT 'NO_OBTENIDA', legal_basis_status TEXT NOT NULL,
 crm_stage TEXT NOT NULL DEFAULT '', potential_subscription TEXT NOT NULL DEFAULT '',
 marketing_consent INTEGER NOT NULL DEFAULT 0, campaign_allowed INTEGER NOT NULL DEFAULT 0,
 suppressed INTEGER NOT NULL DEFAULT 0, record_hash TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(business_id,record_hash)
);
CREATE INDEX business_research_business ON business_research_contacts(business_id,channel);
