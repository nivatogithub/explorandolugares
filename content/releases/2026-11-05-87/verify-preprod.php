<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(80);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))exit(81);
$mode=$argv[1]??'';
if(!in_array($mode,['rehearse','apply'],true))exit(82);
$db=$mode==='apply'?new PDO($c['dsn'],$c['db_user'],$c['db_password']):new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=el_rehearse_nov87;charset=utf8mb4','root','');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pack=json_decode(file_get_contents(__DIR__.'/public-content.json'),true,128,JSON_THROW_ON_ERROR);
$businessCount=0;$visible=0;
foreach($pack['editorial'] as $slug=>$source){
    $q=$db->prepare("SELECT id FROM destinations WHERE slug=? AND publication_status='published'");$q->execute([$slug]);$id=$q->fetchColumn();
    if(!$id)throw new RuntimeException("Missing $slug");
    $q=$db->prepare("SELECT slug FROM points_of_interest WHERE destination_id=? AND publication_status='published'");$q->execute([$id]);$got=$q->fetchAll(PDO::FETCH_COLUMN);
    $want=array_column($source['places'],'slug');sort($want);sort($got);
    if($got!==$want)throw new RuntimeException("Attractions differ for $slug");
    $visible+=count($got);
    $q=$db->prepare("SELECT locale FROM destination_translations WHERE destination_id=? ORDER BY locale");$q->execute([$id]);if($q->fetchAll(PDO::FETCH_COLUMN)!==['en','es','pt-BR'])throw new RuntimeException("Translations differ for $slug");
}
$find=$db->prepare("SELECT b.id,b.publication_status FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.public_id=? AND d.slug=?");
$slugById=array_column($pack['editorial'],'slug','id');
foreach($pack['tables']['businesses'] as $b){
    $find->execute([$b['public_id'],$slugById[$b['destination_id']]]);$row=$find->fetch();
    if(!$row||$row['publication_status']!=='published')throw new RuntimeException('Published business identity mismatch');
    ++$businessCount;
}
$heritage=(int)$db->query("SELECT COUNT(*) FROM place_labels l JOIN destinations d ON d.id=l.place_id WHERE d.publication_status='published' AND l.code='heritage_town' AND l.enabled=1")->fetchColumn();
if($heritage!==18)throw new RuntimeException('Heritage count '.$heritage);
echo json_encode(['guides'=>87,'poi_and_experiences'=>$visible,'businesses'=>$businessCount,'heritage'=>$heritage,'all_published'=>(int)$db->query("SELECT COUNT(*) FROM destinations WHERE publication_status='published'")->fetchColumn()])."\n";
