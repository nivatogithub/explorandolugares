CREATE TABLE business_account_application_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 evidence_type VARCHAR(32) NOT NULL,
 storage_name VARCHAR(80) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(40) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 verified_by VARCHAR(120) NULL,
 verified_at DATETIME NULL,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY business_app_evidence_type (application_id,evidence_type),
 CONSTRAINT business_app_evidence_application_fk FOREIGN KEY (application_id) REFERENCES business_account_applications(id) ON DELETE CASCADE,
 INDEX business_app_evidence_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
