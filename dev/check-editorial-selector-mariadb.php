<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/state/editorial-selector-test/BusinessEditorialSelector.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TEMPORARY TABLE business_editorial_images(id INT PRIMARY KEY,version_id INT,storage_name VARCHAR(100),original_name VARCHAR(100))');$pdo->exec('CREATE TEMPORARY TABLE media_assets(id INT PRIMARY KEY,asset_path VARCHAR(100))');$pdo->exec('CREATE TEMPORARY TABLE businesses(id INT PRIMARY KEY,public_id VARCHAR(100))');
$pdo->exec("INSERT INTO business_editorial_images VALUES(1,1,'a.png','First'),(2,1,'b.png','Second'),(3,2,'c.png','Other')");$pdo->exec("INSERT INTO businesses VALUES(1,'synthetic-id')");$insert=$pdo->prepare('INSERT INTO media_assets VALUES(?,?)');for($i=1;$i<=505;$i++)$insert->execute([$i,'synthetic-'.$i]);
$s=new BusinessEditorialSelector($pdo);$query=$pdo->prepare('SELECT id,storage_name,original_name FROM business_editorial_images WHERE version_id=?');$query->execute([1]);if($s->references(1)!==$query->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Reference list changed.');
$query=$pdo->prepare('SELECT id,original_name FROM business_editorial_images WHERE version_id=?');$query->execute([1]);if($s->referenceNames(1)!==$query->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Reference names changed.');
$assets=$pdo->query('SELECT id,asset_path FROM media_assets ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);if($s->mediaAssets()!==$assets||count($assets)!==500||(int)$assets[0]['id']!==505)throw new RuntimeException('Media list changed.');
if($s->publicId(1)!=='synthetic-id'||$s->publicId(2)!==false)throw new RuntimeException('Public ID changed.');
echo "OK MariaDB: editorial references, 505 assets, public ID and missing item; temporary tables only.\n";
