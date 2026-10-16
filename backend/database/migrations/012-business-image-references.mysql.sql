ALTER TABLE businesses
 ADD COLUMN image_assistance_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER reservations_url;

CREATE TABLE business_image_references (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 storage_name VARCHAR(100) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(32) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 width SMALLINT UNSIGNED NOT NULL,
 height SMALLINT UNSIGNED NOT NULL,
 review_status VARCHAR(24) NOT NULL DEFAULT 'received',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT business_image_reference_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE,
 INDEX business_image_reference_business (business_id,created_at),
 CHECK (review_status IN ('received','used_for_draft','not_used'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
