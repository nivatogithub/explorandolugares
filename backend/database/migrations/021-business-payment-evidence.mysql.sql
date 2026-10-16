CREATE TABLE business_subscription_payment_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 storage_name VARCHAR(80) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(40) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 payment_reference VARCHAR(160) NOT NULL DEFAULT '',
 status VARCHAR(16) NOT NULL DEFAULT 'pending',
 admin_note VARCHAR(1000) NOT NULL DEFAULT '',
 reviewed_by VARCHAR(120) NULL,
 reviewed_at DATETIME NULL,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT business_payment_evidence_subscription_fk FOREIGN KEY (subscription_id) REFERENCES business_subscriptions(id) ON DELETE CASCADE,
 CONSTRAINT business_payment_evidence_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
 INDEX business_payment_evidence_status (status,submitted_at),
 INDEX business_payment_evidence_account (account_id,submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
