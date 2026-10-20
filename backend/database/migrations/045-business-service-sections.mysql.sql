CREATE TABLE IF NOT EXISTS business_service_sections (
 business_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(5) NOT NULL,
 section_code VARCHAR(24) NOT NULL,
 description TEXT NOT NULL,
 PRIMARY KEY (business_id,locale,section_code),
 CONSTRAINT fk_service_sections_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
