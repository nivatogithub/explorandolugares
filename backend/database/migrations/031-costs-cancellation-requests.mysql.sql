CREATE TABLE operating_costs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cost_date DATE NOT NULL,
 category VARCHAR(32) NOT NULL,
 amount_cop DECIMAL(14,2) NOT NULL,
 destination_id BIGINT UNSIGNED NULL,
 description VARCHAR(500) NOT NULL,
 created_by VARCHAR(120) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_at DATETIME NULL,
 void_reason VARCHAR(500) NULL,
 INDEX operating_costs_period (cost_date,voided_at),
 INDEX operating_costs_destination (destination_id,cost_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE subscription_cancellation_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id BIGINT UNSIGNED NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 reason_code VARCHAR(32) NOT NULL,
 reason_text VARCHAR(1000) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 review_note VARCHAR(1000) NULL,
 UNIQUE KEY cancellation_subscription (subscription_id),
 INDEX cancellation_queue (status,created_at),
 INDEX cancellation_account (account_id,business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
