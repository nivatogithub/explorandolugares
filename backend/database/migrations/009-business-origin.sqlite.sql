ALTER TABLE businesses ADD COLUMN record_origin TEXT NOT NULL DEFAULT 'public_import';
CREATE INDEX businesses_origin ON businesses(record_origin,publication_status);
