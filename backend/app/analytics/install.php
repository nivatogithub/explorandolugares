<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/runtime.php';
try {
 $config=measurement_config();if(strlen((string)($config['app_key']??''))<32)throw new RuntimeException('Application key unavailable');
 $pdo=measurement_db($config);$pdo->query('SELECT id FROM accounts LIMIT 0');$pdo->query('SELECT slug FROM destinations LIMIT 0');
 $service=new AnalyticsService($pdo,$config['app_key']);
 $service->install(json_decode(file_get_contents(dirname(__DIR__).'/content/coverage-plan.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/content/coverage-geography.json'),true,512,JSON_THROW_ON_ERROR));
 echo "MEASUREMENT_INSTALLED\n";
}catch(Throwable){fwrite(STDERR,"Measurement installation failed; private diagnostics suppressed.\n");exit(1);}
