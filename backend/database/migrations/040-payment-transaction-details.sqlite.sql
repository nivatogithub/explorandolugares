ALTER TABLE business_subscription_payment_evidence ADD COLUMN checkout_reference VARCHAR(40) NULL;
ALTER TABLE business_subscription_payment_evidence ADD COLUMN transaction_date DATE NULL;
ALTER TABLE business_subscription_payment_evidence ADD COLUMN paid_amount DECIMAL(14,2) NULL;
ALTER TABLE business_subscription_payment_evidence ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE business_subscription_payment_evidence ADD COLUMN source_bank VARCHAR(120) NOT NULL DEFAULT '';
CREATE UNIQUE INDEX business_payment_checkout_reference ON business_subscription_payment_evidence(checkout_reference);
