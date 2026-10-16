CREATE TABLE people (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 public_id TEXT NOT NULL UNIQUE,
 display_name TEXT NOT NULL,
 record_origin TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE business_person_contacts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE RESTRICT,
 status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive')),
 is_primary INTEGER NOT NULL DEFAULT 0,
 source_type TEXT NOT NULL,
 source_note TEXT NOT NULL DEFAULT '',
 ended_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(person_id,business_id)
);
CREATE INDEX business_person_list ON business_person_contacts(business_id,status,is_primary);
CREATE TABLE business_person_contact_roles (
 contact_id INTEGER NOT NULL REFERENCES business_person_contacts(id) ON DELETE CASCADE,
 role_code TEXT NOT NULL,
 PRIMARY KEY(contact_id,role_code)
);
CREATE TABLE person_channels (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
 business_id INTEGER NULL,
 channel TEXT NOT NULL,
 channel_value TEXT NOT NULL,
 channel_hash TEXT NOT NULL,
 source_type TEXT NOT NULL,
 source_url TEXT NOT NULL DEFAULT '',
 source_note TEXT NOT NULL DEFAULT '',
 marketing_consent INTEGER NOT NULL DEFAULT 0,
 campaign_allowed INTEGER NOT NULL DEFAULT 0,
 suppressed INTEGER NOT NULL DEFAULT 0,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(person_id,channel_hash),
 FOREIGN KEY(person_id,business_id) REFERENCES business_person_contacts(person_id,business_id) ON DELETE CASCADE
);
CREATE INDEX person_channel_business ON person_channels(person_id,business_id);
CREATE TABLE account_person_links (
 account_id INTEGER PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
 person_id INTEGER NOT NULL UNIQUE REFERENCES people(id) ON DELETE RESTRICT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
