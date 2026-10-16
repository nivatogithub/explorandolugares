CREATE UNIQUE INDEX business_one_active_account ON business_account_memberships(business_id) WHERE status='active';
ALTER TABLE accounts ADD COLUMN contact_phone TEXT NOT NULL DEFAULT '';
ALTER TABLE business_research_contacts ADD COLUMN recipient_label TEXT NOT NULL DEFAULT '';
