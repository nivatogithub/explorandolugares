CREATE TABLE business_claim_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 claimant_name VARCHAR(180) NOT NULL,
 email VARCHAR(254) NOT NULL DEFAULT '',
 phone VARCHAR(40) NOT NULL DEFAULT '',
 relationship_code VARCHAR(32) NOT NULL,
 message VARCHAR(1000) NOT NULL DEFAULT '',
 consent_version VARCHAR(40) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 admin_note VARCHAR(1000) NOT NULL DEFAULT '',
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX business_claim_queue (status,created_at),
 INDEX business_claim_target (business_id,status),
 CONSTRAINT business_claim_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
