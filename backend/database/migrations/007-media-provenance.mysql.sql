-- Run once after migration 006. Private image/source traceability for AI assets.
ALTER TABLE media_assets
  ADD COLUMN provenance_json LONGTEXT NULL AFTER license_url;
