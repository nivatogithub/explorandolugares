CREATE TABLE business_subscriptions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE RESTRICT,
 reference VARCHAR(120) NOT NULL UNIQUE,
 status VARCHAR(24) NOT NULL DEFAULT 'active',
 starts_on DATE NOT NULL, ends_on DATE NOT NULL,
 created_at DATETIME NOT NULL
);
CREATE INDEX subscription_business_period ON business_subscriptions(business_id,ends_on);
CREATE TABLE subscription_payments (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 subscription_id INTEGER NOT NULL REFERENCES business_subscriptions(id) ON DELETE RESTRICT,
 reference VARCHAR(120) NOT NULL UNIQUE,
 entry_type VARCHAR(24) NOT NULL,
 amount_minor INTEGER NOT NULL, currency CHAR(3) NOT NULL,
 received_on DATE NOT NULL, created_at DATETIME NOT NULL
);
CREATE TABLE business_capabilities (
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 capability_code VARCHAR(40) NOT NULL,
 PRIMARY KEY(business_id,capability_code)
);
