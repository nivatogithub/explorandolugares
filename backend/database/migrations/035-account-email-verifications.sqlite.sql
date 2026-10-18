CREATE TABLE account_email_verifications (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 token_hash TEXT NOT NULL UNIQUE,
 expires_at TEXT NOT NULL,
 used_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX account_email_verifications_account ON account_email_verifications(account_id,used_at,expires_at);
