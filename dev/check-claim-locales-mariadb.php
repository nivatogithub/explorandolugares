<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local dev only.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev database required.');
require '/srv/state/architecture-claim-locales/BusinessClaimSelector.php';
$p=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['destinations','destination_translations','businesses','business_translations','service_categories','service_category_translations'] as $table)$p->exec('DROP TEMPORARY TABLE IF EXISTS '.$table);
$p->exec('CREATE TEMPORARY TABLE destinations(id INT,slug VARCHAR(50),display_order INT,publication_status VARCHAR(30)) ENGINE=InnoDB');
$p->exec('CREATE TEMPORARY TABLE destination_translations(destination_id INT,locale VARCHAR(10),display_name VARCHAR(100)) ENGINE=InnoDB');
$p->exec('CREATE TEMPORARY TABLE businesses(id INT,destination_id INT,category_code VARCHAR(30),publication_status VARCHAR(30),claim_status VARCHAR(30),operation_status VARCHAR(30),information_status VARCHAR(30),address_text VARCHAR(100)) ENGINE=InnoDB');
$p->exec('CREATE TEMPORARY TABLE business_translations(business_id INT,locale VARCHAR(10),display_name VARCHAR(100)) ENGINE=InnoDB');
$p->exec('CREATE TEMPORARY TABLE service_categories(code VARCHAR(30),is_active INT,display_order INT) ENGINE=InnoDB');
$p->exec('CREATE TEMPORARY TABLE service_category_translations(category_code VARCHAR(30),locale VARCHAR(10),label VARCHAR(100)) ENGINE=InnoDB');
$p->exec("INSERT INTO destinations VALUES(1,'pueblo',1,'published'),(2,'costa',2,'published')");
$p->exec("INSERT INTO destination_translations VALUES(1,'es','Pueblo'),(1,'en','Village'),(1,'pt-BR','Vila'),(2,'es','Costa')");
$p->exec("INSERT INTO service_categories VALUES('hotel',1,1)");
$p->exec("INSERT INTO service_category_translations VALUES('hotel','es','Alojamiento'),('hotel','en','Lodging'),('hotel','pt-BR','Hospedagem')");
$p->exec("INSERT INTO businesses VALUES(1,1,'hotel','published','NO_RECLAMADO','ABIERTO','VERIFICADO','Dirección'),(2,1,'hotel','published','NO_RECLAMADO','ABIERTO','PUBLICO',''),(3,2,'hotel','published','NO_RECLAMADO','ABIERTO','PUBLICO','')");
$p->exec("INSERT INTO business_translations VALUES(1,'es','Casa Uno'),(1,'en','English House'),(1,'pt-BR','Casa Portuguesa'),(2,'es','Casa Dos'),(3,'es','Casa Costa')");
$s=new BusinessClaimSelector($p);
foreach(['es'=>['Pueblo','Alojamiento','Casa Uno'],'en'=>['Village','Lodging','English House'],'pt-br'=>['Vila','Hospedagem','Casa Portuguesa']] as $locale=>$expected){
 $places=$s->places($locale);$categories=$s->categories(1,$locale);$businesses=$s->search(1,'hotel','',$locale);
 $byId=[];foreach($businesses as $row)$byId[(int)$row['id']]=$row['display_name'];
 if($places[0]['display_name']!==$expected[0]||$places[1]['display_name']!=='Costa'||$categories[0]['label']!==$expected[1]||$byId[1]!==$expected[2]||$byId[2]!=='Casa Dos')throw new RuntimeException('Locale or fallback incorrect: '.$locale);
}
if(count($s->search(1,'hotel','English','en'))!==1||$s->search(1,'hotel','English','es')!==[])throw new RuntimeException('Localized name search failed.');
if($s->places('unknown')!==$s->places('es'))throw new RuntimeException('Unsupported locale fallback failed.');
echo json_encode(['driver'=>'MariaDB','temporary_tables_only'=>true,'locales'=>3,'fallback'=>true,'localized_search'=>true],JSON_THROW_ON_ERROR)."\n";
