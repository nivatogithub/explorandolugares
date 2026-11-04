<?php
declare(strict_types=1);
// Exercise the real MySQL dialect using connection-local copies, never live rows.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
$app=realpath($argv[1]??'');
if(!$app||!str_starts_with($app,'/srv/state/')||!is_file($app.'/PlaceAdminService.php'))throw new RuntimeException('Biblioteca candidata local requerida.');
require $app.'/PlaceAdminService.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$schema=file_get_contents($app.'/database/destinations.sqlite.sql');
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?([a-z_]+)/',$schema,$matches);
$tables=$matches[1];if(count($tables)<10)throw new RuntimeException('Esquema de prueba incompleto.');
$pdo->exec('CREATE TEMPORARY TABLE local_rollback_probe(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('INSERT INTO local_rollback_probe VALUES(1)');$pdo->beginTransaction();
$pdo->exec('DELETE FROM local_rollback_probe WHERE id=1');$pdo->exec('INSERT INTO local_rollback_probe VALUES(2)');$pdo->rollBack();
if($pdo->query('SELECT id FROM local_rollback_probe')->fetchAll(PDO::FETCH_COLUMN)!==[1])throw new RuntimeException('La prueba mínima de rollback temporal no conserva la fila.');
$pdo->exec('DROP TEMPORARY TABLE local_rollback_probe');
$created=[];
try{
    foreach($tables as $table){
        $rows=$pdo->query('SELECT * FROM '.$table)->fetchAll();
        $temporary='local_copy_'.$table;
        $pdo->exec('CREATE TEMPORARY TABLE '.$temporary.' LIKE '.$table);$created[]=$temporary;
        $pdo->exec('ALTER TABLE '.$temporary.' RENAME TO '.$table);array_pop($created);$created[]=$table;
        if($rows){
            $columns=array_keys($rows[0]);$quoted=implode(',',array_map(static fn($c)=>'`'.$c.'`',$columns));
            $q=$pdo->prepare('INSERT INTO '.$table.' ('.$quoted.') VALUES('.implode(',',array_fill(0,count($columns),'?')).')');
            foreach($rows as $row)$q->execute(array_values($row));
        }
    }
    $service=new PlaceAdminService($pdo);$locales=['es','en','pt-BR'];
    $input=['slug'=>'local-persistence-fixture','destination_type'=>'municipality','country_code'=>'CO','department'=>'Prueba','publication_status'=>'draft','display_order'=>9000,'translations'=>[],'history'=>[],'reasons'=>[],'sources'=>[],
        'places'=>[['slug'=>'local-attraction','type'=>'place','publication_status'=>'draft','translations'=>[]]]];
    foreach($locales as $locale){
        $input['translations'][$locale]=['display_name'=>'Prueba privada','headline'=>'Título','intro'=>'Texto'];
        $input['history'][$locale]='Historia';$input['reasons'][$locale]='Motivo';$input['sources'][$locale]='https://example.invalid/source';
        $input['places'][0]['translations'][$locale]=['name'=>'Atractivo privado','description'=>'Descripción','map_url'=>'https://example.invalid/map'];
    }
    $id=$service->save($input,[],[],'/unused-no-uploads');$input['id']=$id;
    $saved=$service->find($id);$point=$saved['places'][0]['id'];$input['places'][0]['id']=$point;
    $storyIds=$pdo->query('SELECT id FROM destination_story_blocks WHERE destination_id='.(int)$id.' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $input['translations']['es']['headline']='Texto editado';$input['history']['es']='Historia editada';
    if($service->save($input,[],[],'/unused-no-uploads')!==$id)throw new RuntimeException('Cambió ID del lugar.');
    $edited=$service->find($id);
    if($edited['places'][0]['id']!==$point||$edited['translations']['es']['headline']!=='Texto editado'||$storyIds!==$pdo->query('SELECT id FROM destination_story_blocks WHERE destination_id='.(int)$id.' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))throw new RuntimeException('Edición perdió identidad o traducciones.');
    $snapshot=static function() use($pdo,$tables):string {
        $data=[];foreach($tables as $table){$rows=$pdo->query('SELECT * FROM '.$table)->fetchAll();usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$data[$table]=$rows;}
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    };
    $asset=(int)$pdo->query('SELECT id FROM media_assets ORDER BY id LIMIT 1')->fetchColumn();
    if(!$asset)throw new RuntimeException('Falta medio sintético existente.');
    $input['hero_asset_id']=$asset;$input['places'][0]['media_asset_id']=$asset;
    $service->save($input,[],[],'/unused-no-uploads');
    // MariaDB 10.11 temporary InnoDB tables lose a deleted/reinserted same key
    // after rollback. Probe and report this limitation independently of app code.
    $pdo->exec('CREATE TEMPORARY TABLE local_same_key_probe(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO local_same_key_probe VALUES(1)');$pdo->beginTransaction();
    $pdo->exec('DELETE FROM local_same_key_probe WHERE id=1');$pdo->exec('INSERT INTO local_same_key_probe VALUES(1)');$pdo->rollBack();
    $temporarySameKeyRollbackSupported=$pdo->query('SELECT id FROM local_same_key_probe')->fetchAll(PDO::FETCH_COLUMN)===[1];
    $pdo->exec('DROP TEMPORARY TABLE local_same_key_probe');

    // An early edit failure tests restoration of existing rows without that
    // temporary-table limitation; SQLite covers late edit failures separately.
    $pdo->exec("ALTER TABLE destination_translations ADD CONSTRAINT local_reject_headline CHECK (headline <> 'local-rollback-marker')");
    $before=$snapshot();$bad=$input;$bad['translations']['es']['headline']='local-rollback-marker';$failed=false;
    try{$service->save($bad,[],[],'/unused-no-uploads');}catch(PDOException){$failed=true;}
    if(!$failed||$pdo->inTransaction()||$snapshot()!==$before)throw new RuntimeException('Rollback incompleto tras fallo temprano de edición.');

    // A late creation failure exercises every writer before the final binding,
    // without deleting/reinserting keys that existed before this transaction.
    $pdo->exec("ALTER TABLE destination_media ADD CONSTRAINT local_reject_history CHECK (destination_id <= {$id} OR role <> 'history')");
    $before=$snapshot();$bad=$input;$bad['id']=0;$bad['slug']='local-persistence-failure';$bad['history_asset_id']=$asset;$bad['places'][0]['id']=0;$failed=false;
    try{$service->save($bad,[],[],'/unused-no-uploads');}catch(PDOException){$failed=true;}
    if(!$failed||$pdo->inTransaction()||$snapshot()!==$before)throw new RuntimeException('Rollback incompleto tras fallo final de creación.');
    $service->archive($id);if($service->find($id)['publication_status']!=='archived')throw new RuntimeException('Archivado incorrecto.');
    $service->delete($id);if($service->find($id)!==null)throw new RuntimeException('Borrado incorrecto.');
    file_put_contents('/srv/state/place-persistence-report.json',json_encode(['driver'=>'MariaDB','connection_local_temporary_tables'=>true,'production_accessed'=>false,'original_rows_written'=>false,'creation_edit_translations_media_archive_delete'=>true,'place_attraction_story_ids_preserved'=>true,'early_edit_and_late_creation_full_rollback'=>true,'temporary_same_key_rollback_supported'=>$temporarySameKeyRollbackSupported,'limitations'=>'Temporary LIKE tables retain indexes but do not reproduce foreign keys. Same-key delete/reinsert rollback is probed separately; MariaDB 10.11 local returns no row even without application code. Late edit and FK rollback are verified with isolated SQLite fixtures against both versions.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
    echo "OK MariaDB: creación, edición, traducciones, medios, IDs, archivado, borrado y rollback temprano/final en tablas temporales; filas originales intactas.\n";
}finally{
    if($pdo->inTransaction())$pdo->rollBack();
    foreach(array_reverse($created) as $table)$pdo->exec('DROP TEMPORARY TABLE IF EXISTS '.$table);
}
