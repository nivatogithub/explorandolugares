CREATE TABLE leads (
 id INTEGER PRIMARY KEY AUTOINCREMENT, request_key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL, business TEXT NOT NULL, municipality TEXT NOT NULL,
 category TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '', phone_type TEXT NOT NULL DEFAULT '',
 participation_version TEXT NOT NULL DEFAULT '',
 has_brand TEXT NOT NULL, brand_name TEXT NOT NULL DEFAULT '', description TEXT NOT NULL,
 interest TEXT NOT NULL, wants_contact INTEGER NOT NULL DEFAULT 0,
 consent_version TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'nuevo', created_at TEXT NOT NULL
);
CREATE INDEX leads_status_created ON leads(status,created_at);
CREATE TABLE rate_limits (bucket_key TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires_at INTEGER NOT NULL);
