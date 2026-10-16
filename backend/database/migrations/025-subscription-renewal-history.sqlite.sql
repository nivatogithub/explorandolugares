ALTER TABLE business_subscriptions
 ADD COLUMN renewal_of_subscription_id INTEGER NULL REFERENCES business_subscriptions(id) ON DELETE SET NULL;
CREATE UNIQUE INDEX business_subscription_renewal_unique ON business_subscriptions(renewal_of_subscription_id);
