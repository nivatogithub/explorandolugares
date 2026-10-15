-- Extiende los atractivos para distinguir una experiencia y enlazar al operador.
-- Ejecutar una sola vez después de la migración 003 en cada base de datos.
ALTER TABLE points_of_interest
 ADD COLUMN poi_type VARCHAR(20) NOT NULL DEFAULT 'place' AFTER slug;

ALTER TABLE point_of_interest_translations
 ADD COLUMN external_url VARCHAR(1000) NOT NULL DEFAULT '' AFTER map_url,
 ADD COLUMN external_label VARCHAR(180) NOT NULL DEFAULT '' AFTER external_url;
