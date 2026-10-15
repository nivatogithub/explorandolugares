-- Explorando Lugares: destinos editoriales y directorio público.
-- No altera leads ni rate_limits. Aplicar en una base de pruebas primero.
CREATE TABLE destinations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(100) NOT NULL UNIQUE,
 destination_type VARCHAR(24) NOT NULL DEFAULT 'municipality',
 country_code CHAR(2) NOT NULL DEFAULT 'CO',
 department VARCHAR(120) NOT NULL DEFAULT '',
 subregion VARCHAR(120) NOT NULL DEFAULT '',
 latitude DECIMAL(9,6) NULL,
 longitude DECIMAL(9,6) NULL,
 publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX destinations_public_order (publication_status,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_translations (
 destination_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 display_name VARCHAR(120) NOT NULL,
 eyebrow VARCHAR(180) NOT NULL DEFAULT '',
 headline VARCHAR(240) NOT NULL,
 intro TEXT NOT NULL,
 meta_description VARCHAR(255) NOT NULL DEFAULT '',
 PRIMARY KEY (destination_id,locale),
 CONSTRAINT destination_translations_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_story_blocks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destination_id BIGINT UNSIGNED NOT NULL,
 section_key VARCHAR(32) NOT NULL DEFAULT 'history',
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT destination_story_blocks_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE,
 INDEX destination_story_order (destination_id,section_key,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_story_translations (
 story_block_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 body TEXT NOT NULL,
 PRIMARY KEY (story_block_id,locale),
 CONSTRAINT destination_story_translation_block_fk FOREIGN KEY (story_block_id)
  REFERENCES destination_story_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_reasons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destination_id BIGINT UNSIGNED NOT NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT destination_reasons_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE,
 INDEX destination_reasons_order (destination_id,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_reason_translations (
 reason_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 body VARCHAR(500) NOT NULL,
 PRIMARY KEY (reason_id,locale),
 CONSTRAINT destination_reason_translation_reason_fk FOREIGN KEY (reason_id)
  REFERENCES destination_reasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE points_of_interest (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destination_id BIGINT UNSIGNED NOT NULL,
 slug VARCHAR(120) NOT NULL,
 latitude DECIMAL(9,6) NULL,
 longitude DECIMAL(9,6) NULL,
 publication_status VARCHAR(20) NOT NULL DEFAULT 'published',
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY poi_destination_slug (destination_id,slug),
 INDEX poi_destination_public (destination_id,publication_status,display_order),
 CONSTRAINT poi_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE point_of_interest_translations (
 point_of_interest_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 name VARCHAR(180) NOT NULL,
 description TEXT NOT NULL,
 map_url VARCHAR(1000) NOT NULL DEFAULT '',
 PRIMARY KEY (point_of_interest_id,locale),
 CONSTRAINT poi_translation_poi_fk FOREIGN KEY (point_of_interest_id)
  REFERENCES points_of_interest(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_path VARCHAR(255) NOT NULL UNIQUE,
 creator VARCHAR(255) NOT NULL,
 source_url VARCHAR(1000) NOT NULL,
 license_name VARCHAR(100) NOT NULL,
 license_url VARCHAR(1000) NOT NULL,
 width SMALLINT UNSIGNED NULL,
 height SMALLINT UNSIGNED NULL,
 INDEX media_source_license (license_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_asset_translations (
 media_asset_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 alt_text VARCHAR(500) NOT NULL DEFAULT '',
 caption VARCHAR(500) NOT NULL DEFAULT '',
 PRIMARY KEY (media_asset_id,locale),
 CONSTRAINT media_translation_asset_fk FOREIGN KEY (media_asset_id)
  REFERENCES media_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_media (
 destination_id BIGINT UNSIGNED NOT NULL,
 media_asset_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (destination_id,media_asset_id,role),
 INDEX destination_media_role (destination_id,role,display_order),
 CONSTRAINT destination_media_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE,
 CONSTRAINT destination_media_asset_fk FOREIGN KEY (media_asset_id)
  REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE point_of_interest_media (
 point_of_interest_id BIGINT UNSIGNED NOT NULL,
 media_asset_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL DEFAULT 'gallery',
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (point_of_interest_id,media_asset_id,role),
 INDEX poi_media_order (point_of_interest_id,display_order),
 CONSTRAINT poi_media_poi_fk FOREIGN KEY (point_of_interest_id)
  REFERENCES points_of_interest(id) ON DELETE CASCADE,
 CONSTRAINT poi_media_asset_fk FOREIGN KEY (media_asset_id)
  REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE destination_sources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 destination_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 section_key VARCHAR(32) NOT NULL DEFAULT 'destination',
 source_url VARCHAR(1000) NOT NULL,
 source_label VARCHAR(255) NOT NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT destination_sources_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE CASCADE,
 INDEX destination_sources_order (destination_id,locale,section_key,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_categories (
 code VARCHAR(40) PRIMARY KEY,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_category_translations (
 category_code VARCHAR(40) NOT NULL,
 locale VARCHAR(8) NOT NULL,
 label VARCHAR(160) NOT NULL,
 PRIMARY KEY (category_code,locale),
 CONSTRAINT service_category_translation_code_fk FOREIGN KEY (category_code)
  REFERENCES service_categories(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE businesses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) NOT NULL UNIQUE,
 destination_id BIGINT UNSIGNED NOT NULL,
 category_code VARCHAR(40) NOT NULL,
 slug VARCHAR(140) NOT NULL,
 information_status VARCHAR(20) NOT NULL DEFAULT 'PUBLICO',
 claim_status VARCHAR(20) NOT NULL DEFAULT 'NO_RECLAMADO',
 operation_status VARCHAR(24) NOT NULL DEFAULT 'DESCONOCIDO',
 publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY businesses_destination_slug (destination_id,slug),
 INDEX businesses_directory (destination_id,publication_status,category_code,display_order),
 INDEX businesses_state (information_status,claim_status,operation_status),
 CONSTRAINT businesses_destination_fk FOREIGN KEY (destination_id)
  REFERENCES destinations(id) ON DELETE RESTRICT,
 CONSTRAINT businesses_category_fk FOREIGN KEY (category_code)
  REFERENCES service_categories(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_translations (
 business_id BIGINT UNSIGNED NOT NULL,
 locale VARCHAR(8) NOT NULL,
 display_name VARCHAR(180) NOT NULL,
 summary VARCHAR(500) NOT NULL DEFAULT '',
 description TEXT NOT NULL,
 PRIMARY KEY (business_id,locale),
 CONSTRAINT business_translation_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 channel VARCHAR(24) NOT NULL,
 contact_value VARCHAR(500) NOT NULL,
 is_public TINYINT(1) NOT NULL DEFAULT 0,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT business_contact_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE,
 INDEX business_contact_public (business_id,is_public,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_media (
 business_id BIGINT UNSIGNED NOT NULL,
 media_asset_id BIGINT UNSIGNED NOT NULL,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (business_id,media_asset_id),
 CONSTRAINT business_media_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE,
 CONSTRAINT business_media_asset_fk FOREIGN KEY (media_asset_id)
  REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_sources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 source_url VARCHAR(1000) NOT NULL,
 checked_at DATETIME NULL,
 note VARCHAR(500) NOT NULL DEFAULT '',
 CONSTRAINT business_source_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE,
 INDEX business_sources_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
