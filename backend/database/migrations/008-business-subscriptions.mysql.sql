-- Subscription catalog and business entitlements. Prices stay unset until approved.
CREATE TABLE subscription_plans (
 code VARCHAR(32) PRIMARY KEY,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 has_business_page TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plan_translations (
 plan_code VARCHAR(32) NOT NULL,
 locale VARCHAR(8) NOT NULL,
 name VARCHAR(100) NOT NULL,
 summary VARCHAR(500) NOT NULL DEFAULT '',
 PRIMARY KEY (plan_code,locale),
 CONSTRAINT subscription_plan_translation_plan_fk FOREIGN KEY (plan_code)
  REFERENCES subscription_plans(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_features (
 code VARCHAR(40) PRIMARY KEY,
 display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_feature_translations (
 feature_code VARCHAR(40) NOT NULL,
 locale VARCHAR(8) NOT NULL,
 name VARCHAR(160) NOT NULL,
 description VARCHAR(500) NOT NULL DEFAULT '',
 PRIMARY KEY (feature_code,locale),
 CONSTRAINT subscription_feature_translation_feature_fk FOREIGN KEY (feature_code)
  REFERENCES subscription_features(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plan_features (
 plan_code VARCHAR(32) NOT NULL,
 feature_code VARCHAR(40) NOT NULL,
 included TINYINT(1) NOT NULL DEFAULT 0,
 detail VARCHAR(300) NOT NULL DEFAULT '',
 PRIMARY KEY (plan_code,feature_code),
 CONSTRAINT subscription_plan_feature_plan_fk FOREIGN KEY (plan_code)
  REFERENCES subscription_plans(code) ON DELETE CASCADE,
 CONSTRAINT subscription_plan_feature_feature_fk FOREIGN KEY (feature_code)
  REFERENCES subscription_features(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plan_prices (
 plan_code VARCHAR(32) NOT NULL,
 billing_frequency VARCHAR(16) NOT NULL,
 amount DECIMAL(12,2) NULL,
 currency CHAR(3) NOT NULL DEFAULT 'COP',
 PRIMARY KEY (plan_code,billing_frequency),
 CONSTRAINT subscription_plan_price_plan_fk FOREIGN KEY (plan_code)
  REFERENCES subscription_plans(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 plan_code VARCHAR(32) NOT NULL,
 billing_frequency VARCHAR(16) NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX business_subscription_current (business_id,status,starts_at,ends_at),
 CONSTRAINT business_subscription_business_fk FOREIGN KEY (business_id)
  REFERENCES businesses(id) ON DELETE CASCADE,
 CONSTRAINT business_subscription_plan_fk FOREIGN KEY (plan_code)
  REFERENCES subscription_plans(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE business_media
 ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT 'gallery' AFTER media_asset_id,
 ADD INDEX business_media_role (business_id,role,display_order);

INSERT INTO subscription_plans (code,display_order,is_active,has_business_page) VALUES
 ('free',1,1,0),('basic',2,1,0),('featured',3,1,0),('page',4,1,1),('page_premium',5,1,1);

INSERT INTO subscription_plan_translations (plan_code,locale,name,summary) VALUES
 ('free','es','Gratis','Presencia básica en el directorio del destino.'),
 ('basic','es','Básico','Más información y contacto dentro del directorio.'),
 ('featured','es','Destacado','Tarjeta resaltada con identidad visual e información ampliada.'),
 ('page','es','Página','Página propia del negocio con información completa.'),
 ('page_premium','es','Página Premium','Página propia con identidad de marca y presentación ampliada.'),
 ('free','en','Free','Basic presence in the destination directory.'),
 ('basic','en','Basic','More information and contact within the directory.'),
 ('featured','en','Featured','Highlighted card with visual identity and expanded information.'),
 ('page','en','Business Page','A dedicated page with complete business information.'),
 ('page_premium','en','Premium Page','A dedicated page with brand identity and expanded presentation.'),
 ('free','pt-BR','Grátis','Presença básica no diretório do destino.'),
 ('basic','pt-BR','Básico','Mais informações e contato dentro do diretório.'),
 ('featured','pt-BR','Destaque','Cartão em destaque com identidade visual e informações ampliadas.'),
 ('page','pt-BR','Página','Página própria do negócio com informações completas.'),
 ('page_premium','pt-BR','Página Premium','Página própria com identidade de marca e apresentação ampliada.');

INSERT INTO subscription_features (code,display_order) VALUES
 ('directory_entry',1),('expanded_details',2),('direct_contact',3),('highlighted_card',4),
 ('business_logo',5),('business_page',6),('photo_gallery',7),('brand_presentation',8);

INSERT INTO subscription_feature_translations (feature_code,locale,name,description) VALUES
 ('directory_entry','es','Aparecer en el directorio','Nombre, categoría y destino.'),
 ('expanded_details','es','Información ampliada en el directorio','El viajero consulta más información sin salir de la página del destino.'),
 ('direct_contact','es','Contacto directo','Canales de contacto públicos autorizados.'),
 ('highlighted_card','es','Tarjeta destacada','Resaltada visualmente dentro del directorio, sin alterar los filtros.'),
 ('business_logo','es','Logo o iniciales','Si no hay logo, se muestran las iniciales del negocio.'),
 ('business_page','es','Página propia','Dirección pública propia generada desde una plantilla.'),
 ('photo_gallery','es','Galería de imágenes','Imágenes del negocio revisadas y autorizadas.'),
 ('brand_presentation','es','Presentación de marca','Portada y secciones con identidad visual del negocio.'),
 ('directory_entry','en','Directory listing','Name, category and destination.'),
 ('expanded_details','en','Expanded directory details','Visitors can see more without leaving the destination page.'),
 ('direct_contact','en','Direct contact','Approved public contact channels.'),
 ('highlighted_card','en','Featured card','Visually highlighted in the directory without changing filters.'),
 ('business_logo','en','Logo or initials','If no logo is available, the business initials are shown.'),
 ('business_page','en','Business page','A dedicated public URL rendered from a template.'),
 ('photo_gallery','en','Photo gallery','Reviewed and approved business images.'),
 ('brand_presentation','en','Brand presentation','Cover and sections with the business visual identity.'),
 ('directory_entry','pt-BR','Presença no diretório','Nome, categoria e destino.'),
 ('expanded_details','pt-BR','Informações ampliadas no diretório','O visitante consulta mais informações sem sair da página do destino.'),
 ('direct_contact','pt-BR','Contato direto','Canais de contato públicos autorizados.'),
 ('highlighted_card','pt-BR','Cartão em destaque','Destaque visual no diretório, sem alterar os filtros.'),
 ('business_logo','pt-BR','Logo ou iniciais','Sem logo, são exibidas as iniciais do negócio.'),
 ('business_page','pt-BR','Página própria','Endereço público próprio gerado a partir de um modelo.'),
 ('photo_gallery','pt-BR','Galeria de imagens','Imagens do negócio revisadas e autorizadas.'),
 ('brand_presentation','pt-BR','Apresentação da marca','Capa e seções com a identidade visual do negócio.');

INSERT INTO subscription_plan_features (plan_code,feature_code,included,detail) VALUES
 ('free','directory_entry',1,'Nombre y categoría'),('free','expanded_details',0,'Sin información ampliada'),('free','direct_contact',0,'Sin contacto público'),('free','highlighted_card',0,'Sin resalte visual'),('free','business_logo',0,'Sin logo'),('free','business_page',0,'Sin página propia'),('free','photo_gallery',0,'Sin fotografías'),('free','brand_presentation',0,'Sin presentación de marca'),
 ('basic','directory_entry',1,'Nombre y categoría'),('basic','expanded_details',1,'Descripción y datos útiles desplegables'),('basic','direct_contact',1,'Canal de contacto autorizado'),('basic','highlighted_card',0,'Sin resalte visual'),('basic','business_logo',0,'Sin logo'),('basic','business_page',0,'Sin página propia'),('basic','photo_gallery',0,'Sin fotografías'),('basic','brand_presentation',0,'Sin presentación de marca'),
 ('featured','directory_entry',1,'Nombre y categoría'),('featured','expanded_details',1,'Información intermedia desplegable'),('featured','direct_contact',1,'Canales de contacto autorizados'),('featured','highlighted_card',1,'Tarjeta resaltada dentro del directorio'),('featured','business_logo',1,'Logo o iniciales generadas con el nombre'),('featured','business_page',0,'Sin página propia'),('featured','photo_gallery',0,'Sin galería'),('featured','brand_presentation',0,'Identidad básica en la tarjeta'),
 ('page','directory_entry',1,'Enlace a la página del negocio'),('page','expanded_details',1,'Resumen en el directorio'),('page','direct_contact',1,'Canales de contacto autorizados'),('page','highlighted_card',0,'No incluye destacado editorial'),('page','business_logo',1,'Logo o iniciales'),('page','business_page',1,'Página propia con información completa'),('page','photo_gallery',1,'Galería autorizada'),('page','brand_presentation',0,'Plantilla estándar del portal'),
 ('page_premium','directory_entry',1,'Enlace con identificación Premium'),('page_premium','expanded_details',1,'Resumen ampliado en el directorio'),('page_premium','direct_contact',1,'Canales de contacto priorizados'),('page_premium','highlighted_card',1,'Destacado identificado en directorio'),('page_premium','business_logo',1,'Logo o iniciales'),('page_premium','business_page',1,'Página propia con secciones ampliadas'),('page_premium','photo_gallery',1,'Galería priorizada'),('page_premium','brand_presentation',1,'Presentación con identidad de marca');

INSERT INTO subscription_plan_prices (plan_code,billing_frequency,amount,currency)
SELECT p.code,f.frequency,NULL,'COP' FROM subscription_plans p
CROSS JOIN (SELECT 'monthly' AS frequency UNION ALL SELECT 'quarterly' UNION ALL SELECT 'semiannual' UNION ALL SELECT 'annual') f;
