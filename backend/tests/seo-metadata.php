<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/seo/PublicSeo.php';
require dirname(__DIR__).'/app/DatabaseMaintenance.php';
function metadata_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
metadata_check(PublicSeo::description('  Café   y tradición. ')==='Café y tradición.','Normalize whitespace');
$excerpt=PublicSeo::description(str_repeat('Café tradición Bogotá. ',20));
metadata_check(mb_check_encoding($excerpt,'UTF-8')&&mb_strlen($excerpt)<=160&&str_ends_with($excerpt,'…'),'Safe Unicode excerpt');
metadata_check(str_contains(PublicSeo::placeTitle('Jericó','en','Antioquia'),'Things to do in Jericó, Antioquia'),'Localized purpose and location');
$business=['display_name'=>'Café </script><script>alert(1)</script>','document'=>'PRIVATE-DOCUMENT','payment'=>'PRIVATE-PAYMENT'];
$json=PublicSeo::jsonLd(PublicSeo::businessGraph($business,'https://example.invalid/negocios/test/','Title','Description','es',null));
metadata_check(substr_count($json,'</script>')===1&&!str_contains($json,'PRIVATE-'),'Escape and private allowlist');
preg_match('~<script[^>]*>(.*)</script>~s',$json,$matches);json_decode($matches[1],true,512,JSON_THROW_ON_ERROR);
metadata_check(PublicSeo::alternates(['es'=>'/one/'],'https://example.invalid')==='','No invented language');
$alternates=PublicSeo::alternates(['es'=>'/one/','en'=>'/en/one/'],'https://example.invalid');
metadata_check(str_contains($alternates,'x-default')&&!str_contains($alternates,'pt-BR'),'Only real equivalents');
metadata_check(!str_contains(PublicSeo::alternates(['es'=>'//evil.invalid/','en'=>'/en/'],'https://example.invalid'),'evil.invalid'),'Reject external alternate path');

$root=dirname(__DIR__);$changes=json_decode(file_get_contents($root.'/app/content/seo-description-patch.json'),true,512,JSON_THROW_ON_ERROR)['changes'];
$db=$seoFixtureDb??new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT);CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,meta_description TEXT)');
$insert=$db->prepare('INSERT INTO destination_translations VALUES(?,?,?)');$ids=[];
foreach($changes as $change){
    if(!isset($ids[$change['slug']])){$id=count($ids)+1;$ids[$change['slug']]=$id;$q=$db->prepare('INSERT INTO destinations VALUES(?,?,?)');$q->execute([$id,$change['slug'],'draft']);}
    $insert->execute([$ids[$change['slug']],$change['locale'],$change['before']]);
    metadata_check(mb_strlen($change['after'])<=160,'Reviewed description budget');
}
$first=$changes[0];$customCopy=mb_strtoupper($first['before'],'UTF-8');
$q=$db->prepare('UPDATE destination_translations SET meta_description=? WHERE destination_id=? AND locale=?');$q->execute([$customCopy,$ids[$first['slug']],$first['locale']]);
$mysql=file_get_contents($root.'/database/migrations/059-public-seo-descriptions.mysql.sql');
metadata_check($mysql===file_get_contents($root.'/database/migrations/059-public-seo-descriptions.sqlite.sql'),'Engine parity');
$maintenance=new DatabaseMaintenance($db,$root.'/app','unused');$state=new ReflectionMethod(DatabaseMaintenance::class,'migrationState');
$definition=['metadata_patch'=>'content/seo-description-patch.json'];$tables=['destinations','destination_translations'];
metadata_check($state->invoke($maintenance,$definition,$tables)!=='applied','Old descriptions detected');
$db->exec($mysql);$db->exec($mysql);
metadata_check($state->invoke($maintenance,$definition,$tables)==='applied','Idempotent metadata update confirmed');
metadata_check((int)$db->query("SELECT COUNT(*) FROM destinations WHERE publication_status<>'draft'")->fetchColumn()===0,'No publication changed');
$q=$db->prepare('SELECT meta_description FROM destination_translations WHERE destination_id=? AND locale=?');$q->execute([$ids[$first['slug']],$first['locale']]);
metadata_check($q->fetchColumn()===$customCopy,'Preserve later custom copy even under case-insensitive MySQL collation');
echo "SEO metadata: OK — Unicode, localized titles, safe schema, real languages and scoped idempotent data repair.\n";
