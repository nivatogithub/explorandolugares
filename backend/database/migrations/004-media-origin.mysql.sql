-- Run once on databases created with migration 003 before deploying the AI/local-image upload editor.
ALTER TABLE media_assets
  ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT 'external' AFTER id;
