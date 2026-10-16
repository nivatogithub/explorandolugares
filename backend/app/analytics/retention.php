<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/runtime.php';
try{$c=measurement_config();$s=new AnalyticsService(measurement_db($c),$c['app_key']);if($s->ready())$s->maintain();echo "Measurement retention checked\n";}catch(Throwable){fwrite(STDERR,"Retention check failed\n");exit(1);}
