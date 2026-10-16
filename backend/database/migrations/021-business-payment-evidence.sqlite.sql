CREATE TABLE business_subscription_payment_evidence (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 subscription_id INTEGER NOT NULL REFERENCES business_subscriptions(id) ON DELETE CASCADE,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 storage_name TEXT NOT NULL,
 original_name TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL,
 payment_reference TEXT NOT NULL DEFAULT '',
 status TEXT NOT NULL DEFAULT 'pending',
 admin_note TEXT NOT NULL DEFAULT '',
 reviewed_by TEXT NULL,
 reviewed_at TEXT NULL,
 submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX business_payment_evidence_status ON business_subscription_payment_evidence(status,submitted_at);
CREATE INDEX business_payment_evidence_account ON business_subscription_payment_evidence(account_id,submitted_at);
