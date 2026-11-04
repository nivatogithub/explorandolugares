<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/portal/app/AccountSessionValidator.php';
require '/srv/portal/app/ProductionRuntimeSettings.php';
$config=array_replace_recursive($config,ProductionRuntimeSettings::load('/srv/state/private'));
$unifiedAdminOnly=!empty($config['unified_admin_only']);
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$account=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
$place=(int)$pdo->query("SELECT id FROM destinations WHERE slug='jerico'")->fetchColumn();
$business=(int)$pdo->query('SELECT b.id FROM businesses b ORDER BY EXISTS(SELECT 1 FROM business_subscriptions s WHERE s.business_id=b.id) DESC,b.id LIMIT 1')->fetchColumn();
$subscription=$pdo->prepare('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?');$subscription->execute([$business]);$editorStatus=(int)$subscription->fetchColumn()?200:503;
$membership=$pdo->prepare('SELECT COUNT(*) FROM business_account_memberships WHERE account_id=? AND business_id=? AND role IN ("owner","manager") AND status="active"');$membership->execute([$account,$business]);$hasMembership=(int)$membership->fetchColumn()>0;
if(!$account||!$place||!$business)throw new RuntimeException('Falta cuenta, lugar o negocio sintético.');
$nameQuery=$pdo->prepare('SELECT display_name FROM accounts WHERE id=?');$nameQuery->execute([$account]);$displayName=(string)($nameQuery->fetchColumn()?:'Administrador');
function layout_http_session(PDO $pdo,int $account,string $mode,callable $test):void
{
    $id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();
    $_SESSION=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    AccountSessionSecurity::bind($pdo,$_SESSION,$account);
    if($mode==='expired')$_SESSION['last_seen']=$_SESSION['business_last_seen']=time()-3600;
    if($mode==='invalid_proof')$_SESSION['account_credential_proof']='synthetic-invalid-proof';
    if($mode==='identity_mismatch')$_SESSION['admin_account_id']=$account+999999;
    if($mode==='guest')$_SESSION=['csrf'=>bin2hex(random_bytes(32))];
    if($mode==='flag_only')$_SESSION=['admin'=>true,'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    $csrf=$_SESSION['csrf'];session_write_close();
    $directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
    if(!is_file($file))throw new RuntimeException('Falta sesión de prueba.');
    chown($file,'www-data');chmod($file,0600);
    try{$test($id,$csrf);}finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar la sesión sintética.');}
}
function layout_http_request(string $session,string $path):array
{
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$session]]);
    $html=file_get_contents('http://127.0.0.1'.$path,false,$context);
    if($html===false)throw new RuntimeException('No responde la ruta administrativa.');
    preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);
    return [(int)($status[1]??0),$html];
}
$checks=[];
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks,$displayName,$place,$business,$editorStatus):void{
    $routes=[['index.php','index.php?tab=solicitudes'],['index.php?tab=sistema','index.php?tab=sistema'],
        ['businesses.php','business-support.php'],['places.php','places.php'],['places.php?tab=crear','places.php'],['places.php?id='.$place,'places.php'],
        ['accounts.php','accounts.php'],['subscriptions.php','subscriptions.php'],['campaigns.php','campaigns.php'],['campaigns.php?tab=conexion','campaigns.php?tab=conexion'],
        ['email-templates.php','email-templates.php'],['payment-settings.php','payment-settings.php'],['settings.php','settings.php'],['reports.php','reports.php']];
    foreach($routes as [$route,$active]){
        [$status,$html]=layout_http_request($session,'/admin/'.$route);
        if($status!==200)throw new RuntimeException('HTTP administrativo incorrecto: '.$route.' / '.$status);
        $document=new DOMDocument();$previous=libxml_use_internal_errors(true);$document->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
        $sidebar=$xpath->query('//aside[contains(@class,"admin-sidebar")]');
        if($sidebar->length!==1)throw new RuntimeException('Falta sidebar único: '.$route);
        $names=$xpath->query('//aside//span[contains(@class,"admin-session-name")]');
        if($names->length!==1||$names->item(0)->textContent!==$displayName)throw new RuntimeException('Nombre de cuenta incorrecto: '.$route);
        $current=$xpath->query('//aside//a[@aria-current="page"]');
        if($current->length!==1)throw new RuntimeException('Menú activo incorrecto: '.$route);
        $href=$current->item(0)->getAttribute('href');$href=str_starts_with($href,'/')?$href:'/admin/'.$href;
        if($href!=='/admin/'.$active)throw new RuntimeException('Destino del menú activo incorrecto: '.$route);
        $logout=$xpath->query('//aside//form[@action="/negocios/salir/"]/input[@name="csrf"]');
        if($logout->length!==1||!hash_equals($csrf,$logout->item(0)->getAttribute('value')))throw new RuntimeException('Cierre de sesión/CSRF incorrecto: '.$route);
        $checks[]=['route'=>$route,'http'=>200,'active_menu_account_and_logout_preserved'=>true];
    }
    [$status,$html]=layout_http_request($session,'/business/editorial.php?business='.$business.'&admin=1');
    if($status!==$editorStatus)throw new RuntimeException('Respuesta del editor compartido incorrecta: '.$status);
    $document=new DOMDocument();$previous=libxml_use_internal_errors(true);$document->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
    foreach(['//body[@class="portal-admin"]','//main[contains(@class,"business-editorial") and contains(@class,"admin-page")]','//aside[contains(@class,"admin-sidebar")]','//aside//a[@aria-current="page" and @href="/admin/business-support.php"]','//aside//form[@action="/negocios/salir/"]/input[@name="csrf"]','//head/link[contains(@href,"/assets/admin-theme.css")]','//head/link[contains(@href,"/assets/admin-ui.css")]'] as $expression){
        if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición explícita del editor incorrecta.');
    }
    if($xpath->query('//header[@data-portal-shell] | //footer')->length!==0)throw new RuntimeException('Layout público dentro del editor administrativo.');
    foreach($xpath->query('//aside//a[@href]') as $link)if(!str_starts_with($link->getAttribute('href'),'/'))throw new RuntimeException('Enlace administrativo dependiente de la carpeta.');
    $token=$xpath->query('//aside//form/input[@name="csrf"]')->item(0)->getAttribute('value');
    if(!hash_equals($csrf,$token))throw new RuntimeException('CSRF del editor incorrecto.');
    if($editorStatus===503&&!str_contains($html,'Primero selecciona una suscripción.'))throw new RuntimeException('Fallo del editor distinto a la ausencia de suscripción de la fixture.');
    $checks[]=['route'=>'/business/editorial.php?admin=1','http'=>$status,'explicit_body_main_navigation_assets_and_logout'=>true,'fixture_has_subscription'=>$editorStatus===200,'html_without_session_token_sha256'=>hash('sha256',str_replace($csrf,'synthetic-csrf',$html))];
});
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks,$business,$editorStatus,$hasMembership):void{
    foreach(['es','en','pt-br'] as $locale){
        [$status,$html]=layout_http_request($session,'/business/editorial.php?business='.$business.'&lang='.$locale);
        if($status!==($hasMembership?$editorStatus:503))throw new RuntimeException('Respuesta del editor de cuenta incorrecta.');
        $document=new DOMDocument();$previous=libxml_use_internal_errors(true);$document->loadHTML('<?xml encoding="UTF-8">'.str_replace($csrf,'synthetic-csrf',$html),LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
        foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main[contains(@class,"business-editorial")]','//head/link[contains(@href,"/assets/account-header.css")]'] as $expression){
            if($xpath->query($expression)->length!==1)throw new RuntimeException('Layout/idioma de cuenta incorrecto: '.$locale);
        }
        if($xpath->query('//aside[contains(@class,"admin-sidebar")] | //main[contains(@class,"admin-page")]')->length!==0)throw new RuntimeException('Layout administrativo dentro de la cuenta.');
        if(!$hasMembership&&(!str_contains($document->textContent,'Este negocio no está asociado a tu cuenta.')||$xpath->query('//input[@name="action" and @value="save"]')->length!==0))throw new RuntimeException('La cuenta recibió edición sin relación con el negocio: '.$locale);
        foreach(iterator_to_array($xpath->query('//text()[normalize-space(.)=""]')) as $node)$node->parentNode->removeChild($node);
        $checks[]=['route'=>'/business/editorial.php (cuenta)','locale'=>$locale,'http'=>$status,'public_account_shell_and_access_preserved'=>true,'fixture_has_membership'=>$hasMembership,'canonical_dom_without_session_token_sha256'=>hash('sha256',$document->saveHTML())];
    }
});
foreach(['guest','expired','invalid_proof','identity_mismatch','flag_only'] as $mode){
    layout_http_session($pdo,$account,$mode,function(string $session)use(&$checks,$mode,$unifiedAdminOnly):void{
        [$status,$html]=layout_http_request($session,'/admin/places.php');
        if($mode==='flag_only'&&!$unifiedAdminOnly){
            if($status!==200||!str_contains($html,'admin-session-name">Administrador')||!str_contains($html,'action="/admin/index.php"'))throw new RuntimeException('Compatibilidad administrativa antigua cambió.');
            $checks[]=['case'=>$mode,'http'=>200,'legacy_access_and_logout_match_effective_configuration'=>true];
        }else{
            if($status!==303||str_contains($html,'admin-sidebar'))throw new RuntimeException('Acceso privado no rechazado: '.$mode.' / '.$status);
            $checks[]=['case'=>$mode,'http'=>303,'private_layout_not_rendered'=>true];
        }
    });
}
file_put_contents('/srv/state/admin-layout-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'unified_admin_only'=>$unifiedAdminOnly,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo 'OK HTTP/MariaDB: '.count($checks)." casos de menú, cuenta, CSRF, cierre de sesión y rechazo de acceso privado; sesiones sintéticas retiradas.\n";
