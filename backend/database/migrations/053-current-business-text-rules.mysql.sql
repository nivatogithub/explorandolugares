-- Widen the physical capacity when necessary. Never shrink an existing TEXT/wider column.
SET @el_summary_capacity=(SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='business_translations' AND COLUMN_NAME='summary');
SET @el_summary_upgrade=IF(@el_summary_capacity<1200,'ALTER TABLE business_translations MODIFY COLUMN summary VARCHAR(1200) NOT NULL DEFAULT ''''','DO 0');
PREPARE el_summary_statement FROM @el_summary_upgrade;
EXECUTE el_summary_statement;
DEALLOCATE PREPARE el_summary_statement;
-- Plan configuration only. Do not rewrite business content or live metrics.
UPDATE subscription_plans
SET max_summary_chars=CASE code WHEN 'featured' THEN 300 WHEN 'page' THEN 450 WHEN 'page_premium' THEN 1200 ELSE 0 END,
    max_description_chars=0,max_brand_story_chars=0,max_featured_services_chars=0
WHERE code IN ('free','featured','page','page_premium');
