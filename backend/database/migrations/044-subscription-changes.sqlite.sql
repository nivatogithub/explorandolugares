CREATE TABLE subscription_changes (
 subscription_id INTEGER PRIMARY KEY REFERENCES business_subscriptions(id),
 source_subscription_id INTEGER NOT NULL REFERENCES business_subscriptions(id),
 change_kind TEXT NOT NULL,
 quoted_at TEXT NOT NULL,
 term_starts_at TEXT NOT NULL,
 effective_at TEXT NULL,
 original_ends_at TEXT NOT NULL,
 source_full_cents INTEGER NOT NULL,
 target_full_cents INTEGER NOT NULL,
 credit_cents INTEGER NOT NULL,
 target_remaining_cents INTEGER NOT NULL,
 due_cents INTEGER NOT NULL,
 currency TEXT NOT NULL
);
CREATE INDEX subscription_changes_source ON subscription_changes(source_subscription_id);
