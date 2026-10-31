-- Approved 2026-10-31. Current catalogue only; never rewrite captured quotes,
-- subscriptions, coupon redemptions, receipts, accounts or statistics.
INSERT INTO subscription_pricing_policy(period_code,billable_months) VALUES
 ('monthly',1.00),('quarterly',2.70),('semiannual',5.10),('annual',9.60)
ON DUPLICATE KEY UPDATE billable_months=VALUES(billable_months);
INSERT INTO subscription_plan_prices(plan_code,billing_frequency,amount,currency) VALUES
 ('featured','monthly',25000.00,'COP'),('featured','quarterly',67500.00,'COP'),
 ('featured','semiannual',127500.00,'COP'),('featured','annual',240000.00,'COP'),
 ('page','monthly',40000.00,'COP'),('page','quarterly',108000.00,'COP'),
 ('page','semiannual',204000.00,'COP'),('page','annual',384000.00,'COP'),
 ('page_premium','monthly',60000.00,'COP'),('page_premium','quarterly',162000.00,'COP'),
 ('page_premium','semiannual',306000.00,'COP'),('page_premium','annual',576000.00,'COP')
ON DUPLICATE KEY UPDATE amount=VALUES(amount),currency=VALUES(currency);
INSERT INTO experience_operator_prices(billing_frequency,amount,currency) VALUES
 ('monthly',20000.00,'COP'),('quarterly',54000.00,'COP'),
 ('semiannual',102000.00,'COP'),('annual',192000.00,'COP')
ON DUPLICATE KEY UPDATE amount=VALUES(amount),currency=VALUES(currency);
