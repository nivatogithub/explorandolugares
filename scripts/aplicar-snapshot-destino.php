<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')throw new RuntimeException('Solo CLI');
$o=getopt('',['config:','snapshot:','snapshot-sha256:','public-root:','report:','apply']);foreach(['config','snapshot','snapshot-sha256','public-root','report'] as $k)if(empty($o[$k]))throw new RuntimeException('Falta --'.$k);
if(file_exists($o['report']))throw new RuntimeException('Reporte existente; no sobrescribir');
if(!hash_equals($o['snapshot-sha256'],hash_file('sha256',$o['snapshot'])))throw new RuntimeException('Hash snapshot incorrecto');
$c=require $o['config'];$s=json_decode(file_get_contents($o['snapshot']),true,512,JSON_THROW_ON_ERROR);
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);require __DIR__.'/lib/DestinationSnapshotMigration.php';
$result=(new DestinationSnapshotMigration($p))->run($s,$o['public-root'],isset($o['apply']));$result['snapshot_sha256']=$o['snapshot-sha256'];file_put_contents($o['report'],json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($result)."\n";
