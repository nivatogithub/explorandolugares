<?php
require dirname(__DIR__).'/app/ReviewedMapLinks.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT);CREATE TABLE points_of_interest(id INTEGER PRIMARY KEY,destination_id INTEGER,slug TEXT,poi_type TEXT,latitude REAL,longitude REAL,publication_status TEXT);CREATE TABLE point_of_interest_translations(point_of_interest_id INTEGER,locale TEXT,map_url TEXT,name TEXT,description TEXT);CREATE TABLE protected_data(id INTEGER,value TEXT);INSERT INTO protected_data VALUES(1,"retain live data")');
$c=ReviewedMapLinks::catalog();$i=0;$dest=[];
foreach($c['places'] as $p){$i++;if(!isset($dest[$p['destination']])){$dest[$p['destination']]=count($dest)+1;$db->prepare('INSERT INTO destinations VALUES(?,?)')->execute([$dest[$p['destination']],$p['destination']]);}$db->prepare('INSERT INTO points_of_interest VALUES(?,?,?,?,0,0,"published")')->execute([$i,$dest[$p['destination']],$p['slug'],$p['type']]);foreach($p['before'] as $locale=>$url)$db->prepare('INSERT INTO point_of_interest_translations VALUES(?,?,?,?,?)')->execute([$i,$locale,$url,'Retain name','Retain description']);}
$first=ReviewedMapLinks::apply($db);$second=ReviewedMapLinks::apply($db);
if($first['checked_translations']!==288||$second['changed_translations']!==0)throw new Exception('Not complete or idempotent');
if($db->query('SELECT value FROM protected_data')->fetchColumn()!=='retain live data'||$db->query('SELECT COUNT(*) FROM point_of_interest_translations WHERE name<>"Retain name" OR description<>"Retain description"')->fetchColumn()!=0)throw new Exception('Unrelated data changed');
$db->exec('UPDATE point_of_interest_translations SET map_url="https://example.com/later-edit" WHERE point_of_interest_id=90');
$before=$db->query('SELECT * FROM point_of_interest_translations')->fetchAll();try{ReviewedMapLinks::apply($db);throw new Exception('Expected conflict');}catch(RuntimeException $e){}
if($db->inTransaction()||$before!==$db->query('SELECT * FROM point_of_interest_translations')->fetchAll())throw new Exception('Rollback failed');
echo json_encode(['first'=>$first,'second'=>$second,'conflict_rolls_back'=>true,'protected_data_preserved'=>true])."\n";
