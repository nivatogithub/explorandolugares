PRAGMA foreign_keys = ON;
CREATE TABLE destinations (
 id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE,
 destination_type TEXT NOT NULL DEFAULT 'municipality', country_code TEXT NOT NULL DEFAULT 'CO',
 department TEXT NOT NULL DEFAULT '', subregion TEXT NOT NULL DEFAULT '',
 latitude REAL, longitude REAL, publication_status TEXT NOT NULL DEFAULT 'draft',
 display_order INTEGER NOT NULL DEFAULT 0, internal_research_json TEXT NOT NULL DEFAULT '{"schema_version":1}',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX destinations_public_order ON destinations(publication_status,display_order);
CREATE TABLE destination_translations (
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, display_name TEXT NOT NULL, eyebrow TEXT NOT NULL DEFAULT '',
 headline TEXT NOT NULL, intro TEXT NOT NULL, meta_description TEXT NOT NULL DEFAULT '',
 PRIMARY KEY(destination_id,locale)
);
CREATE TABLE destination_story_blocks (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 section_key TEXT NOT NULL DEFAULT 'history', display_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX destination_story_order ON destination_story_blocks(destination_id,section_key,display_order);
CREATE TABLE destination_story_translations (
 story_block_id INTEGER NOT NULL REFERENCES destination_story_blocks(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, body TEXT NOT NULL, PRIMARY KEY(story_block_id,locale)
);
CREATE TABLE destination_reasons (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 display_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX destination_reasons_order ON destination_reasons(destination_id,display_order);
CREATE TABLE destination_reason_translations (
 reason_id INTEGER NOT NULL REFERENCES destination_reasons(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, body TEXT NOT NULL, PRIMARY KEY(reason_id,locale)
);
CREATE TABLE points_of_interest (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 slug TEXT NOT NULL, latitude REAL, longitude REAL,
 publication_status TEXT NOT NULL DEFAULT 'published', display_order INTEGER NOT NULL DEFAULT 0,
 UNIQUE(destination_id,slug)
);
CREATE INDEX poi_destination_public ON points_of_interest(destination_id,publication_status,display_order);
CREATE TABLE point_of_interest_translations (
 point_of_interest_id INTEGER NOT NULL REFERENCES points_of_interest(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, name TEXT NOT NULL, description TEXT NOT NULL,
 map_url TEXT NOT NULL DEFAULT '', PRIMARY KEY(point_of_interest_id,locale)
);
CREATE TABLE media_assets (
 id INTEGER PRIMARY KEY AUTOINCREMENT, asset_path TEXT NOT NULL UNIQUE,
 origin TEXT NOT NULL DEFAULT 'external',
 creator TEXT NOT NULL, source_url TEXT NOT NULL, license_name TEXT NOT NULL,
 license_url TEXT NOT NULL, width INTEGER, height INTEGER
);
CREATE TABLE media_asset_translations (
 media_asset_id INTEGER NOT NULL REFERENCES media_assets(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, alt_text TEXT NOT NULL DEFAULT '', caption TEXT NOT NULL DEFAULT '',
 PRIMARY KEY(media_asset_id,locale)
);
CREATE TABLE destination_media (
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 media_asset_id INTEGER NOT NULL REFERENCES media_assets(id) ON DELETE RESTRICT,
 role TEXT NOT NULL, display_order INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(destination_id,media_asset_id,role)
);
CREATE INDEX destination_media_role ON destination_media(destination_id,role,display_order);
CREATE TABLE point_of_interest_media (
 point_of_interest_id INTEGER NOT NULL REFERENCES points_of_interest(id) ON DELETE CASCADE,
 media_asset_id INTEGER NOT NULL REFERENCES media_assets(id) ON DELETE RESTRICT,
 role TEXT NOT NULL DEFAULT 'gallery', display_order INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(point_of_interest_id,media_asset_id,role)
);
CREATE INDEX poi_media_order ON point_of_interest_media(point_of_interest_id,display_order);
CREATE TABLE destination_sources (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, section_key TEXT NOT NULL DEFAULT 'destination',
 source_url TEXT NOT NULL, source_label TEXT NOT NULL, display_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX destination_sources_order ON destination_sources(destination_id,locale,section_key,display_order);
CREATE TABLE service_categories (
 code TEXT PRIMARY KEY, display_order INTEGER NOT NULL DEFAULT 0,
 is_active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE service_category_translations (
 category_code TEXT NOT NULL REFERENCES service_categories(code) ON DELETE CASCADE,
 locale TEXT NOT NULL, label TEXT NOT NULL, PRIMARY KEY(category_code,locale)
);
CREATE TABLE businesses (
 id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT NOT NULL UNIQUE,
 destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE RESTRICT,
 category_code TEXT NOT NULL REFERENCES service_categories(code) ON DELETE RESTRICT,
 slug TEXT NOT NULL, information_status TEXT NOT NULL DEFAULT 'PUBLICO',
 claim_status TEXT NOT NULL DEFAULT 'NO_RECLAMADO', operation_status TEXT NOT NULL DEFAULT 'DESCONOCIDO',
 publication_status TEXT NOT NULL DEFAULT 'draft', display_order INTEGER NOT NULL DEFAULT 0,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(destination_id,slug)
);
CREATE INDEX businesses_directory ON businesses(destination_id,publication_status,category_code,display_order);
CREATE INDEX businesses_state ON businesses(information_status,claim_status,operation_status);
CREATE TABLE business_translations (
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 locale TEXT NOT NULL, display_name TEXT NOT NULL, summary TEXT NOT NULL DEFAULT '',
 description TEXT NOT NULL, PRIMARY KEY(business_id,locale)
);
CREATE TABLE business_contacts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 channel TEXT NOT NULL, contact_value TEXT NOT NULL, is_public INTEGER NOT NULL DEFAULT 0,
 display_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX business_contact_public ON business_contacts(business_id,is_public,display_order);
CREATE TABLE business_media (
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 media_asset_id INTEGER NOT NULL REFERENCES media_assets(id) ON DELETE RESTRICT,
 display_order INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(business_id,media_asset_id)
);
CREATE TABLE business_sources (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 source_url TEXT NOT NULL, checked_at TEXT, note TEXT NOT NULL DEFAULT ''
);
CREATE INDEX business_sources_business ON business_sources(business_id);
