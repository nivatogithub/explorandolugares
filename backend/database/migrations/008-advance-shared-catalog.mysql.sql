-- Aplicar exclusivamente con bin/advance-schema.php: comprueba datos heredados antes de modificar.
ALTER TABLE advance_contacts ADD COLUMN destination_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT advance_contact_destination_fk FOREIGN KEY(destination_id) REFERENCES destinations(id) ON DELETE RESTRICT;
CREATE INDEX advance_contacts_destination ON advance_contacts(destination_id);
CREATE TABLE advance_business_crm (
 business_id BIGINT UNSIGNED PRIMARY KEY,
 contact_id BIGINT UNSIGNED NULL,
 version INTEGER NOT NULL DEFAULT 1,
 CONSTRAINT advance_business_crm_business_fk FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,
 CONSTRAINT advance_business_crm_contact_fk FOREIGN KEY(contact_id) REFERENCES advance_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE advance_audit MODIFY COLUMN entity_id BIGINT UNSIGNED NOT NULL;
-- La herramienta solo permite retirar la tabla anterior cuando está vacía.
DROP TABLE advance_establishments;
