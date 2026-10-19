CREATE TABLE IF NOT EXISTS subscription_changes (
 subscription_id BIGINT UNSIGNED PRIMARY KEY,
 source_subscription_id BIGINT UNSIGNED NOT NULL,
 change_kind VARCHAR(16) NOT NULL,
 quoted_at DATETIME NOT NULL,
 term_starts_at DATETIME NOT NULL,
 effective_at DATETIME NULL,
 original_ends_at DATETIME NOT NULL,
 source_full_cents BIGINT UNSIGNED NOT NULL,
 target_full_cents BIGINT UNSIGNED NOT NULL,
 credit_cents BIGINT UNSIGNED NOT NULL,
 target_remaining_cents BIGINT UNSIGNED NOT NULL,
 due_cents BIGINT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 INDEX subscription_changes_source (source_subscription_id),
 FOREIGN KEY (subscription_id) REFERENCES business_subscriptions(id),
 FOREIGN KEY (source_subscription_id) REFERENCES business_subscriptions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
