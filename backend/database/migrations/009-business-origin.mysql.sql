ALTER TABLE businesses
 ADD COLUMN record_origin VARCHAR(24) NOT NULL DEFAULT 'public_import' AFTER category_code,
 ADD INDEX businesses_origin (record_origin,publication_status);
