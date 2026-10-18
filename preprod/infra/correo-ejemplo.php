<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(empty($config['local_test'])||getenv('EL_PREPROD_LOCAL')!=='1'||!preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',(string)($_SERVER['HTTP_HOST']??''))){http_response_code(404);exit;}
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src https://explorandolugares.com; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
require_once dirname(__DIR__).'/app/AccountMailTemplate.php';
$verification=($_GET['tipo']??'verificacion')==='verificacion';
$path='/correo-ejemplo.php?tipo='.($verification?'verificacion':'recuperacion').'&ejemplo=1';
$mail=AccountMailTemplate::build('cuenta@ejemplo.com','http://127.0.0.1:8088'.$path,$verification,true);
echo $mail['htmlContent'];
