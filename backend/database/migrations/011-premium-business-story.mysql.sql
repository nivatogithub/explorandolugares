ALTER TABLE business_translations
 ADD COLUMN brand_story TEXT NULL AFTER description,
 ADD COLUMN featured_services TEXT NULL AFTER brand_story;
