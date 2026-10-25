-- Current description budgets; do not truncate content or shrink storage.
UPDATE subscription_plans
SET max_summary_chars=CASE code WHEN 'featured' THEN 100 WHEN 'page' THEN 300 WHEN 'page_premium' THEN 600 ELSE 0 END
WHERE code IN ('free','featured','page','page_premium');
