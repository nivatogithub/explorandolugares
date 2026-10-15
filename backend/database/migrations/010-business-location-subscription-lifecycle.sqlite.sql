ALTER TABLE businesses ADD COLUMN address_text TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN location_reference TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN latitude NUMERIC NULL;
ALTER TABLE businesses ADD COLUMN longitude NUMERIC NULL;
ALTER TABLE businesses ADD COLUMN location_source TEXT NULL;
ALTER TABLE businesses ADD COLUMN opening_hours TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN price_range TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN capacity_count INTEGER NULL;
ALTER TABLE businesses ADD COLUMN menu_url TEXT NOT NULL DEFAULT '';
ALTER TABLE businesses ADD COLUMN reservations_url TEXT NOT NULL DEFAULT '';

ALTER TABLE subscription_plans ADD COLUMN max_gallery_photos INTEGER NOT NULL DEFAULT 0;
UPDATE subscription_plans SET max_gallery_photos=5 WHERE code='page';
UPDATE subscription_plans SET max_gallery_photos=12 WHERE code='page_premium';

ALTER TABLE business_subscriptions ADD COLUMN publish_not_before TEXT NULL;
ALTER TABLE business_subscriptions ADD COLUMN quoted_amount NUMERIC NULL;
ALTER TABLE business_subscriptions ADD COLUMN currency TEXT NOT NULL DEFAULT 'COP';
ALTER TABLE business_subscriptions ADD COLUMN published_at TEXT NULL;
