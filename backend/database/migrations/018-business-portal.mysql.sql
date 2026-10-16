ALTER TABLE business_claim_requests
 ADD COLUMN account_id BIGINT UNSIGNED NULL AFTER business_id,
 ADD INDEX business_claim_account (account_id,status),
 ADD CONSTRAINT business_claim_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;
