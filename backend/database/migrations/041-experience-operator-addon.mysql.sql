CREATE TABLE experience_operator_prices (
 billing_frequency VARCHAR(16) NOT NULL PRIMARY KEY,
 amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'COP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO experience_operator_prices(billing_frequency,amount,currency) VALUES
 ('monthly',29900,'COP'),('quarterly',74750,'COP'),('semiannual',155480,'COP'),('annual',299000,'COP');
CREATE TABLE business_experience_subscriptions (
 subscription_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 experience_id BIGINT UNSIGNED NOT NULL,
 quoted_amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'COP',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(subscription_id) REFERENCES business_subscriptions(id) ON DELETE CASCADE,
 FOREIGN KEY(experience_id) REFERENCES points_of_interest(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX business_experience_lookup ON business_experience_subscriptions(experience_id);
