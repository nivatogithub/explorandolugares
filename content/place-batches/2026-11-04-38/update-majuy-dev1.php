<?php
if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv, true)) throw new RuntimeException('Explicit apply required');
$c=require '/srv/state/private/config.php';
if (empty($c['local_test']) || !str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_')) throw new RuntimeException('Dev only');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->beginTransaction();
$q=$p->query("SELECT p.id FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id WHERE d.slug='cota' AND p.slug='qc-17-7'");$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)throw new RuntimeException('Identity mismatch');$id=$ids[0];
$q=$p->prepare('SELECT media_asset_id FROM point_of_interest_media WHERE point_of_interest_id=?');$q->execute([$id]);$media=$q->fetchAll(PDO::FETCH_COLUMN);if(count($media)!==1 || (int)$media[0]!==680)throw new RuntimeException('Media identity mismatch');
$p->prepare('UPDATE points_of_interest SET latitude=?,longitude=? WHERE id=?')->execute([4.8048233,-74.1195667,$id]);
$translations=['es'=>['Serranía del Majuy · mirador rocoso','Afloramiento rocoso de la serranía del Majuy, en Cota, con vista sobre los cultivos de la sabana. La ubicación corresponde al punto de la fotografía, no al acceso al sendero.'],'en'=>['Majuy mountain range · rocky viewpoint','Rocky outcrop in the Majuy mountain range, in Cota, overlooking the farmland of the savanna. The location marks the photograph’s viewpoint, not the trail entrance.'],'pt-BR'=>['Serra do Majuy · mirante rochoso','Afloramento rochoso na serra do Majuy, em Cota, com vista para os cultivos da savana. A localização corresponde ao ponto da fotografia, não ao acesso à trilha.']];
$q=$p->prepare('UPDATE point_of_interest_translations SET name=?,description=?,map_url=? WHERE point_of_interest_id=? AND locale=?');foreach($translations as $locale=>$v)$q->execute([$v[0],$v[1],'',$id,$locale]);
$p->prepare('UPDATE media_assets SET source_url=? WHERE id=?')->execute(['https://es.wikiloc.com/rutas-senderismo/serrania-del-majuy-cota-76399724#wp-76399729',680]);$p->commit();echo json_encode(['id'=>$id,'asset_id'=>680,'latitude'=>4.8048233,'longitude'=>-74.1195667]);
