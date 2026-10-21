<?php
declare(strict_types=1);
if(!defined('EL_PRIVATE_SNAPSHOT')||!isset($options))exit(1);
try{
 $config=require $options['config'];
 require $options['migration_root'].'/DestinationSnapshotMigration.php';
 $p=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $before=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)if(preg_match('/account|measurement|statistic|payment|subscription|contact|analytics|visit|event|trace|session|tracking|report/',$table))$before[$table]=(int)$p->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
 $snapshots=[];foreach(DestinationSnapshotMigration::SLUGS as $slug){$s=json_decode(file_get_contents($options['migration_root'].'/'.$slug.'.json'),true,512,JSON_THROW_ON_ERROR);foreach($s['tables']['business_translations'] as $tr)if(str_contains(mb_strtolower($tr['display_name']),'ficticio'))throw new RuntimeException('Ficha de prueba rechazada');$snapshots[]=$s;}
 // Preflight every destination in rollback mode before committing any of them.
 foreach($snapshots as $s)(new DestinationSnapshotMigration($p))->run($s,$options['public_root'],false);
 $reports=[];if(!empty($options['apply']))foreach($snapshots as $s)$reports[]=(new DestinationSnapshotMigration($p))->run($s,$options['public_root'],true);
 foreach($before as $table=>$count)if((int)$p->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()<$count)throw new RuntimeException('Disminución inesperada de datos protegidos');
 echo json_encode(['preflight_passed'=>true,'applied'=>!empty($options['apply']),'protected_tables_non_decreasing'=>count($before),'reports'=>$reports]);
}catch(Throwable $e){http_response_code(500);error_log('destination migration: '.$e->getMessage());echo json_encode(['error'=>'La migración de lugares no completó; revisar registro privado.']);}
