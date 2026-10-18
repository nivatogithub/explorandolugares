-- Current catalog only; historical subscription/application quotes are immutable.
CREATE TABLE IF NOT EXISTS subscription_pricing_policy (
 period_code TEXT NOT NULL PRIMARY KEY CHECK (period_code IN ('monthly','quarterly','semiannual','annual')),
 billable_months NUMERIC NOT NULL CHECK (billable_months > 0 AND billable_months <= CASE period_code WHEN 'monthly' THEN 1 WHEN 'quarterly' THEN 3 WHEN 'semiannual' THEN 6 WHEN 'annual' THEN 12 END)
);
INSERT INTO subscription_pricing_policy(period_code,billable_months) VALUES
 ('monthly',1.00),('quarterly',2.50),('semiannual',5.20),('annual',10.00)
ON CONFLICT(period_code) DO UPDATE SET billable_months=excluded.billable_months;
INSERT INTO subscription_plan_prices(plan_code,billing_frequency,amount,currency)
SELECT monthly.plan_code,policy.period_code,ROUND(monthly.amount*policy.billable_months,2),'COP'
FROM subscription_plan_prices monthly CROSS JOIN subscription_pricing_policy policy
WHERE monthly.plan_code IN ('featured','page','page_premium')
 AND monthly.billing_frequency='monthly' AND monthly.currency='COP' AND monthly.amount>0
 AND policy.period_code<>'monthly'
ON CONFLICT(plan_code,billing_frequency) DO UPDATE SET amount=excluded.amount,currency=excluded.currency;
