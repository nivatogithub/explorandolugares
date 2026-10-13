-- Solo para bases existentes, ejecutar una vez antes de actualizar el backend.
ALTER TABLE leads ADD COLUMN phone_type VARCHAR(16) NOT NULL DEFAULT '';
