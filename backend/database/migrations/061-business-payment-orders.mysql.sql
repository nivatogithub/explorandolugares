CREATE TABLE IF NOT EXISTS business_payment_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(48) NOT NULL UNIQUE,
 account_id BIGINT UNSIGNED NOT NULL,
 request_key CHAR(48) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 checkout_reference VARCHAR(40) NOT NULL UNIQUE,
 total_cents BIGINT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'awaiting_evidence',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY payment_order_request(account_id,request_key),
 INDEX business_payment_orders_account(account_id,created_at),
 FOREIGN KEY (account_id) REFERENCES accounts(id),
 CHECK(total_cents>0),
 CHECK(status IN ('awaiting_evidence','under_review','approved','rejected','clarification_requested','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_payment_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 source_request_id BIGINT UNSIGNED NOT NULL,
 subscription_id BIGINT UNSIGNED NULL,
 open_business_id BIGINT UNSIGNED NULL UNIQUE,
 business_name VARCHAR(255) NOT NULL,
 place_name VARCHAR(255) NOT NULL,
 quote_json LONGTEXT NOT NULL,
 addon_json LONGTEXT NULL,
 due_cents BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY payment_order_business(order_id,business_id),
 INDEX business_payment_order_items_subscription(subscription_id),
 FOREIGN KEY (order_id) REFERENCES business_payment_orders(id),
 FOREIGN KEY (business_id) REFERENCES businesses(id),
 FOREIGN KEY (source_request_id) REFERENCES business_subscriptions(id),
 FOREIGN KEY (subscription_id) REFERENCES business_subscriptions(id),
 FOREIGN KEY (open_business_id) REFERENCES businesses(id),
 CHECK(due_cents>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_payment_order_evidence (
 evidence_id BIGINT UNSIGNED PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 INDEX business_payment_order_evidence_order(order_id,evidence_id),
 FOREIGN KEY (evidence_id) REFERENCES business_subscription_payment_evidence(id),
 FOREIGN KEY (order_id) REFERENCES business_payment_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
