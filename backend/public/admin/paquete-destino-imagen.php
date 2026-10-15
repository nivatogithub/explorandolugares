<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/DestinationPackage.php';
if (isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
if (empty($_SESSION['admin'])) { http_response_code(403); exit; }
$_SESSION['last_seen']=time();
$token=is_string($_GET['token']??null)?$_GET['token']:'';
$file=is_string($_GET['file']??null)?$_GET['file']:'';
$staged=$_SESSION['destination_package_staging'][$token]??null;
if (!preg_match('/^[a-f0-9]{48}$/D',$token)||!is_array($staged)||($staged['expires']??0)<time()||!isset($staged['files'][$file])) { http_response_code(404); exit; }
$entry=$staged['files'][$file];
if (!is_file($entry['path'])) { http_response_code(404); exit; }
header('Content-Type: '.$entry['mime']);
header('Content-Length: '.filesize($entry['path']));
header('Content-Disposition: inline; filename="'.basename($entry['name']).'"');
readfile($entry['path']);
