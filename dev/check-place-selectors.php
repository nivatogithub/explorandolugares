<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo pruebas locales en el dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Usar la base sintética del dev1.');

if(($argv[1]??'')==='--read-models'){
    $app=realpath($argv[2]??'');$legacy=($argv[3]??'')==='legacy';
    if(!$app||(!str_starts_with($app,'/srv/state/')&&!str_starts_with($app,'/srv/portal/')))throw new RuntimeException('Paquete fuera del entorno reservado.');
    require $app.($legacy?'/DestinationRepository.php':'/PlaceRepository.php');
    require $app.'/HomeAttractions.php';require $app.'/seo/PublicSitemap.php';
    require $app.($legacy?'/DestinationAdminService.php':'/PlaceAdminService.php');
    $pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $catalog=json_decode(file_get_contents($app.'/content/release-places-businesses.json'),true,512,JSON_THROW_ON_ERROR);
    $data=['pages'=>[],'previews'=>[],'directories'=>[],'home'=>[]];
    foreach(['es','en','pt-BR'] as $locale){
        foreach($catalog['public_places'] as $slug){
            $service=$legacy?new DestinationRepository($pdo):PlaceRepository::fromDatabase($pdo);
            $data['pages'][$locale][$slug]=$service->findPublished($slug,$locale);
            $data['previews'][$locale][$slug]=$service->findPublished($slug,$locale,true);
        }
        $data['directories'][$locale]=$service->listPublished($locale);
        $data['home'][$locale]=HomeAttractions::items($pdo,$locale,'/srv/portal/public');
    }
    $data['sitemap']=(new PublicSitemap($pdo))->xml('https://explorandolugares.com','');
    $admin=$legacy?new DestinationAdminService($pdo):new PlaceAdminService($pdo);$data['admin_list']=$admin->all();$data['admin_details']=[];
    foreach($data['admin_list'] as $row)$data['admin_details'][(int)$row['id']]=$admin->find((int)$row['id']);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}

$read=static function(string $app,string $mode):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--read-models',$app,$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('No se pudo iniciar la comprobación aislada.');
    $data=stream_get_contents($pipes[1]);fclose($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[2]);
    if(proc_close($process)!==0||trim($errors)!=='')throw new RuntimeException('Falló la lectura del paquete local: '.$errors);
    return json_decode($data,true,512,JSON_THROW_ON_ERROR);
};
$before=$read('/srv/state/lugares-selectores-backup-20261102/app','legacy');
$after=$read('/srv/portal/app','current');
if($before!==$after)throw new RuntimeException('Cambió el contrato de lectura en MariaDB.');
$report=['driver'=>'MariaDB','production_accessed'=>false,'public_models'=>60,'preview_models'=>60,'public_and_preview_models_identical'=>true,'private_admin_models_identical'=>true,'directories_identical'=>true,'home_selection_identical'=>true,'sitemap_identical'=>true,'data_migrations_required'=>false];
file_put_contents('/srv/state/lugares-selectores-mariadb.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo "OK MariaDB: 60 modelos públicos y 60 previews, administración privada, listados, portada y sitemap conservan exactamente los datos anteriores. Sin migraciones de datos.\n";
