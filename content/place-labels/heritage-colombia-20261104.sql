-- National membership, checked against the FONTUR network list.
-- Apply after destination imports; preserve explicit editorial overrides.
INSERT INTO place_labels (place_id, code, enabled, source_url, verified_on)
SELECT d.id, 'heritage_town', 1,
       'https://fontur.com.co/es/comunicados/villa-de-leyva-sede-del-xi-encuentro-nacional-de-la-red-turistica-de-pueblos-patrimonio',
       '2026-11-04'
FROM destinations d
WHERE d.country_code = 'CO'
  AND (d.department, d.slug) IN (
    ('Antioquia','jardin'), ('Antioquia','jerico'), ('Antioquia','santa-fe-de-antioquia'),
    ('Caldas','aguadas'), ('Caldas','salamina'), ('Santander','barichara'),
    ('Santander','giron'), ('Santander','socorro'), ('Boyacá','mongui'),
    ('Boyacá','villa-de-leyva'), ('Bolívar','mompox'), ('Casanare','pore'),
    ('Córdoba','lorica'), ('Cundinamarca','guaduas'), ('Magdalena','cienaga'),
    ('Norte de Santander','la-playa-de-belen'), ('Tolima','honda'), ('Valle del Cauca','buga')
  )
  AND NOT EXISTS (
    SELECT 1 FROM place_labels l WHERE l.place_id = d.id AND l.code = 'heritage_town'
  );
