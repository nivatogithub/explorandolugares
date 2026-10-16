CREATE TABLE business_categories (
 business_id BIGINT UNSIGNED NOT NULL,
 category_code VARCHAR(40) NOT NULL,
 PRIMARY KEY(business_id,category_code),
 INDEX business_categories_category(category_code,business_id),
 CONSTRAINT business_categories_business_fk FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,
 CONSTRAINT business_categories_category_fk FOREIGN KEY(category_code) REFERENCES service_categories(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
