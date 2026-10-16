CREATE TABLE people (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) NOT NULL UNIQUE,
 display_name VARCHAR(180) NOT NULL,
 record_origin VARCHAR(40) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_person_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 person_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 source_type VARCHAR(40) NOT NULL,
 source_note VARCHAR(1000) NOT NULL DEFAULT '',
 ended_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY business_person_once (person_id,business_id),
 INDEX business_person_list (business_id,status,is_primary),
 CONSTRAINT business_person_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
 CONSTRAINT business_person_person_fk FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_person_contact_roles (
 contact_id BIGINT UNSIGNED NOT NULL,
 role_code VARCHAR(32) NOT NULL,
 PRIMARY KEY (contact_id,role_code),
 CONSTRAINT business_person_role_fk FOREIGN KEY (contact_id) REFERENCES business_person_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE person_channels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 person_id BIGINT UNSIGNED NOT NULL,
 business_id BIGINT UNSIGNED NULL,
 channel VARCHAR(24) NOT NULL,
 channel_value VARCHAR(500) NOT NULL,
 channel_hash CHAR(64) NOT NULL,
 source_type VARCHAR(40) NOT NULL,
 source_url VARCHAR(1000) NOT NULL DEFAULT '',
 source_note VARCHAR(1000) NOT NULL DEFAULT '',
 marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
 campaign_allowed TINYINT(1) NOT NULL DEFAULT 0,
 suppressed TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY person_channel_once (person_id,channel_hash),
 INDEX person_channel_business (person_id,business_id),
 CONSTRAINT person_channel_person_fk FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
 CONSTRAINT person_channel_relationship_fk FOREIGN KEY (person_id,business_id) REFERENCES business_person_contacts(person_id,business_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_person_links (
 account_id BIGINT UNSIGNED PRIMARY KEY,
 person_id BIGINT UNSIGNED NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT account_person_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
 CONSTRAINT account_person_person_fk FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
