CREATE TABLE account_password_resets (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 token_hash TEXT NOT NULL UNIQUE,
 created_by TEXT NOT NULL DEFAULT 'admin',
 expires_at TEXT NOT NULL,
 used_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX account_password_resets_account ON account_password_resets(account_id,used_at,expires_at);
