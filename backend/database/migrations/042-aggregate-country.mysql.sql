-- Aggregate country counts. No IP, browser, visitor or session columns.
CREATE TABLE IF NOT EXISTS el_basic_country_daily (
 page_day VARCHAR(10) NOT NULL,
 scope_code VARCHAR(2) NOT NULL,
 destination_slug VARCHAR(160) NOT NULL,
 country_code VARCHAR(2) NOT NULL,
 page_views BIGINT NOT NULL DEFAULT 0,
 PRIMARY KEY(page_day,scope_code,destination_slug,country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
