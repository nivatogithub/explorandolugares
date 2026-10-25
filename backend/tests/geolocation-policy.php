<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/GeolocationPolicy.php';
function geoCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
foreach([[null,null],['',''],[5.79,null],[INF,-75.7],[95,-75.7],[5.7,-181]] as [$lat,$lon])geoCheck(!GeolocationPolicy::hasPoint($lat,$lon),'Invalid or partial point accepted');
geoCheck(GeolocationPolicy::hasPoint(5.7914719,-75.7862113),'Valid point rejected');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec('CREATE TABLE businesses(id INTEGER PRIMARY KEY,public_id TEXT,category_code TEXT,latitude NUMERIC,longitude NUMERIC)');
$q=$db->prepare('INSERT INTO businesses VALUES(?,?,?,?,?)');$rules=GeolocationPolicy::rules();
$q->execute([1,'physical','comercio',null,null]);$q->execute([2,'located','comercio',5.79,-75.78]);$q->execute([3,$rules['retired_public_ids'][0],'comercio',5.79,-75.78]);$q->execute([4,$rules['business_exceptions'][0],'transporte',null,null]);$q->execute([5,'activity','experiencias',null,null]);
foreach([1,3] as $id){$blocked=false;try{GeolocationPolicy::business($db,$id);}catch(RuntimeException){$blocked=true;}geoCheck($blocked,'Missing point or retirement bypassed publication');}
foreach([2,4,5] as $id)GeolocationPolicy::business($db,$id);
geoCheck(GeolocationPolicy::placeExempt('el-retiro','paisaje-rural','place'),'Owner exception lost');geoCheck(!GeolocationPolicy::placeExempt('otro','paisaje-rural','place'),'Exception leaked to another destination');
echo "OK: publication requires complete coordinates, retained retirements cannot republish, and approved exceptions are scoped.\n";
