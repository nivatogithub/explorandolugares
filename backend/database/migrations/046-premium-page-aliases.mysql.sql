CREATE TABLE IF NOT EXISTS business_page_aliases (
 alias VARCHAR(70) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 is_current TINYINT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX business_page_alias_business (business_id,is_current),
 FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
