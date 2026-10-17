CREATE TABLE operating_cost_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cost_id BIGINT UNSIGNED NOT NULL,
 storage_name VARCHAR(80) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_by VARCHAR(120) NOT NULL,
 INDEX operating_cost_evidence_cost (cost_id),
 CONSTRAINT operating_cost_evidence_fk FOREIGN KEY (cost_id) REFERENCES operating_costs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
