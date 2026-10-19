CREATE TABLE IF NOT EXISTS account_access_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(20) NOT NULL,
 actor_account_id BIGINT UNSIGNED NULL,
 reason VARCHAR(500) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX account_access_events_account (account_id,id),
 FOREIGN KEY (account_id) REFERENCES accounts(id),
 FOREIGN KEY (actor_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS account_reactivations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX account_reactivations_account (account_id,used_at),
 FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the state of existing accounts without editing accounts or business data.
INSERT INTO account_access_events(account_id,event_type,actor_account_id,reason)
SELECT a.id,CASE WHEN a.status='disabled' THEN 'suspended' ELSE 'initialized' END,NULL,
 CASE WHEN a.status='disabled' THEN 'Acceso deshabilitado antes de la migración, requiere revisión administrativa' ELSE 'Estado existente conservado al incorporar el historial de acceso' END
FROM accounts a WHERE NOT EXISTS (SELECT 1 FROM account_access_events e WHERE e.account_id=a.id);
