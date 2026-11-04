CREATE TABLE IF NOT EXISTS business_payment_orders (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 request_key TEXT NOT NULL,
 request_hash TEXT NOT NULL,
 checkout_reference TEXT NOT NULL UNIQUE,
 total_cents INTEGER NOT NULL CHECK(total_cents>0),
 currency TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'awaiting_evidence' CHECK(status IN ('awaiting_evidence','under_review','approved','rejected','clarification_requested','cancelled')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(account_id,request_key)
);
CREATE TABLE IF NOT EXISTS business_payment_order_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES business_payment_orders(id),
 business_id INTEGER NOT NULL REFERENCES businesses(id),
 source_request_id INTEGER NOT NULL REFERENCES business_subscriptions(id),
 subscription_id INTEGER NULL REFERENCES business_subscriptions(id),
 open_business_id INTEGER NULL UNIQUE REFERENCES businesses(id),
 business_name TEXT NOT NULL,
 place_name TEXT NOT NULL,
 quote_json TEXT NOT NULL,
 addon_json TEXT NULL,
 due_cents INTEGER NOT NULL CHECK(due_cents>0),
 UNIQUE(order_id,business_id)
);
CREATE TABLE IF NOT EXISTS business_payment_order_evidence (
 evidence_id INTEGER PRIMARY KEY REFERENCES business_subscription_payment_evidence(id),
 order_id INTEGER NOT NULL REFERENCES business_payment_orders(id)
);
CREATE INDEX IF NOT EXISTS business_payment_orders_account ON business_payment_orders(account_id,created_at);
CREATE INDEX IF NOT EXISTS business_payment_order_items_subscription ON business_payment_order_items(subscription_id);
CREATE INDEX IF NOT EXISTS business_payment_order_evidence_order ON business_payment_order_evidence(order_id,evidence_id);
