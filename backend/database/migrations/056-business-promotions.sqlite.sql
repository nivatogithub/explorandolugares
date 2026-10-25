CREATE TABLE IF NOT EXISTS business_promotion_codes (
 code TEXT PRIMARY KEY, percentage INTEGER NOT NULL,
 plans_json TEXT NOT NULL, periods_json TEXT NOT NULL,
 enabled INTEGER NOT NULL DEFAULT 1, expires_at TEXT NULL, max_uses INTEGER NULL,
 created_by TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS business_promotion_redemptions (
 subscription_id INTEGER PRIMARY KEY REFERENCES business_subscriptions(id),
 code TEXT NOT NULL REFERENCES business_promotion_codes(code),
 business_id INTEGER NOT NULL REFERENCES businesses(id),
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 plan_code TEXT NOT NULL, billing_frequency TEXT NOT NULL, percentage INTEGER NOT NULL,
 list_cents INTEGER NOT NULL, addon_cents INTEGER NOT NULL DEFAULT 0,
 discount_cents INTEGER NOT NULL, due_cents INTEGER NOT NULL, currency TEXT NOT NULL,
 redeemed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(code,business_id)
);
CREATE TABLE IF NOT EXISTS business_promotion_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL REFERENCES business_promotion_codes(code),
 actor TEXT NOT NULL, settings_json TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
