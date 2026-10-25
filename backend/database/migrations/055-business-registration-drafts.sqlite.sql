CREATE TABLE IF NOT EXISTS business_registration_drafts (
 id TEXT PRIMARY KEY,
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 payload_json TEXT NOT NULL,
 wizard_step INTEGER NOT NULL DEFAULT 1,
 revision INTEGER NOT NULL DEFAULT 1,
 status TEXT NOT NULL DEFAULT 'editing',
 business_id INTEGER REFERENCES businesses(id),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS registration_drafts_account ON business_registration_drafts(account_id,status);
