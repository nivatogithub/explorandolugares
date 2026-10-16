CREATE TABLE business_email_permissions (
 email VARCHAR(254) NOT NULL PRIMARY KEY,
 decision VARCHAR(24) NOT NULL,
 evidence TEXT NOT NULL,
 actor VARCHAR(120) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE business_email_campaigns (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 subject VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 action_url VARCHAR(1000) NOT NULL DEFAULT '',
 recipients_json MEDIUMTEXT NOT NULL,
 sender_json TEXT NOT NULL,
 content_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'draft',
 busy TINYINT NOT NULL DEFAULT 0,
 sync_cursor INT NOT NULL DEFAULT 0,
 provider_folder_id BIGINT UNSIGNED NULL,
 provider_list_id BIGINT UNSIGNED NULL,
 provider_campaign_id BIGINT UNSIGNED NULL,
 approved_by VARCHAR(120) NOT NULL DEFAULT '',
 approved_at DATETIME NULL,
 last_error VARCHAR(500) NOT NULL DEFAULT '',
 stats_json TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE business_email_audit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NULL,
 actor VARCHAR(120) NOT NULL,
 action VARCHAR(40) NOT NULL,
 detail TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX business_email_audit_campaign (campaign_id),
 CONSTRAINT business_email_audit_campaign_fk FOREIGN KEY (campaign_id) REFERENCES business_email_campaigns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
