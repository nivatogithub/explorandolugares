<?php
declare(strict_types=1);
$app=dirname(__DIR__).'/app';$local=realpath(dirname(__DIR__,2).'/.local');
if(getenv('EL_TEST_APP_ROOT')){
    $app=realpath(getenv('EL_TEST_APP_ROOT'));
    if(!$app||!$local||!str_starts_with($app,$local.'/'))throw new RuntimeException('Solo paquetes locales aislados.');
}
require $app.'/PlaceRepository.php';
$file=realpath($argv[1]??'');
if(!$file||!$local||!str_starts_with($file,$local.'/'))throw new RuntimeException('Solo fixtures aisladas bajo .local.');
require __DIR__.'/support/place-reader-contract.php';
$pdo=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
checkPlaceReaderContract($pdo);
