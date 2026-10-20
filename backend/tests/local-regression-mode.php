<?php
declare(strict_types=1);
require __DIR__.'/../app/LocalRegressionMode.php';
function check(bool $condition): void { if (!$condition) throw new RuntimeException('Local regression guard failed'); }
$server=['HTTP_HOST'=>'127.0.0.1:8088','HTTP_X_EL_LOCAL_REGRESSION'=>'1'];
putenv('EL_PREPROD_LOCAL=1');
check(LocalRegressionMode::enabled(['local_test'=>true],$server));
check(!LocalRegressionMode::enabled(['local_test'=>false],$server));
check(!LocalRegressionMode::enabled([], $server));
check(!LocalRegressionMode::enabled(['local_test'=>true],array_replace($server,['HTTP_HOST'=>'explorandolugares.com'])));
check(!LocalRegressionMode::enabled(['local_test'=>true],array_replace($server,['HTTP_X_EL_LOCAL_REGRESSION'=>''])));
putenv('EL_PREPROD_LOCAL=0');
check(!LocalRegressionMode::enabled(['local_test'=>true],$server));
echo "OK: bypass only for explicitly marked local preproduction regression; production and normal requests protected.\n";
