CREATE TABLE account_residences (
 account_id INTEGER PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
 country_code TEXT NOT NULL, region_code TEXT NOT NULL DEFAULT '',region_name TEXT NOT NULL,
 city_code TEXT NOT NULL DEFAULT '',city_name TEXT NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX account_residence_location ON account_residences(country_code,region_code,city_code);
