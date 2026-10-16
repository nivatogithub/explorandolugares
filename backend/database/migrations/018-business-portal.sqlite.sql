ALTER TABLE business_claim_requests ADD COLUMN account_id INTEGER NULL REFERENCES accounts(id) ON DELETE SET NULL;
CREATE INDEX business_claim_account ON business_claim_requests(account_id,status);
