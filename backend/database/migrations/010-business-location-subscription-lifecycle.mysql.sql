ALTER TABLE businesses
 ADD COLUMN address_text VARCHAR(300) NOT NULL DEFAULT '' AFTER category_code,
 ADD COLUMN location_reference VARCHAR(500) NOT NULL DEFAULT '' AFTER address_text,
 ADD COLUMN latitude DECIMAL(10,7) NULL AFTER location_reference,
 ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
 ADD COLUMN location_source VARCHAR(16) NULL AFTER longitude,
 ADD COLUMN opening_hours TEXT NULL AFTER location_source,
 ADD COLUMN price_range VARCHAR(100) NOT NULL DEFAULT '' AFTER opening_hours,
 ADD COLUMN capacity_count SMALLINT UNSIGNED NULL AFTER price_range,
 ADD COLUMN menu_url VARCHAR(500) NOT NULL DEFAULT '' AFTER capacity_count,
 ADD COLUMN reservations_url VARCHAR(500) NOT NULL DEFAULT '' AFTER menu_url;

ALTER TABLE subscription_plans
 ADD COLUMN max_gallery_photos SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER has_business_page;

UPDATE subscription_plans SET max_gallery_photos=5 WHERE code='page';
UPDATE subscription_plans SET max_gallery_photos=12 WHERE code='page_premium';

ALTER TABLE business_subscriptions
 ADD COLUMN publish_not_before DATE NULL AFTER billing_frequency,
 ADD COLUMN quoted_amount DECIMAL(12,2) NULL AFTER publish_not_before,
 ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'COP' AFTER quoted_amount,
 ADD COLUMN published_at DATETIME NULL AFTER reviewed_at;
