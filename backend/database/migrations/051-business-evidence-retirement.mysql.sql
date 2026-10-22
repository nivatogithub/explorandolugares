CREATE TABLE IF NOT EXISTS business_evidence_retirement (
 evidence_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 document_sha256 VARCHAR(64) NOT NULL,
 reviewer VARCHAR(180) NOT NULL,
 verified_at DATETIME NOT NULL,
 deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
