-- Catalogue only: existing subscriptions and payments are never updated.
-- One atomic statement updates all four frequencies together.
UPDATE subscription_plan_prices
SET amount=CASE billing_frequency
 WHEN 'monthly' THEN 79900.00
 WHEN 'quarterly' THEN 199750.00
 WHEN 'semiannual' THEN 415480.00
 WHEN 'annual' THEN 799000.00
 END,currency='COP'
WHERE plan_code='page' AND billing_frequency IN ('monthly','quarterly','semiannual','annual');
