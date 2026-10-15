CREATE TABLE business_account_applications (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 application_type TEXT NOT NULL CHECK(application_type IN ('claim_existing','request_new')),
 business_id INTEGER NULL REFERENCES businesses(id) ON DELETE SET NULL,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE RESTRICT,
 category_code TEXT NOT NULL REFERENCES service_categories(code) ON DELETE RESTRICT,
 business_name TEXT NOT NULL,
 relationship_code TEXT NOT NULL,
 contact_phone TEXT NOT NULL DEFAULT '',
 message TEXT NOT NULL DEFAULT '',
 consent_version TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending',
 admin_note TEXT NOT NULL DEFAULT '',
 reviewed_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX business_account_app_queue ON business_account_applications(status,created_at);
CREATE INDEX business_account_app_account ON business_account_applications(account_id,status);
CREATE INDEX business_account_app_business ON business_account_applications(business_id,status);
