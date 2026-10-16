<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/runtime.php';require __DIR__.'/BasicAudienceService.php';
try{$config=measurement_config();(new BasicAudienceService(measurement_db($config),dirname(__DIR__,2).'/public'))->install();echo "Basic aggregate page counters enabled\n";}catch(Throwable){fwrite(STDERR,"Basic counters activation failed\n");exit(1);}
