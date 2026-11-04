<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PlaceDirectoryService.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$place=$pdo->prepare("INSERT INTO destinations(slug,country_code,department,subregion,publication_status) VALUES(?,'CO','Antioquia','Suroeste','published')");
$translation=$pdo->prepare("INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,'','','Synthetic','Synthetic')");
for($i=1;$i<=100;$i++){$name=sprintf('Synthetic %03d',$i);$place->execute([sprintf('synthetic-%03d',$i)]);$id=(int)$pdo->lastInsertId();foreach(['es','en','pt-BR'] as $locale)$translation->execute([$id,$locale,$name]);}
$directory=new PlaceDirectoryService(new PlaceDirectorySelector($pdo));
foreach(['es','en','pt-BR'] as $locale){
 $seen=[];for($page=1;$page<=12;$page++){$result=$directory->search($locale,['pagina'=>(string)$page]);if($result['total']!==100||$result['pages']!==12||count($result['rows'])!==($page===12?1:9))throw new RuntimeException('Unexpected page count.');$seen=array_merge($seen,array_column($result['rows'],'slug'));}
 if(count($seen)!==100||count(array_unique($seen))!==100)throw new RuntimeException('A place was repeated or omitted.');
 $filtered=$directory->search($locale,['q'=>'Synthetic 100']);if($filtered['total']!==1||$filtered['rows'][0]['slug']!=='synthetic-100')throw new RuntimeException('Search failed.');
}
echo "OK: 100 synthetic places paginate and search correctly in three languages (SQLite; no load test).\n";
