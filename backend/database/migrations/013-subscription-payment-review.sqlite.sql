CREATE TABLE subscription_payment_claims (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 subscription_id INTEGER NOT NULL REFERENCES business_subscriptions(id) ON DELETE RESTRICT,
 reference VARCHAR(120) NOT NULL UNIQUE,
 payer_name VARCHAR(160) NOT NULL,
 amount_minor INTEGER NOT NULL, currency CHAR(3) NOT NULL,
 reported_on DATE NOT NULL, evidence_note TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 reviewed_by VARCHAR(120), reviewed_at DATETIME, review_note TEXT,
 payment_id INTEGER UNIQUE REFERENCES subscription_payments(id) ON DELETE RESTRICT,
 created_at DATETIME NOT NULL
);
CREATE INDEX payment_claim_status ON subscription_payment_claims(status,subscription_id);
