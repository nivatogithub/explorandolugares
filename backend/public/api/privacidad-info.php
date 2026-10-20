<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');http_response_code(405);exit;}
header('Cache-Control: no-store');
$lang=is_string($_GET['lang']??null)?$_GET['lang']:'es';
$privacy=$config['privacy']??[];
json_response(['operator'=>(string)($privacy['operator']??''),'contact'=>(string)($privacy['contact']??''),'retention'=>PortalSettings::retention($privacy,$lang)]);
