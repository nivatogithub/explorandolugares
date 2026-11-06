<?php
declare(strict_types=1);
require __DIR__.'/../../backend/app/HomeAttractions.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT,country_code TEXT);
 CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,display_name TEXT);
 CREATE TABLE points_of_interest(id INTEGER PRIMARY KEY,destination_id INTEGER,slug TEXT,publication_status TEXT,poi_type TEXT,display_order INTEGER);
 CREATE TABLE point_of_interest_translations(point_of_interest_id INTEGER,locale TEXT,name TEXT);
 CREATE TABLE media_assets(id INTEGER PRIMARY KEY,asset_path TEXT,creator TEXT,license_name TEXT,source_url TEXT,license_url TEXT);
 CREATE TABLE media_asset_translations(media_asset_id INTEGER,locale TEXT,alt_text TEXT,caption TEXT);
 CREATE TABLE point_of_interest_media(point_of_interest_id INTEGER,media_asset_id INTEGER,display_order INTEGER)');
$selection=json_decode(file_get_contents(__DIR__.'/../../backend/app/content/home-featured-attractions.json'),true,32,JSON_THROW_ON_ERROR);
if(count($selection['items'])!==8||$selection['selection_type']!=='editorial')throw new RuntimeException('Invalid editorial selection');
foreach($selection['items'] as $i=>$item){
 $id=$i+1;$db->prepare('INSERT INTO destinations VALUES(?,?,?,?)')->execute([$id,$item['destination_slug'],'published','CO']);
 $db->prepare('INSERT INTO points_of_interest VALUES(?,?,?,?,?,?)')->execute([$id,$id,$item['attraction_slug'],'published','attraction',100-$i]);
 $db->prepare('INSERT INTO media_assets VALUES(?,?,?,?,?,?)')->execute([$id,'assets/test-'.$id.'.webp','','','','']);
 $db->prepare('INSERT INTO point_of_interest_media VALUES(?,?,0)')->execute([$id,$id]);
 foreach(['es','en','pt-BR'] as $locale){
  $db->prepare('INSERT INTO destination_translations VALUES(?,?,?)')->execute([$id,$locale,'Place '.$id]);
  $db->prepare('INSERT INTO point_of_interest_translations VALUES(?,?,?)')->execute([$id,$locale,'Attraction '.$id]);
  $db->prepare('INSERT INTO media_asset_translations VALUES(?,?,?,?)')->execute([$id,$locale,'Image '.$id,'']);
 }
}
foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $locale=>$prefix){
 $rows=HomeAttractions::items($db,$locale,'/unused');
 if(count($rows)!==8)throw new RuntimeException('Missing published selection');
 foreach($rows as $i=>$row){if($row['slug']!==$selection['items'][$i]['attraction_slug']||$row['url']!==$prefix.'/lugares/'.$selection['items'][$i]['destination_slug'].'/#que-conocer')throw new RuntimeException('Ordering/localized link changed');}
}
$db->exec("UPDATE destinations SET publication_status='draft' WHERE id=3");
if(count(HomeAttractions::items($db,'es','/unused'))!==7)throw new RuntimeException('Draft destination leaked');
$db->exec("UPDATE points_of_interest SET publication_status='draft' WHERE id=4");
if(count(HomeAttractions::items($db,'es','/unused'))!==6)throw new RuntimeException('Draft attraction leaked');
$db->exec("DELETE FROM media_assets WHERE id=5");
if(count(HomeAttractions::items($db,'es','/unused'))!==5)throw new RuntimeException('Missing media produced placeholder');
$db->exec("UPDATE media_assets SET asset_path='../private/config.php' WHERE id=6");
if(count(HomeAttractions::items($db,'es','/unused'))!==4)throw new RuntimeException('Unsafe asset path accepted');
echo "OK: eight editorial attractions, ES/EN/PT-BR, order, draft/media/path protection; synthetic in-memory DB only\n";
