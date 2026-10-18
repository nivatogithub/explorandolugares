-- Private self-reported residence. Existing accounts remain unknown until updated.
CREATE TABLE account_residences (
 account_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 country_code CHAR(2) NOT NULL,
 region_code VARCHAR(20) NOT NULL DEFAULT '',
 region_name VARCHAR(120) NOT NULL,
 city_code VARCHAR(20) NOT NULL DEFAULT '',
 city_name VARCHAR(120) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT account_residence_account_fk FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE CASCADE,
 INDEX account_residence_location(country_code,region_code,city_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
