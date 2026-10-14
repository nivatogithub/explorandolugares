-- Aplicar exclusivamente con bin/advance-schema.php: comprueba datos heredados antes de modificar.
ALTER TABLE advance_contacts ADD COLUMN destination_id INTEGER REFERENCES destinations(id) ON DELETE RESTRICT;
CREATE INDEX advance_contacts_destination ON advance_contacts(destination_id);
CREATE TABLE advance_business_crm (
 business_id INTEGER PRIMARY KEY REFERENCES businesses(id) ON DELETE CASCADE,
 contact_id INTEGER REFERENCES advance_contacts(id) ON DELETE SET NULL,
 version INTEGER NOT NULL DEFAULT 1
);
-- La herramienta solo permite retirar la tabla anterior cuando está vacía.
DROP TABLE advance_establishments;
