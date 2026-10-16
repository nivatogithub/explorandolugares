CREATE TABLE business_email_permissions (
 email VARCHAR(254) NOT NULL PRIMARY KEY,
 decision VARCHAR(24) NOT NULL,
 evidence TEXT NOT NULL,
 actor VARCHAR(120) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE business_email_campaigns (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name VARCHAR(180) NOT NULL,
 subject VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 action_url VARCHAR(1000) NOT NULL DEFAULT '',
 recipients_json TEXT NOT NULL,
 sender_json TEXT NOT NULL,
 content_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'draft',
 busy INTEGER NOT NULL DEFAULT 0,
 sync_cursor INT NOT NULL DEFAULT 0,
 provider_folder_id INTEGER NULL,
 provider_list_id INTEGER NULL,
 provider_campaign_id INTEGER NULL,
 approved_by VARCHAR(120) NOT NULL DEFAULT '',
 approved_at DATETIME NULL,
 last_error VARCHAR(500) NOT NULL DEFAULT '',
 stats_json TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE business_email_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 campaign_id INTEGER NULL,
 actor VARCHAR(120) NOT NULL,
 action VARCHAR(40) NOT NULL,
 detail TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT business_email_audit_campaign_fk FOREIGN KEY (campaign_id) REFERENCES business_email_campaigns(id)
);
