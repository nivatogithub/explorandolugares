-- Research and acquisition notes are private admin data, never public destination content.
-- Apply once after migration 003 on the destination database.
ALTER TABLE destinations ADD COLUMN internal_research_json LONGTEXT NULL;
UPDATE destinations SET internal_research_json = '{"schema_version":1}' WHERE internal_research_json IS NULL;
ALTER TABLE destinations MODIFY internal_research_json LONGTEXT NOT NULL;
