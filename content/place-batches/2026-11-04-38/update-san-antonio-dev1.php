<?php
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))throw new RuntimeException('Explicit apply required');
$c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Dev only');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->beginTransaction();
$q=$p->prepare("SELECT p.id FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id WHERE p.slug='qc-06-3' AND d.slug='girardota'");$q->execute();$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)throw new RuntimeException('Identity mismatch');
$p->prepare('UPDATE points_of_interest SET latitude=?,longitude=? WHERE id=?')->execute([6.353044,-75.4259681,$ids[0]]);
$p->prepare('UPDATE point_of_interest_translations SET map_url=? WHERE point_of_interest_id=?')->execute(['https://maps.app.goo.gl/V1NEbRrWPuCqRLew6',$ids[0]]);$p->commit();echo json_encode(['id'=>$ids[0],'slug'=>'qc-06-3','latitude'=>6.353044,'longitude'=>-75.4259681,'map_url'=>'https://maps.app.goo.gl/V1NEbRrWPuCqRLew6']);
