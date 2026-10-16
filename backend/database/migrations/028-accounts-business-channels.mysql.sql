ALTER TABLE business_account_memberships ADD COLUMN active_business_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='active' THEN business_id ELSE NULL END) STORED, ADD UNIQUE KEY business_one_active_account (active_business_id);
ALTER TABLE accounts ADD COLUMN contact_phone VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE business_research_contacts ADD COLUMN recipient_label VARCHAR(180) NOT NULL DEFAULT '';
