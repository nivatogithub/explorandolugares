<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessExistenceVerification.php';
function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$row=['codigo_rnt'=>'11','nit'=>'800078692','razon_social_establecimiento'=>'BCD TRAVEL OFICINA PRINCIPAL','municipio'=>'BOGOTA D.C.','estado_rnt'=>'ACTIVO','ano'=>'2026','periodo'=>'AGOSTO'];
$ids=BusinessExistenceVerification::capture(['business_rnt'=>'11','business_nit'=>'800.078.692-4']);
 $formattedRnt=BusinessExistenceVerification::capture(['business_rnt'=>'RNT N.° 000.000-011']);verify($formattedRnt['rnt']==='000000011','Accept the RNT label and visual separators from its certificate');
$r=BusinessExistenceVerification::assessNew('BCD TRAVEL OFICINA PRINCIPAL','Bogotá D.C.',$ids,static fn($url)=>[$row]);verify($r['existence']==='official_snapshot_match'&&!$r['ownership_verified']&&$r['review']==='attention','Snapshot should match but retain historical warning');verify(!isset($r['snapshot']['nit']),'Do not copy unnecessary registry fields');
$r=BusinessExistenceVerification::assessNew('Otro comercio','Bogotá',$ids,static fn($url)=>[$row]);verify($r['existence']==='name_needs_review','No loose name verification');
$r=BusinessExistenceVerification::assessNew($row['razon_social_establecimiento'],'Bogotá',['rnt'=>'11','nit'=>'123456789'],static fn($url)=>[$row]);verify($r['existence']==='identifier_conflict','Detect conflicting NIT');
$r=BusinessExistenceVerification::assessNew('Comercio informal','Jericó',[]);verify($r['existence']==='needs_evidence','No identifiers does not mean nonexistent');
$r=BusinessExistenceVerification::assessNew('Comercio','Jericó',$ids,static fn($url)=>throw new RuntimeException('timeout'));verify($r['existence']==='source_unavailable','Network failure should defer');
$r=BusinessExistenceVerification::assessNew('Comercio','Jericó',$ids,static fn($url)=>[]);verify($r['existence']==='needs_evidence','No result is not proof of absence');
$pdo=new PDO('sqlite::memory:');$pdo->exec("CREATE TABLE businesses(id INTEGER,record_origin TEXT,information_status TEXT,operation_status TEXT);CREATE TABLE business_sources(business_id INTEGER);INSERT INTO businesses VALUES(1,'public_import','PUBLICO','ABIERTO');INSERT INTO business_sources VALUES(1)");
$r=BusinessExistenceVerification::assessExisting($pdo,1);verify($r['existence']==='known_catalog'&&$r['source_count']===1&&!$r['ownership_verified'],'Imported provenance reused without assigning ownership');
echo "OK: catalog provenance, exact official snapshot, historical warning, name/NIT conflicts, missing IDs and unavailable source; no ownership escalation.\n";
