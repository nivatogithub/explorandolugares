CREATE TABLE business_categories (
 business_id INTEGER NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
 category_code TEXT NOT NULL REFERENCES service_categories(code) ON DELETE RESTRICT,
 PRIMARY KEY(business_id,category_code)
);
CREATE INDEX business_categories_category ON business_categories(category_code,business_id);
