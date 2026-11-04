<?php
declare(strict_types=1);
/** Disposable database inside the reserved dev container; never accepts a remote DSN. */
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo pruebas locales en el dev reservado.');
$app=$argv[1]??'';$public=$argv[2]??'';$fixture=$argv[3]??'';$keep=in_array('--keep-for-review',$argv,true);
if(!str_starts_with(realpath($app)?:'','/srv/state/')||!is_file($app.'/PlaceContentMigration.php'))throw new RuntimeException('Paquete local no permitido.');
require $app.'/DatabaseMaintenance.php';require $app.'/PlaceContentMigration.php';
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_destination_'.bin2hex(random_bytes(6));
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$success=false;
try{
    $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $maintenance=new DatabaseMaintenance($p,$app,$public);$maintenance->installBaseSchema();
    try{$maintenance->applyMigrations();}
    catch(RuntimeException $e){
        if(!str_contains($e->getMessage(),'022-subscription-prices'))throw $e;
        $sql=preg_replace('/^\s*--.*$/m','',file_get_contents($app.'/database/migrations/022-subscription-prices.mysql.sql'));
        foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$p->exec($statement);
        $maintenance->applyMigrations();
    }
    if($maintenance->inspect()['migrations']['060-destination-content']!=='applied')throw new RuntimeException('Falta esquema 060.');
    $maintenance->applyMigrations(['060-destination-content']);
    $data=json_decode(file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
    if(($data['source']??'')!=='versioned-content-disposable-fixture')throw new RuntimeException('Fixture no permitida.');
    $tables=$p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    // Load synthetic rows only, into this freshly created database. Preserve the
    // schema migration's reference/pricing rows if the SQLite fixture lacks them.
    $p->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($data['tables'] as $table=>$rows){
        if(!in_array($table,$tables,true)||!preg_match('/^[a-z_]+$/D',$table))continue;
        $columns=$p->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        foreach($rows as $row){
            $row=array_intersect_key($row,array_flip($columns));
            if(!$row)continue;
            $names=array_keys($row);
            $updates=implode(',',array_map(static fn($column)=>'`'.$column.'`=VALUES(`'.$column.'`)',$names));
            $p->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$names).'`) VALUES('.implode(',',array_fill(0,count($names),'?')).') ON DUPLICATE KEY UPDATE '.$updates)->execute(array_values($row));
        }
    }
    $p->exec('SET FOREIGN_KEY_CHECKS=1');
    foreach(['destinations','points_of_interest','businesses','accounts','media_assets'] as $table){
        if((int)$p->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==count($data['tables'][$table]))throw new RuntimeException('La fixture no se cargó íntegra: '.$table);
    }
    $snapshot=static function()use($p,$tables):array{
        $out=[];
        foreach($tables as $table){
            $rows=$p->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$out[$table]=$rows;
        }
        return $out;
    };
    $plan=json_decode(file_get_contents($app.'/content/destination-content-060.json'),true,512,JSON_THROW_ON_ERROR);
    $before=$snapshot();
    $service=new PlaceContentMigration($p);
    $bad=$plan;$bad['destinations']['jerico']['translations']['es']['intro']['before']='Unexpected earlier value';
    $rejected=false;try{$service->run($bad,$public,true);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'Contenido cambiado');}
    if(!$rejected||$snapshot()!==$before)throw new RuntimeException('Falló rechazo/rollback de contenido divergente.');
    $bad=$plan;$bad['destinations']['bogota']['media']['hero']['files']['assets/missing.webp']=str_repeat('0',64);
    $rejected=false;try{$service->run($bad,$public,true);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'Medio aprobado');}
    if(!$rejected||$snapshot()!==$before)throw new RuntimeException('Falló rechazo/rollback de medios.');
    $dry=$service->run($plan,$public,false);
    if($dry['changes']===0||$snapshot()!==$before)throw new RuntimeException('El preflight persistió cambios o no encontró contenido.');
    $first=$service->run($plan,$public,true);$after=$snapshot();
    $mutable=['destination_translations','destination_story_translations','point_of_interest_translations','media_assets','media_asset_translations','destination_media','point_of_interest_media','destination_content_migrations'];
    foreach($before as $table=>$rows)if(!in_array($table,$mutable,true)&&$after[$table]!==$rows)throw new RuntimeException('Cambió una tabla protegida: '.$table);
    foreach(['destinations','points_of_interest','businesses','media_assets'] as $table)if(count($before[$table])!==count($after[$table]))throw new RuntimeException('Cambió la cantidad de entidades: '.$table);
    $repeat=$service->run($plan,$public,true);
    if(!$repeat['already_applied']||$repeat['changes']!==0||$snapshot()!==$after)throw new RuntimeException('La repetición cambió registros.');
    $p->exec("UPDATE destination_translations SET intro='Edición posterior desde MariaDB' WHERE locale='es' AND destination_id=(SELECT id FROM destinations WHERE slug='jerico')");
    $edited=$snapshot();$service->run($plan,$public,true);
    if($snapshot()!==$edited)throw new RuntimeException('La repetición pisó una edición posterior.');
    // Restore this single synthetic edit for the visual review of unchanged content.
    $p->prepare('UPDATE destination_translations SET intro=? WHERE locale="es" AND destination_id=(SELECT id FROM destinations WHERE slug="jerico")')->execute([$plan['destinations']['jerico']['translations']['es']['intro']['after']]);
    $success=true;
    $report=json_encode(['driver'=>'MariaDB','database'=>$keep?$name:'disposable-dropped','dry_run'=>$dry,'first_apply'=>$first,'repeat'=>$repeat,'protected_tables_unchanged'=>true,'fixture_loaded_completely'=>true,'schema_repeated'=>true,'conflicts_rolled_back'=>true,'later_edits_preserved'=>true,'production_accessed'=>false],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    file_put_contents('/srv/state/refactor-mariadb-report.json',$report);echo $report;
}finally{
    $p=null;if(!$keep||!$success)$admin->exec('DROP DATABASE `'.$name.'`');
}
