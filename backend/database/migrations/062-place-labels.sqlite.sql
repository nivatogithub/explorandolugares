CREATE TABLE IF NOT EXISTS place_labels (
 place_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
 code TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1,
 source_url TEXT NOT NULL DEFAULT '', verified_on TEXT,
 PRIMARY KEY (place_id,code)
);
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='barranquilla' AND department='Atlántico' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='riohacha' AND department='La Guajira' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='valledupar' AND department='Cesar' AND TRIM(COALESCE(subregion,''))='';
UPDATE destinations SET subregion='Caribe' WHERE country_code='CO' AND slug='san-bernardo-del-viento' AND department='Córdoba' AND TRIM(COALESCE(subregion,''))='';
INSERT INTO place_labels(place_id,code,enabled,source_url,verified_on)
 SELECT d.id,'heritage_town',1,'https://fontur.com.co/es/comunicados/villa-de-leyva-sede-del-xi-encuentro-nacional-de-la-red-turistica-de-pueblos-patrimonio','2026-11-03'
 FROM destinations d WHERE d.country_code='CO' AND d.department='Antioquia' AND d.slug IN ('jardin','jerico','santa-fe-de-antioquia')
 AND NOT EXISTS (SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code='heritage_town');
