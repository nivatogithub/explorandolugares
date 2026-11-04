-- Labels are independent of geography. A disabled row preserves an editorial removal.
CREATE TABLE IF NOT EXISTS place_labels (
 place_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(50) NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 source_url VARCHAR(2048) NOT NULL DEFAULT '',
 verified_on DATE NULL,
 PRIMARY KEY (place_id,code),
 CONSTRAINT place_labels_place FOREIGN KEY (place_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fill reviewed omissions only. Later nonempty editorial values are preserved.
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='barranquilla' AND department='Atlántico' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='riohacha' AND department='La Guajira' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='valledupar' AND department='Cesar' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='san-bernardo-del-viento' AND department='Córdoba' AND TRIM(COALESCE(subregion,''))='';

-- Membership verified against Fontur. No inference from architecture or a parent place.
INSERT INTO place_labels(place_id,code,enabled,source_url,verified_on)
 SELECT d.id,'heritage_town',1,'https://fontur.com.co/es/comunicados/villa-de-leyva-sede-del-xi-encuentro-nacional-de-la-red-turistica-de-pueblos-patrimonio','2026-11-03'
 FROM destinations d WHERE d.country_code='CO' AND d.department='Antioquia' AND d.slug IN ('jardin','jerico','santa-fe-de-antioquia')
 AND NOT EXISTS (SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code='heritage_town');
