INSERT INTO subscription_features(code,display_order) VALUES ('extended_description',9);
INSERT INTO subscription_feature_translations(feature_code,locale,name,description) VALUES
 ('extended_description','es','Descripción completa','Texto ampliado del negocio en los niveles destacados y página.'),
 ('extended_description','en','Full description','Expanded business description for featured and page tiers.'),
 ('extended_description','pt-BR','Descrição completa','Descrição ampliada do negócio nos níveis de destaque e página.');
INSERT INTO subscription_plan_features(plan_code,feature_code,included,detail) VALUES
 ('free','extended_description',0,'Sin descripción ampliada'),
 ('basic','extended_description',0,'Solo resumen breve'),
 ('featured','extended_description',1,'Descripción intermedia dentro del directorio'),
 ('page','extended_description',1,'Descripción completa en su página'),
 ('page_premium','extended_description',1,'Descripción completa y presentación Premium');
