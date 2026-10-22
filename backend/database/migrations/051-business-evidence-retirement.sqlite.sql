CREATE TABLE IF NOT EXISTS business_evidence_retirement (
 evidence_id INTEGER NOT NULL PRIMARY KEY,
 document_sha256 VARCHAR(64) NOT NULL,
 reviewer VARCHAR(180) NOT NULL,
 verified_at DATETIME NOT NULL,
 deleted_at DATETIME NULL
);
