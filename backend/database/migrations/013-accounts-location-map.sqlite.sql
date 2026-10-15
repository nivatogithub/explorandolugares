ALTER TABLE businesses ADD COLUMN location_map_url TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN location_confirmed INTEGER NOT NULL DEFAULT 0;

CREATE TABLE accounts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 account_type TEXT NOT NULL CHECK(account_type IN ('traveler','business')),
 display_name TEXT NOT NULL,
 email TEXT NOT NULL COLLATE NOCASE UNIQUE,
 password_hash TEXT NULL,
 status TEXT NOT NULL DEFAULT 'pending_activation' CHECK(status IN ('pending_activation','active','disabled')),
 email_verified_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX accounts_type_status ON accounts(account_type,status);

CREATE TABLE account_identities (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 provider TEXT NOT NULL CHECK(provider IN ('google','facebook','instagram','apple')),
 provider_subject TEXT NOT NULL,
 email_at_provider TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(provider,provider_subject),
 UNIQUE(account_id,provider)
);

CREATE TABLE business_account_memberships (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 role TEXT NOT NULL DEFAULT 'owner' CHECK(role IN ('owner','manager','editor')),
 status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','revoked')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(account_id,business_id)
);
CREATE INDEX business_membership_business ON business_account_memberships(business_id,status);

-- Premium includes at most three approved public photos total: one cover and two gallery images.
UPDATE subscription_plans SET max_gallery_photos=0 WHERE code='page';
UPDATE subscription_plans SET max_gallery_photos=2 WHERE code='page_premium';
