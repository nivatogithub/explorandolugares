<?php
declare(strict_types=1);
function testLocalAccess(string $localFlag,string $adminFlag,bool $localConfig,string $host,string $path,bool $expected):void {
 putenv('EL_PREPROD_LOCAL='.$localFlag);putenv('EL_PREPROD_ADMIN_OPEN='.$adminFlag);
 $config=['local_test'=>$localConfig];$_SERVER=['HTTP_HOST'=>$host,'REQUEST_URI'=>$path];$_SESSION=[];
 require __DIR__.'/admin-access.php';
 if(!empty($_SESSION['admin'])!==$expected)throw new RuntimeException('Unexpected administrator access');
}
testLocalAccess('1','1',true,'127.0.0.1:8088','/admin/negocios.php',true);
testLocalAccess('1','1',true,'localhost:8088','/admin/index.php',true);
testLocalAccess('','1',true,'localhost:8088','/admin/index.php',false);
testLocalAccess('1','',true,'localhost:8088','/admin/index.php',false);
testLocalAccess('1','1',false,'localhost:8088','/admin/index.php',false);
testLocalAccess('1','1',true,'explorandolugares.com','/admin/index.php',false);
testLocalAccess('1','1',true,'localhost:8088','/negocios/acceso/',false);
$_SESSION=['admin'=>true,'preprod_admin_open'=>true];putenv('EL_PREPROD_ADMIN_OPEN=0');
require __DIR__.'/admin-access.php';
if(!empty($_SESSION['admin']))throw new RuntimeException('Local access was not revoked');
echo "OK: local-only administrator access, host/config/environment guards and revocation.\n";
