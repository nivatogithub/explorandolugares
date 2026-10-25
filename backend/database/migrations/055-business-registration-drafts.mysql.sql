CREATE TABLE IF NOT EXISTS business_registration_drafts (
 id CHAR(48) PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 payload_json MEDIUMTEXT NOT NULL,
 wizard_step SMALLINT NOT NULL DEFAULT 1,
 revision INT NOT NULL DEFAULT 1,
 status VARCHAR(16) NOT NULL DEFAULT 'editing',
 business_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX registration_drafts_account(account_id,status),
 FOREIGN KEY(account_id) REFERENCES accounts(id),
 FOREIGN KEY(business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
