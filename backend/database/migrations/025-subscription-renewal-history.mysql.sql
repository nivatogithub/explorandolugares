ALTER TABLE business_subscriptions
 ADD COLUMN renewal_of_subscription_id BIGINT UNSIGNED NULL AFTER published_at,
 ADD UNIQUE INDEX business_subscription_renewal_unique (renewal_of_subscription_id),
 ADD CONSTRAINT business_subscription_renewal_fk
  FOREIGN KEY (renewal_of_subscription_id) REFERENCES business_subscriptions(id) ON DELETE SET NULL;
