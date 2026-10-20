CREATE TABLE IF NOT EXISTS business_service_sections (
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 locale VARCHAR(5) NOT NULL,
 section_code VARCHAR(24) NOT NULL,
 description TEXT NOT NULL,
 PRIMARY KEY (business_id,locale,section_code)
);
