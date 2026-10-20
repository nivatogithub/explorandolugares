CREATE TABLE IF NOT EXISTS lead_business_links (
 lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 linked_by TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (lead_id,business_id)
);
CREATE INDEX IF NOT EXISTS lead_business_links_business ON lead_business_links(business_id);
