<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessAdminService.php';
require_once dirname(__DIR__,2).'/app/BusinessAdminFilters.php';

if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){
    unset($_SESSION['admin'],$_SESSION['last_seen']);
    header('Location: /admin/index.php',true,303);exit;
}
$_SESSION['last_seen']=time();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

function local_business_state(array $business):string{
    if(($business['claim_status']??'')==='EN_TRAMITE')return 'Pendiente de titularidad';
    return match($business['subscription_status']??''){
        'pending'=>'Pendiente de validar pago',
        'paid_pending_publication'=>'Pago confirmado · preparación pendiente',
        'pending_publication'=>'Pendiente de preparación editorial',
        'active'=>($business['publication_status']??'')==='published'?'Publicado':'Pendiente de publicación',
        'expired'=>'Suscripción vencida',
        'cancelled'=>'Suscripción cancelada',
        default=>($business['publication_status']??'')==='published'?'Publicado':'Pendiente de preparación editorial',
    };
}

$filters=[];
foreach(['query','category','account','plan'] as $key)$filters[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';
$filters['destination']=max(0,(int)($_GET['destination']??0));
$error='';$all=[];$rows=[];
$options=['destination'=>[],'category'=>[],'plan'=>['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium']];
try{
    $all=(new BusinessAdminService(db(),private_storage_path('business-image-references')))->all();
    foreach($all as $business){
        $options['destination'][(int)$business['destination_id']]=$business['destination_name'];
        foreach($business['category_codes'] as $i=>$code)$options['category'][$code]=$business['category_labels'][$i]??$code;
        if(BusinessAdminFilters::matches($business,$filters))$rows[]=$business;
    }
}catch(Throwable $e){
    if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'negocios_locales');
    $error='No se pudo cargar los negocios locales.';http_response_code(503);
}
$total=count($rows);$page=min(max(1,(int)($_GET['page']??1)),max(1,(int)ceil($total/25)));$rows=array_slice($rows,($page-1)*25,25);
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Negocios locales | Administración</title><?php require __DIR__.'/_theme.php';?></head>
<body class="portal-admin"><main class="wrap admin-page"><h1>Negocios locales</h1><?php require __DIR__.'/_navigation.php';?>
<p>Desde cada ficha validas titularidad y pago, preparas el paquete editorial, revisas el resultado y envías la prepublicación.</p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php else:?>
<form method="get" class="admin-inbox-filter"><label>Buscar negocio o lugar<input type="search" name="query" value="<?=esc($filters['query'])?>"></label><label>Lugar<select name="destination"><option value="">Todos</option><?php foreach($options['destination'] as $id=>$name):?><option value="<?=$id?>" <?=$filters['destination']===$id?'selected':''?>><?=esc($name)?></option><?php endforeach?></select></label><label>Suscripción<select name="plan"><option value="">Todas</option><?php foreach($options['plan'] as $code=>$name):?><option value="<?=esc($code)?>" <?=$filters['plan']===$code?'selected':''?>><?=esc($name)?></option><?php endforeach?></select></label><label>Cuenta<select name="account"><option value="">Todas</option><option value="yes" <?=$filters['account']==='yes'?'selected':''?>>Con cuenta</option><option value="no" <?=$filters['account']==='no'?'selected':''?>>Sin cuenta</option></select></label><button class="pill">Filtrar</button><a href="atencion-comercios.php">Limpiar</a></form>
<p><?=$total?> negocios.</p><div class="destination-admin-table"><table><thead><tr><th>Negocio</th><th>Lugar</th><th>Estado</th><th>Cuenta</th><th>Suscripción</th><th></th></tr></thead><tbody><?php foreach($rows as $business):?><tr><td><strong><?=esc($business['display_name'])?></strong></td><td><?=esc($business['destination_name'])?></td><td><select disabled aria-label="Estado de <?=esc($business['display_name'])?>"><option selected><?=esc(local_business_state($business))?></option></select></td><td><?php if($business['account_email']):?><a href="usuarios.php?q=<?=rawurlencode($business['account_email'])?>"><?=esc($business['account_email'])?></a><?php else:?>Sin cuenta<?php endif?></td><td><?=esc($options['plan'][$business['plan_code']]??$business['plan_code'])?></td><td><a class="pill light compact" href="paquete-editorial.php?business=<?=(int)$business['id']?>">Gestionar</a></td></tr><?php endforeach?><?php if(!$rows):?><tr><td colspan="6">No hay negocios con esos filtros.</td></tr><?php endif?></tbody></table></div>
<?php endif?></main></body></html>
