-- Solo bases existentes; ejecutar una vez antes de actualizar el backend.
ALTER TABLE leads ADD COLUMN participation_version VARCHAR(48) NOT NULL DEFAULT '';
