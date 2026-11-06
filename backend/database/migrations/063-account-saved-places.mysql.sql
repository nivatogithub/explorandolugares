-- Additive: preserves all accounts, catalogue, memberships and metrics.
CREATE TABLE IF NOT EXISTS account_saved_places (
 account_id BIGINT UNSIGNED NOT NULL,
 place_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(account_id,place_id),
 CONSTRAINT saved_places_account_fk FOREIGN KEY(account_id) REFERENCES accounts(id),
 CONSTRAINT saved_places_place_fk FOREIGN KEY(place_id) REFERENCES destinations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
