<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv, true)) throw new RuntimeException('Solo ejecución explícita en dev1');
$config = require '/srv/state/private/config.php';
if (empty($config['local_test']) || !str_contains($config['dsn'], 'host=127.0.0.1;dbname=el_test_destination_')) throw new RuntimeException('Entorno incorrecto');
require_once '/srv/portal/app/BusinessPublicImporter.php';
$pdo = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$rows = json_decode(file_get_contents('/srv/state/cobertura-lote30-20261104/lote30-contraste-04.json'), true, 512, JSON_THROW_ON_ERROR);
if (count($rows)!==6) throw new RuntimeException('Conteo inesperado');
$find=$pdo->prepare('SELECT b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');
$update=$pdo->prepare('UPDATE businesses SET latitude=?,longitude=?,location_source="osm",publication_status="published" WHERE id=? AND record_origin="public_import" AND publication_status="draft"');
$sub=$pdo->prepare('UPDATE business_subscriptions SET status="active",published_at=COALESCE(published_at,NOW()),starts_at=COALESCE(starts_at,NOW()) WHERE business_id=? AND plan_code="free" AND status="pending_publication"');
$results=[];
foreach($rows as $r){
 $find->execute([$r['destination_slug'],$r['name']]); if($find->fetch()){ $results[]=['name'=>$r['name'],'status'=>'existing_not_changed']; continue; }
 try{
  $item=['name'=>$r['name'],'category_code'=>$r['category_code'],'information_status'=>'PUBLICO','sources'=>[['url'=>$r['source_map'],'checked_at'=>'2026-11-04','note'=>'© OpenStreetMap contributors; punto de referencia.'],['url'=>$r['source_identity'],'checked_at'=>'2026-11-04','note'=>$r['evidence']]]];
  BusinessPublicImporter::import($pdo,['schema_version'=>'business-import-v1','destination_slug'=>$r['destination_slug'],'businesses'=>[$item]]);
  $find->execute([$r['destination_slug'],$r['name']]); $ids=$find->fetchAll(); if(count($ids)!==1)throw new RuntimeException('Identidad ambigua');
  $id=(int)$ids[0]['id']; $pdo->beginTransaction(); $update->execute([$r['latitude'],$r['longitude'],$id]); if($update->rowCount()!==1)throw new RuntimeException('No se pudo activar el registro'); $sub->execute([$id]); $pdo->commit();
  $results[]=['destination'=>$r['destination_slug'],'name'=>$r['name'],'status'=>'published_dev1'];
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$results[]=['name'=>$r['name'],'status'=>'error','error'=>$e->getMessage()];}
}
echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
