ALTER TABLE businesses ADD COLUMN location_map_url VARCHAR(1000) NOT NULL DEFAULT '' AFTER location_source,
 ADD COLUMN location_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER location_map_url;
-- Premium has at most three approved public photos total: one cover and two gallery images.
UPDATE subscription_plans SET max_gallery_photos=0 WHERE code='page';
UPDATE subscription_plans SET max_gallery_photos=2 WHERE code='page_premium';

CREATE TABLE accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) NOT NULL UNIQUE,
 account_type VARCHAR(16) NOT NULL,
 display_name VARCHAR(180) NOT NULL,
 email VARCHAR(254) NOT NULL,
 password_hash VARCHAR(255) NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending_activation',
 email_verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY accounts_email (email),
 INDEX accounts_type_status (account_type,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_identities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(24) NOT NULL,
 provider_subject VARCHAR(255) NOT NULL,
 email_at_provider VARCHAR(254) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY account_identity_provider_subject (provider,provider_subject),
 UNIQUE KEY account_identity_account_provider (account_id,provider),
 CONSTRAINT account_identity_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_account_memberships (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(16) NOT NULL DEFAULT 'owner',
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY business_membership_account_business (account_id,business_id),
 INDEX business_membership_business (business_id,status),
 CONSTRAINT business_membership_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
 CONSTRAINT business_membership_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
