ALTER TABLE business_subscriptions ADD COLUMN plan_name VARCHAR(120) NOT NULL DEFAULT '';
CREATE TABLE business_follow_up (
 business_id BIGINT UNSIGNED PRIMARY KEY, FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,
 participation_type VARCHAR(24) NOT NULL DEFAULT 'unclassified',
 free_confirmed INTEGER NOT NULL DEFAULT 0,
 validation_notes TEXT NOT NULL,
 publication_blockers TEXT NOT NULL,
 offer_notes TEXT NOT NULL,
 next_action TEXT NOT NULL,
 follow_up_on DATE,
 version INTEGER NOT NULL DEFAULT 1,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
