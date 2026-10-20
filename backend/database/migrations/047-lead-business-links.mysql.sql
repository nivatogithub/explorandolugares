CREATE TABLE IF NOT EXISTS lead_business_links (
 lead_id BIGINT UNSIGNED NOT NULL,
 business_id BIGINT UNSIGNED NOT NULL,
 linked_by VARCHAR(190) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (lead_id,business_id),
 INDEX lead_business_links_business (business_id),
 FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
 FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
