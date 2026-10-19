CREATE TABLE IF NOT EXISTS account_access_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 event_type TEXT NOT NULL CHECK(event_type IN ('closed','suspended','restored','reactivated','initialized')),
 actor_account_id INTEGER NULL REFERENCES accounts(id),
 reason TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS account_access_events_account ON account_access_events(account_id,id);
CREATE TABLE IF NOT EXISTS account_reactivations (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id),
 token_hash TEXT NOT NULL UNIQUE,
 expires_at TEXT NOT NULL,
 used_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS account_reactivations_account ON account_reactivations(account_id,used_at);

-- Preserve the state of existing accounts without editing accounts or business data.
INSERT INTO account_access_events(account_id,event_type,actor_account_id,reason)
SELECT a.id,CASE WHEN a.status='disabled' THEN 'suspended' ELSE 'initialized' END,NULL,
 CASE WHEN a.status='disabled' THEN 'Acceso deshabilitado antes de la migración, requiere revisión administrativa' ELSE 'Estado existente conservado al incorporar el historial de acceso' END
FROM accounts a WHERE NOT EXISTS (SELECT 1 FROM account_access_events e WHERE e.account_id=a.id);
