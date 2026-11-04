<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/portal/app/PlaceDirectoryService.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach([
 'destinations'=>'id INT PRIMARY KEY,slug VARCHAR(190),department VARCHAR(100),subregion VARCHAR(100),publication_status VARCHAR(20)',
 'destination_translations'=>'destination_id INT,locale VARCHAR(10),display_name VARCHAR(190),intro TEXT',
 'place_labels'=>'place_id INT,code VARCHAR(50),enabled INT',
 'media_assets'=>'id INT PRIMARY KEY,asset_path VARCHAR(190)',
 'destination_media'=>'destination_id INT,media_asset_id INT,role VARCHAR(30),display_order INT',
 'media_asset_translations'=>'media_asset_id INT,locale VARCHAR(10),alt_text VARCHAR(190)'
] as $table=>$schema)$pdo->exec('CREATE TEMPORARY TABLE '.$table.'('.$schema.') ENGINE=InnoDB');
$place=$pdo->prepare("INSERT INTO destinations VALUES(?,?,'Antioquia','Suroeste','published')");$translation=$pdo->prepare("INSERT INTO destination_translations VALUES(?,?,?,'Synthetic')");
for($i=1;$i<=100;$i++){$place->execute([$i,sprintf('synthetic-%03d',$i)]);foreach(['es','en','pt-BR'] as $locale)$translation->execute([$i,$locale,sprintf('Synthetic %03d',$i)]);}
$directory=new PlaceDirectoryService(new PlaceDirectorySelector($pdo));
foreach(['es','en','pt-BR'] as $locale){$seen=[];for($page=1;$page<=12;$page++){$result=$directory->search($locale,['pagina'=>(string)$page]);if($result['total']!==100||$result['pages']!==12||count($result['rows'])!==($page===12?1:9))throw new RuntimeException('Wrong pagination.');$seen=array_merge($seen,array_column($result['rows'],'slug'));}if(count($seen)!==100||count(array_unique($seen))!==100)throw new RuntimeException('Repeated or missing places.');$result=$directory->search($locale,['q'=>'Synthetic 100']);if($result['total']!==1||$result['rows'][0]['slug']!=='synthetic-100')throw new RuntimeException('Search failed.');}
echo "OK MariaDB: pagination and search for 100 synthetic places in three languages, temporary tables only; no load test.\n";
