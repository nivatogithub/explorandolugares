-- Current plan configuration only. Do not rewrite business content or live metrics.
UPDATE subscription_plans
SET max_summary_chars=CASE code WHEN 'featured' THEN 300 WHEN 'page' THEN 450 WHEN 'page_premium' THEN 1200 ELSE 0 END,
    max_description_chars=0,max_brand_story_chars=0,max_featured_services_chars=0
WHERE code IN ('free','featured','page','page_premium');
