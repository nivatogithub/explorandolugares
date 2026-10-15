ALTER TABLE businesses ADD COLUMN image_assistance_requested INTEGER NOT NULL DEFAULT 0;

CREATE TABLE business_image_references (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 business_id INTEGER NOT NULL,
 storage_name TEXT NOT NULL UNIQUE,
 original_name TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL,
 width INTEGER NOT NULL,
 height INTEGER NOT NULL,
 review_status TEXT NOT NULL DEFAULT 'received' CHECK(review_status IN ('received','used_for_draft','not_used')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
CREATE INDEX business_image_references_business ON business_image_references(business_id,created_at);
