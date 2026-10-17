CREATE TABLE operating_cost_evidence (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 cost_id INTEGER NOT NULL,
 storage_name VARCHAR(80) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 file_size INTEGER NOT NULL,
 sha256 CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_by VARCHAR(120) NOT NULL,
 CONSTRAINT operating_cost_evidence_fk FOREIGN KEY (cost_id) REFERENCES operating_costs(id)
);
