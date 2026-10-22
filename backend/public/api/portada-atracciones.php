<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/HomeAttractions.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
header('Cache-Control: no-store');
$locale=is_string($_GET['lang']??null)&&in_array($_GET['lang'],['es','en','pt-BR'],true)?$_GET['lang']:'es';
try{json_response(['items'=>HomeAttractions::items(db(),$locale,dirname(__DIR__)),'selection'=>'editorial','ratings'=>false]);}
catch(Throwable){json_response(['items'=>[],'available'=>false],503);}
