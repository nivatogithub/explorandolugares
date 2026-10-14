-- For an existing local SQLite preview database created before the media-origin field.
ALTER TABLE media_assets
  ADD COLUMN origin TEXT NOT NULL DEFAULT 'external';
