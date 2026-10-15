ALTER TABLE subscription_plans
 ADD COLUMN max_summary_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_gallery_photos,
 ADD COLUMN max_description_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_summary_chars,
 ADD COLUMN max_brand_story_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_description_chars,
 ADD COLUMN max_featured_services_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_brand_story_chars;

ALTER TABLE business_translations
 MODIFY COLUMN summary VARCHAR(1000) NOT NULL DEFAULT '';

UPDATE subscription_plans SET max_summary_chars=0,max_description_chars=0,max_brand_story_chars=0,max_featured_services_chars=0 WHERE code='free';
UPDATE subscription_plans SET max_summary_chars=300,max_description_chars=0,max_brand_story_chars=0,max_featured_services_chars=0 WHERE code='basic';
UPDATE subscription_plans SET max_summary_chars=500,max_description_chars=1000,max_brand_story_chars=0,max_featured_services_chars=0 WHERE code='featured';
UPDATE subscription_plans SET max_summary_chars=750,max_description_chars=2000,max_brand_story_chars=0,max_featured_services_chars=0 WHERE code='page';
UPDATE subscription_plans SET max_summary_chars=1000,max_description_chars=3500,max_brand_story_chars=1500,max_featured_services_chars=1500 WHERE code='page_premium';
