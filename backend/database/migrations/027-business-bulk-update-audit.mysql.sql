CREATE TABLE business_bulk_update_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id CHAR(32) NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 actor VARCHAR(180) NOT NULL,
 before_json LONGTEXT NOT NULL,
 after_json LONGTEXT NOT NULL,
 changes_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX business_bulk_audit_batch (batch_id),
 INDEX business_bulk_audit_business (business_id,created_at),
 CONSTRAINT business_bulk_audit_business_fk FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
