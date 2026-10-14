-- Ejecutar después de 008 con el backend detenido y un respaldo.
UPDATE advance_contacts SET relationship=CASE relationship
 WHEN 'prospecto' THEN 'prospect' WHEN 'en_revision' THEN 'under_review'
 WHEN 'aliado' THEN 'partner' WHEN 'archivado' THEN 'archived' ELSE relationship END;
UPDATE advance_campaigns SET status=CASE status
 WHEN 'borrador' THEN 'draft' WHEN 'aprobada' THEN 'approved' ELSE status END;
UPDATE advance_deliveries SET status=CASE status
 WHEN 'pendiente' THEN 'pending' WHEN 'enviando' THEN 'sending' WHEN 'omitida' THEN 'skipped'
 WHEN 'incierta' THEN 'uncertain' WHEN 'aceptada' THEN 'accepted' ELSE status END;
ALTER TABLE advance_contacts ALTER COLUMN relationship SET DEFAULT 'prospect';
ALTER TABLE advance_campaigns ALTER COLUMN status SET DEFAULT 'draft';
ALTER TABLE advance_deliveries ALTER COLUMN status SET DEFAULT 'pending';
