CREATE TABLE IF NOT EXISTS lead_reply_operations (
 id TEXT PRIMARY KEY,
 lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE RESTRICT,
 status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','sending','accepted','failed','uncertain')),
 recipient TEXT NOT NULL,
 sender_email TEXT NOT NULL,
 sender_name TEXT NOT NULL,
 reply_to TEXT NOT NULL,
 subject TEXT NOT NULL,
 message_text TEXT NOT NULL,
 html_body TEXT NOT NULL,
 text_body TEXT NOT NULL,
 template_version TEXT NOT NULL,
 content_hash TEXT NOT NULL,
 created_by TEXT NOT NULL,
 confirmed_by TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 claimed_at TEXT NULL,
 completed_at TEXT NULL,
 provider_message_id TEXT NULL,
 result_code TEXT NULL,
 reconciled_at TEXT NULL,
 reconciled_by TEXT NULL,
 reconciliation_note TEXT NULL
);
CREATE INDEX IF NOT EXISTS lead_reply_operations_lead ON lead_reply_operations(lead_id,created_at);
CREATE TABLE IF NOT EXISTS lead_reply_attempts (
 operation_id TEXT PRIMARY KEY REFERENCES lead_reply_operations(id) ON DELETE RESTRICT,
 started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at TEXT NULL,
 outcome TEXT NOT NULL DEFAULT 'sending',
 http_status INTEGER NULL
);
