-- Owner-approved prepaid periods. Only the current catalog is recalculated.
-- Historical subscriptions, evidence and captured application quotes are untouched.
CREATE TABLE IF NOT EXISTS subscription_pricing_policy (
 period_code VARCHAR(16) NOT NULL PRIMARY KEY,
 billable_months DECIMAL(4,2) NOT NULL,
 CONSTRAINT subscription_pricing_period_check CHECK (period_code IN ('monthly','quarterly','semiannual','annual')),
 CONSTRAINT subscription_pricing_months_check CHECK (billable_months > 0 AND billable_months <= CASE period_code WHEN 'monthly' THEN 1 WHEN 'quarterly' THEN 3 WHEN 'semiannual' THEN 6 WHEN 'annual' THEN 12 END)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO subscription_pricing_policy(period_code,billable_months) VALUES
 ('monthly',1.00),('quarterly',2.50),('semiannual',5.20),('annual',10.00)
ON DUPLICATE KEY UPDATE billable_months=VALUES(billable_months);
INSERT INTO subscription_plan_prices(plan_code,billing_frequency,amount,currency)
SELECT monthly.plan_code,policy.period_code,ROUND(monthly.amount*policy.billable_months,2),'COP'
FROM subscription_plan_prices monthly CROSS JOIN subscription_pricing_policy policy
WHERE monthly.plan_code IN ('featured','page','page_premium')
 AND monthly.billing_frequency='monthly' AND monthly.currency='COP' AND monthly.amount>0
 AND policy.period_code<>'monthly'
ON DUPLICATE KEY UPDATE amount=VALUES(amount),currency=VALUES(currency);
