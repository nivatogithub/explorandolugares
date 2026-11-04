<?php
declare(strict_types=1);

// A temporary, token-protected gateway in the reserved dev runs real multipart
// uploads. Neither this harness nor its synthetic images belongs in a release.
require_once __DIR__.'/LocalMariaDbFixture.php';

const MEDIA_CHECK_CONTROL='/srv/state/place-image-storage-control.json';

function media_check(bool $condition,string $message):void
{
    if(!$condition)throw new RuntimeException($message);
}

function media_cases():array
{
    return [
        'png'=>['hero'=>'png'], 'jpeg'=>['hero'=>'jpg'], 'webp'=>['hero'=>'webp'],
        'two_images'=>['hero'=>'png','attraction'=>'webp'],
        'external'=>['hero'=>'png'], 'ai_generated'=>['hero'=>'png'],
        'filename_spoof'=>['hero'=>'png'],
        'svg'=>['hero'=>'svg','error'=>'no se admiten SVG'],
        'small'=>['hero'=>'small','error'=>'al menos 240'],
        'wide'=>['hero'=>'wide','error'=>'10000'],
        'oversized'=>['hero'=>'oversized','error'=>'5 MB'],
        'partial'=>['hero'=>'png','error'=>'No se pudo recibir'],
        'invalid_origin'=>['hero'=>'png','error'=>'origen de imagen válido'],
        'missing_license'=>['hero'=>'png','error'=>'imagen externa'],
        'missing_alt'=>['hero'=>'png','error'=>'texto alternativo'],
        'asset_failure'=>['hero'=>'png','error'=>'database_failure'],
        'relation_failure'=>['hero'=>'png','error'=>'database_failure'],
        'second_invalid'=>['hero'=>'png','attraction'=>'svg','error'=>'no se admiten SVG'],
        'late_failure'=>['hero'=>'png','attraction'=>'webp','error'=>'database_failure'],
        'orphan'=>['attraction'=>'png','error'=>'fila sin nombre'],
    ];
}

function media_snapshot(PDO $pdo):string
{
    $rows=[];
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $tables=$pdo->query($mysql?"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME<>'el_fixture_owner' ORDER BY TABLE_NAME":'SELECT name FROM sqlite_master WHERE type="table" ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){
        media_check((bool)preg_match('/^[a-z0-9_]+$/D',$table),'Unexpected fixture table.');
        $data=$pdo->query('SELECT * FROM `'.$table.'`'.($mysql?'':' ORDER BY rowid'))->fetchAll(PDO::FETCH_ASSOC);
        if($mysql)usort($data,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
        $rows[$table]=$data;
    }
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}

function media_request_case(array $control,string $version,string $case):array
{
    $spec=media_cases()[$case];$app=$control['apps'][$version];
    require $app.'/PlaceAdminService.php';
    $root=$control['root'].'/'.$version.'-'.$case;
    media_check(!file_exists($root),'Fixture case already exists.');
    mkdir($root,0755,true);$public=$root.'/public';mkdir($public);
    if(isset($control['mariadb'])){
        $pdo=LocalMariaDbFixture::reset($control['mariadb']);
        $pdo->exec('DROP TRIGGER IF EXISTS reject_asset');$pdo->exec('DROP TRIGGER IF EXISTS reject_relation');
    }
    else{
        $pdo=new PDO('sqlite:'.$root.'/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec(file_get_contents($app.'/database/destinations.sqlite.sql'));
        $pdo->exec(file_get_contents($app.'/database/migrations/006-poi-experience-links.sqlite.sql'));
    }
    $service=new PlaceAdminService($pdo);$locales=['es','en','pt-BR'];
    $input=['slug'=>'image-fixture','destination_type'=>'municipality','country_code'=>'CO','department'=>'Prueba','publication_status'=>'draft',
        'display_order'=>1,'image_origin'=>'own','translations'=>[],'history'=>[],'reasons'=>[],
        'places'=>[['slug'=>'test-site','type'=>'place','publication_status'=>'draft','image_origin'=>'own','translations'=>[],'image_alt'=>[]]]];
    foreach($locales as $locale){
        $input['translations'][$locale]=['display_name'=>'Lugar sintético','headline'=>'Prueba privada','intro'=>'Solo pruebas'];
        $input['history'][$locale]='';$input['reasons'][$locale]='';$input['image_alt'][$locale]='Imagen de prueba';
        $input['places'][0]['translations'][$locale]=['name'=>'Atractivo sintético','description'=>'Solo pruebas','map_url'=>'https://example.invalid/map'];
        $input['places'][0]['image_alt'][$locale]='Imagen del atractivo';
    }
    $id=$service->save($input,[],[],$public);$input['id']=$id;
    $point=(int)$pdo->query('SELECT id FROM points_of_interest LIMIT 1')->fetchColumn();$input['places'][0]['id']=$point;
    $folder=$public.'/assets/destinos/'.$id;mkdir($folder,0755,true);
    $existing=$folder.'/existing.png';copy($control['fixtures']['png'],$existing);$existingHash=hash_file('sha256',$existing);
    $pdo->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height) VALUES(?,"own","fixture","","fixture","",240,160)')->execute(['assets/destinos/'.$id.'/existing.png']);
    $old=(int)$pdo->lastInsertId();
    foreach($locales as $locale)$pdo->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,"existing","")')->execute([$old,$locale]);
    $pdo->prepare('INSERT INTO destination_media VALUES(?,?,"hero",0)')->execute([$id,$old]);
    $pdo->prepare('INSERT INTO destination_media VALUES(?,?,"history",0)')->execute([$id,$old]);
    $pdo->prepare('INSERT INTO point_of_interest_media VALUES(?,?,"gallery",1)')->execute([$point,$old]);
    $input['hero_asset_id']=$input['history_asset_id']=$input['places'][0]['media_asset_id']=$old;
    if($case==='external')$input=array_replace($input,['image_origin'=>'external','image_creator'=>'Synthetic author','image_source_url'=>'https://example.invalid/source','image_license'=>'Synthetic permission','image_license_url'=>'https://example.invalid/license']);
    if($case==='ai_generated')$input['image_origin']='ai_generated';
    if($case==='invalid_origin')$input['image_origin']='invalid';
    if($case==='missing_license')$input['image_origin']='external';
    if($case==='missing_alt')unset($input['image_alt']['pt-BR']);
    if($case==='late_failure')$input['history_asset_id']=999999;
    if($case==='orphan')$input['places']=[];
    if($case==='asset_failure'&&!isset($control['mariadb']))$pdo->exec('CREATE TRIGGER reject_asset BEFORE INSERT ON media_assets BEGIN SELECT RAISE(ABORT,"synthetic insert failure");END');
    if($case==='relation_failure'&&!isset($control['mariadb']))$pdo->exec('CREATE TRIGGER reject_relation BEFORE INSERT ON destination_media WHEN NEW.role="hero" BEGIN SELECT RAISE(ABORT,"synthetic relationship failure");END');
    if(isset($control['mariadb'])){
        // Triggers are scoped to the disposable schema and removed by each reset.
        $pdo->exec('DROP TRIGGER IF EXISTS reject_asset');$pdo->exec('DROP TRIGGER IF EXISTS reject_relation');
        if($case==='asset_failure')$pdo->exec("CREATE TRIGGER reject_asset BEFORE INSERT ON media_assets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic insert failure'");
        if($case==='relation_failure')$pdo->exec("CREATE TRIGGER reject_relation BEFORE INSERT ON destination_media FOR EACH ROW BEGIN IF NEW.role='hero' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic relationship failure'; END IF; END");
    }
    $before=media_snapshot($pdo);$hero=$_FILES['hero']??[];$upload=$_FILES['attraction']??[];
    if($case==='partial')$hero['error']=UPLOAD_ERR_PARTIAL;
    $places=[];
    foreach($upload as $key=>$value)$places[$key]=[0=>$value];
    $sourceHashes=[];
    foreach(['hero','attraction'] as $field)if(isset($_FILES[$field]))$sourceHashes[]=hash_file('sha256',$_FILES[$field]['tmp_name']);
    $error=null;$saved=null;
    try{$saved=$service->save($input,$hero,$places,$public);}
    catch(Throwable $failure){$error=$failure instanceof PDOException?'database_failure':$failure->getMessage();}
    $expected=$spec['error']??null;
    media_check($expected===null?$error===null:($error!==null&&str_contains($error,$expected)),'Unexpected outcome: '.$case.' / '.($error??'accepted'));
    media_check(is_file($existing)&&hash_file('sha256',$existing)===$existingHash,'Existing image was changed or removed.');
    $newFiles=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile()&&$file->getPathname()!==$existing)$newFiles[]=$file->getPathname();
    $mediaSignature=null;
    if($expected!==null){
        media_check(media_snapshot($pdo)===$before,'Database rollback changed original rows.');
        media_check($newFiles===[],'A failed save left new files behind.');
    }else{
        media_check($saved===$id,'Place identity changed.');
        media_check(count($newFiles)===(isset($spec['hero'])?1:0)+(isset($spec['attraction'])?1:0),'Successful upload file count changed.');
        foreach($newFiles as $file){
            $relative=substr($file,strlen($public)+1);
            media_check((bool)preg_match('~^assets/destinos/'.$id.'/imagen-[a-f0-9]{16}\.(?:png|jpg|webp)$~D',$relative),'Unsafe or incompatible image path.');
            media_check(in_array(hash_file('sha256',$file),$sourceHashes,true),'Stored image bytes changed.');
            media_check((int)$pdo->query('SELECT COUNT(*) FROM media_assets WHERE asset_path='.$pdo->quote($relative))->fetchColumn()===1,'Stored file has no unique database reference.');
        }
        media_check((int)$pdo->query('SELECT COUNT(*) FROM media_asset_translations')->fetchColumn()===(1+count($newFiles))*3,'Image translations changed.');
        media_check((int)$pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM points_of_interest')->fetchColumn()===1,'Place or attraction was recreated.');
        media_check($pdo->query('SELECT publication_status FROM destinations')->fetchColumn()==='draft','A synthetic test place became published.');
        $mediaSignature=hash('sha256',json_encode($pdo->query('SELECT origin,creator,source_url,license_name,license_url,width,height FROM media_assets ORDER BY id')->fetchAll(),JSON_THROW_ON_ERROR));
    }
    return ['case'=>$case,'accepted'=>$error===null,'error'=>$error,'new_files'=>count($newFiles),'existing_image_preserved'=>true,'rollback_preserved_rows'=>$expected!==null,'metadata_signature'=>$mediaSignature];
}

if(PHP_SAPI!=='cli'){
    if(getenv('EL_PREPROD_LOCAL')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!is_file(MEDIA_CHECK_CONTROL)){http_response_code(404);exit;}
    $control=json_decode(file_get_contents(MEDIA_CHECK_CONTROL),true,512,JSON_THROW_ON_ERROR);
    if($_SERVER['REQUEST_METHOD']!=='POST'||!hash_equals($control['token'],$_SERVER['HTTP_X_EL_TEST_TOKEN']??'')){http_response_code(404);exit;}
    $version=(string)($_POST['version']??'');$case=(string)($_POST['case']??'');
    if(!isset($control['apps'][$version],media_cases()[$case])){http_response_code(400);exit;}
    header('Content-Type: application/json');header('Cache-Control: no-store');
    try{echo json_encode(media_request_case($control,$version,$case),JSON_THROW_ON_ERROR);}
    catch(Throwable $error){http_response_code(500);echo json_encode(['test_failure'=>$error->getMessage()]);}
    exit;
}

media_check(getenv('EL_PREPROD_LOCAL')==='1','Only run in the reserved dev.');
$config=require '/srv/state/private/config.php';media_check(!empty($config['local_test']),'Synthetic local dev required.');
media_check(!file_exists(MEDIA_CHECK_CONTROL),'Another media test is already running.');
$candidate=realpath($argv[1]??'');
media_check($candidate&&str_starts_with($candidate,'/srv/state/')&&is_file($candidate.'/PlaceImageStorage.php'),'Invalid candidate library path.');
$mysqlMode=($argv[2]??'')==='--mariadb';
$cases=$mysqlMode?array_intersect_key(media_cases(),array_flip(['png','two_images','missing_alt','asset_failure','relation_failure','second_invalid','late_failure'])):media_cases();
$root='/srv/state/place-image-test-'.bin2hex(random_bytes(6));mkdir($root,0777);chmod($root,0777);$fixtures=[];
$image=imagecreatetruecolor(240,160);imagefill($image,0,0,imagecolorallocate($image,40,110,85));
foreach(['png','jpg','webp'] as $format){$fixtures[$format]=$root.'/fixture.'.$format;match($format){'png'=>imagepng($image,$fixtures[$format]),'jpg'=>imagejpeg($image,$fixtures[$format]),'webp'=>imagewebp($image,$fixtures[$format])};}
foreach($mysqlMode?[]:['small'=>[239,160],'wide'=>[10001,160]] as $key=>[$w,$h]){$sample=imagecreatetruecolor($w,$h);$fixtures[$key]=$root.'/'.$key.'.png';imagepng($sample,$fixtures[$key]);}
$fixtures['svg']=$root.'/invalid.svg';file_put_contents($fixtures['svg'],'<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160"></svg>');
if(!$mysqlMode){$fixtures['oversized']=$root.'/oversized.png';file_put_contents($fixtures['oversized'],file_get_contents($fixtures['png']).str_repeat('x',5*1024*1024+1));}
$control=['root'=>$root,'token'=>bin2hex(random_bytes(32)),'fixtures'=>$fixtures,'apps'=>['baseline'=>'/srv/portal/app','candidate'=>$candidate]];
$databaseFixture=null;
try{
    if($mysqlMode){
        $databaseFixture=new LocalMariaDbFixture($config,$control['token']);
        $control['mariadb']=$databaseFixture->connection();
        echo 'MariaDB: ordinary tables prepared with '.$databaseFixture->foreignKeys." foreign keys.\n";
    }
    file_put_contents(MEDIA_CHECK_CONTROL,json_encode($control,JSON_THROW_ON_ERROR));chmod(MEDIA_CHECK_CONTROL,0644);
    $results=[];
    foreach(['baseline','candidate'] as $version)foreach($cases as $case=>$spec){
        $boundary='el-test-'.bin2hex(random_bytes(12));$body='';
        foreach(['version'=>$version,'case'=>$case] as $key=>$value)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$key."\"\r\n\r\n".$value."\r\n";
        foreach(['hero','attraction'] as $field)if(isset($spec[$field])){
            $fixture=$fixtures[$spec[$field]];$name=$case==='filename_spoof'?'../../untrusted.php':basename($fixture);
            $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$field.'"; filename="'.$name."\"\r\nContent-Type: application/octet-stream\r\n\r\n".file_get_contents($fixture)."\r\n";
        }
        $body.='--'.$boundary."--\r\n";
        $context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>30,'header'=>'Content-Type: multipart/form-data; boundary='.$boundary."\r\nX-EL-Test-Token: ".$control['token'], 'content'=>$body]]);
        $response=file_get_contents('http://127.0.0.1/__local-place-media-check.php',false,$context);
        media_check($response!==false,'Upload request failed.');
        $result=json_decode($response,true,512,JSON_THROW_ON_ERROR);
        media_check(!isset($result['test_failure']),'HTTP fixture failed: '.($result['test_failure']??''));
        $results[$version][$case]=$result;
    }
    media_check($results['baseline']===$results['candidate'],'Image/storage behavior changed between baseline and candidate.');
    file_put_contents('/srv/state/place-image-storage-report.json',json_encode(['production_accessed'=>false,'database'=>$databaseFixture?'disposable ordinary MariaDB tables with foreign keys':'disposable SQLite fixtures','foreign_keys'=>$databaseFixture?->foreignKeys,'original_portal_rows_written'=>false,'auto_increment_gaps_allowed'=>$databaseFixture!==null,'real_http_multipart'=>true,'cases_per_version'=>count($cases),'baseline_and_candidate_identical'=>true,'existing_images_unchanged'=>true,'checks'=>$results['candidate']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
    echo 'OK: '.count($cases)." casos con subidas HTTP reales en ambas versiones; formatos, metadatos, identidades, rollback y archivos existentes conservados.\n";
}finally{
    @unlink(MEDIA_CHECK_CONTROL);
    if($databaseFixture)$databaseFixture->close();
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}
    rmdir($root);
}
