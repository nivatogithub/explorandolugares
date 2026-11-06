CREATE TABLE IF NOT EXISTS account_saved_places (
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 place_id INTEGER NOT NULL REFERENCES destinations(id),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(account_id,place_id)
);
