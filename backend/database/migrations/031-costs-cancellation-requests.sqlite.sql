CREATE TABLE operating_costs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 cost_date DATE NOT NULL,
 category VARCHAR(32) NOT NULL,
 amount_cop DECIMAL(14,2) NOT NULL,
 destination_id INTEGER NULL,
 description VARCHAR(500) NOT NULL,
 created_by VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_at DATETIME NULL,
 void_reason VARCHAR(500) NULL
);
CREATE TABLE subscription_cancellation_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 subscription_id INTEGER NOT NULL,
 business_id INTEGER NOT NULL,
 account_id INTEGER NOT NULL,
 reason_code VARCHAR(32) NOT NULL,
 reason_text VARCHAR(1000) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 review_note VARCHAR(1000) NULL,
 UNIQUE (subscription_id)
);
