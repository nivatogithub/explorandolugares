-- Solo entidades ficticias en una base de prueba aislada.
INSERT INTO destinations(id,slug,publication_status) VALUES(1,'destino-prueba','draft');
INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,'es','Destino de prueba','Prueba','Prueba');
INSERT INTO service_categories(code,display_order,is_active) VALUES('cultura',1,1),('inactiva',2,0);
INSERT INTO service_category_translations(category_code,locale,label) VALUES('cultura','es','Cultura de prueba');
INSERT INTO businesses(id,public_id,destination_id,category_code,slug,publication_status,information_status,claim_status,operation_status) VALUES(1,'00000000-0000-0000-0000-000000000001',1,'cultura','negocio-existente','draft','VERIFICADO','RECLAMADO','ABIERTO');
INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(1,'es','Negocio existente','Resumen existente','Descripción existente'),(1,'en','Existing business','Existing summary','Existing description');
INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(1,'email','business@example.invalid',0),(1,'web','https://example.invalid',1);
INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(1,'https://example.invalid/source','2026-10-14 00:00:00','Fuente previa');
INSERT INTO media_assets(id,asset_path,creator,source_url,license_name,license_url) VALUES(1,'test.webp','Prueba','https://example.invalid','Prueba','https://example.invalid/license');
INSERT INTO business_media(business_id,media_asset_id) VALUES(1,1);
INSERT INTO points_of_interest(destination_id,slug) VALUES(1,'punto-prueba');
