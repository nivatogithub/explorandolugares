CREATE TABLE IF NOT EXISTS business_promotion_codes (
 code VARCHAR(32) PRIMARY KEY,
 percentage SMALLINT NOT NULL,
 plans_json TEXT NOT NULL,
 periods_json TEXT NOT NULL,
 enabled SMALLINT NOT NULL DEFAULT 1,
 expires_at DATETIME NULL,
 max_uses INT NULL,
 created_by VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS business_promotion_redemptions (
 subscription_id BIGINT UNSIGNED PRIMARY KEY,
 code VARCHAR(32) NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 plan_code VARCHAR(32) NOT NULL,
 billing_frequency VARCHAR(32) NOT NULL,
 percentage SMALLINT NOT NULL,
 list_cents BIGINT NOT NULL,
 addon_cents BIGINT NOT NULL DEFAULT 0,
 discount_cents BIGINT NOT NULL,
 due_cents BIGINT NOT NULL,
 currency CHAR(3) NOT NULL,
 redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY promotion_once_per_business(code,business_id),
 FOREIGN KEY(code) REFERENCES business_promotion_codes(code),
 FOREIGN KEY(subscription_id) REFERENCES business_subscriptions(id),
 FOREIGN KEY(business_id) REFERENCES businesses(id),
 FOREIGN KEY(account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS business_promotion_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(32) NOT NULL,
 actor VARCHAR(120) NOT NULL,
 settings_json TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(code) REFERENCES business_promotion_codes(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
