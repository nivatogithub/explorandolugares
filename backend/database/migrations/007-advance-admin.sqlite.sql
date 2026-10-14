CREATE TABLE advance_contacts (
 id INTEGER PRIMARY KEY AUTOINCREMENT, lead_id INTEGER UNIQUE REFERENCES leads(id) ON DELETE SET NULL,
 name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL DEFAULT '', phone VARCHAR(40) NOT NULL DEFAULT '',
 municipality VARCHAR(120) NOT NULL, relationship VARCHAR(24) NOT NULL DEFAULT 'prospect',
 notes TEXT NOT NULL, source TEXT NOT NULL, marketing_consent INTEGER NOT NULL DEFAULT 0,
 consent_evidence TEXT NOT NULL, suppressed INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
);
CREATE INDEX advance_contacts_municipality ON advance_contacts(municipality);
CREATE INDEX advance_contacts_email ON advance_contacts(email);
CREATE TABLE advance_establishments (
 id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER REFERENCES advance_contacts(id),
 name VARCHAR(160) NOT NULL, municipality VARCHAR(120) NOT NULL, category VARCHAR(32) NOT NULL,
 description TEXT NOT NULL, source TEXT NOT NULL, verification VARCHAR(24) NOT NULL DEFAULT 'pending',
 operation VARCHAR(24) NOT NULL DEFAULT 'unknown', status VARCHAR(24) NOT NULL DEFAULT 'draft',
 version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
);
CREATE TABLE advance_templates (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(160) NOT NULL, subject VARCHAR(200) NOT NULL,
 html TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
);
CREATE TABLE advance_campaigns (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(160) NOT NULL, subject VARCHAR(200) NOT NULL,
 html TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'draft', version INTEGER NOT NULL DEFAULT 1,
 approved_by VARCHAR(120), approved_at DATETIME, created_at DATETIME NOT NULL
);
CREATE TABLE advance_deliveries (
 id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER NOT NULL REFERENCES advance_campaigns(id),
 contact_id INTEGER NOT NULL REFERENCES advance_contacts(id), email VARCHAR(254) NOT NULL,
 subject VARCHAR(200) NOT NULL, html TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending',
 provider_id VARCHAR(120), updated_at DATETIME NOT NULL, UNIQUE(campaign_id,email)
);
CREATE TABLE advance_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT, actor VARCHAR(120) NOT NULL, action VARCHAR(48) NOT NULL,
 entity VARCHAR(32) NOT NULL, entity_id INTEGER NOT NULL, created_at DATETIME NOT NULL
);
