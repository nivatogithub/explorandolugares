<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/ParticipationService.php';
require dirname(__DIR__,2).'/app/ParticipationMessages.php';
$locale=ParticipationMessages::locale($_GET['lang']??$_POST['lang']??'es');
$text=static fn(string $key):string=>ParticipationMessages::text($key,$locale);
try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if (!ready()) json_response(['enabled'=>false,'message'=>$text('not_ready_preview')]);
        (new ParticipationSelector(db()))->verifyStorage();
        $_SESSION['request_key'] ??= bin2hex(random_bytes(24));
        $privacy=$config['privacy'];
        $privacy['retention']=PortalSettings::retention($privacy,(string)($_GET['lang']??$_POST['lang']??'es'));
        unset($privacy['retention_en'],$privacy['retention_pt']);
        json_response(['enabled'=>true,'csrf'=>$_SESSION['csrf'],'request_key'=>$_SESSION['request_key'],'privacy'=>$privacy]);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') {header('Allow: GET, POST');json_response(['message'=>$text('method_not_allowed')],405);}
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) json_response(['message'=>$text('request_too_large')],413);
    if (!ready()) json_response(['message'=>$text('not_ready')],503);
    csrf(null,$text('csrf_invalid'));
    if (!is_string($_POST['request_key']??null) || !hash_equals($_SESSION['request_key']??'',$_POST['request_key']) || empty($_POST['request_key'])) json_response(['message'=>$text('reload')],403);
    if (!empty($_POST['website'])) json_response(['message'=>$text('validation_failed')],422);
    [$data,$errors]=lead_validation($_POST);
    if ($errors) json_response(['message'=>$text('check_fields'),'errors'=>array_map(static fn(string $key):string=>$text($key),$errors)],422);
    $selector=new ParticipationSelector(db());
    if ($selector->hasRequest($_POST['request_key'])) json_response(['message'=>$text('duplicate')]);
    if (!throttle('registro',5,3600)) json_response(['message'=>$text('rate_limit')],429);
    (new ParticipationService($selector,new ParticipationWriter(db())))->record($_POST['request_key'],$data,gmdate('Y-m-d H:i:s'));
    json_response(['message'=>$data['interest']==='impulsor_local'?$text('promoter_received'):$text('business_received')]);
} catch (Throwable $e) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'participacion');error_log('Explorando Lugares: fallo de recepción.');json_response(['message'=>$text('storage_error')],503);}
