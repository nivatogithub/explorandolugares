<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessAdminWorkspace.php';
require_once dirname(__DIR__,2).'/app/BusinessAdminFilters.php';
require_once __DIR__.'/_asset.php';
require_admin_session();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
$filters=[];
foreach(['query','category','account','plan','status'] as $key)$filters[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';
$filters['destination']=max(0,(int)($_GET['destination']??0));
$error='';$rows=[];$accounts=[];
$options=['destination'=>[],'category'=>[],'plan'=>['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium']];
try{
    $all=(new BusinessAdminWorkspace(db(),private_storage_path('business-image-references')))->rows();
    foreach($all as $business){
        $options['destination'][(int)$business['destination_id']]=$business['destination_name'];
        if(BusinessAdminFilters::matches($business,$filters)&&($filters['status']===''||$business['workflow_state']===$filters['status']))$rows[]=$business;
    }
}catch(Throwable $e){
    PortalIncidents::technical($e,'negocios_locales');$error='No se pudieron cargar los negocios locales.';http_response_code(503);
}
$total=count($rows);$pages=max(1,(int)ceil($total/25));$page=min(max(1,(int)($_GET['page']??1)),$pages);
$rows=array_slice($rows,($page-1)*25,25);
$return='atencion-comercios.php?'.http_build_query([...$filters,'page'=>$page]);
foreach($rows as $row)foreach($row['accounts'] as $account)$accounts[(int)$account['id']]=$account;
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Negocios locales | Administración</title>
<?php require __DIR__.'/_theme.php';?><link rel="stylesheet" href="<?=esc(admin_asset('admin-business-workspace.css'))?>"></head>
<body class="portal-admin"><main class="wrap admin-page"><h1>Negocios locales</h1><?php require __DIR__.'/_navigation.php';?>
<p>Requisitos, contenido, paquete y prepublicación: todo desde la ficha de cada negocio.</p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php else:?>
<form method="get" class="admin-inbox-filter">
<label>Buscar negocio o lugar<input type="search" name="query" value="<?=esc($filters['query'])?>"></label>
<label>Lugar<select name="destination"><option value="">Todos</option><?php foreach($options['destination'] as $id=>$name):?><option value="<?=$id?>" <?=$filters['destination']===$id?'selected':''?>><?=esc($name)?></option><?php endforeach?></select></label>
<label>Suscripción<select name="plan"><option value="">Todas</option><?php foreach($options['plan'] as $code=>$name):?><option value="<?=esc($code)?>" <?=$filters['plan']===$code?'selected':''?>><?=esc($name)?></option><?php endforeach?></select></label>
<label>Estado<select name="status"><option value="">Todos</option><?php foreach(BusinessAdminWorkspace::STATES as $code=>$name):?><option value="<?=esc($code)?>" <?=$filters['status']===$code?'selected':''?>><?=esc($name)?></option><?php endforeach?></select></label>
<label>Cuenta<select name="account"><option value="">Todas</option><option value="yes" <?=$filters['account']==='yes'?'selected':''?>>Con cuenta</option><option value="no" <?=$filters['account']==='no'?'selected':''?>>Sin cuenta</option></select></label>
<?php if($filters['category']!==''):?><input type="hidden" name="category" value="<?=esc($filters['category'])?>"><?php endif?>
<button class="pill">Filtrar</button><a href="atencion-comercios.php">Limpiar</a></form>
<p><?=$total?> negocios.</p>
<div class="destination-admin-table admin-business-grid"><table><thead><tr><th>Negocio</th><th>Lugar</th><th>Estado</th><th>Cuenta</th><th>Suscripción</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rows as $business):?><tr>
<td><strong><?=esc($business['display_name'])?></strong><?php if($business['publication_status']==='published'&&$business['workflow_state']!=='published'):?><br><small>Conserva su versión pública</small><?php endif?></td>
<td><?=esc($business['destination_name'])?></td>
<td><select disabled data-native-select aria-label="Estado de <?=esc($business['display_name'])?>"><option selected><?=esc(BusinessAdminWorkspace::STATES[$business['workflow_state']])?></option></select></td>
<td><?php foreach($business['accounts'] as $account):?><button type="button" class="admin-account-link" data-admin-account="account-<?=(int)$account['id']?>"><?=esc($account['display_name'])?></button><?php endforeach?><?php if(!$business['accounts']):?>Sin cuenta<?php endif?></td>
<td><?=esc($options['plan'][$business['plan_code']]??$business['plan_code'])?></td>
<td><a class="admin-icon-action" href="<?=esc('paquete-editorial.php?'.http_build_query(['business'=>(int)$business['id'],'return'=>$return]))?>" title="Gestionar <?=esc($business['display_name'])?>" aria-label="Gestionar <?=esc($business['display_name'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m16 3 5 5-12 12-6 1 1-6Z"/><path d="m14 5 5 5"/></svg></a></td>
</tr><?php endforeach?><?php if(!$rows):?><tr><td colspan="6">No hay negocios con esos filtros.</td></tr><?php endif?></tbody></table></div>
<nav class="admin-business-pagination" aria-label="Páginas de negocios">
<?php if($page>1):?><a href="?<?=esc(http_build_query([...$filters,'page'=>$page-1]))?>">← Anterior</a><?php endif?>
<span>Página <?=$page?> de <?=$pages?></span>
<?php if($page<$pages):?><a href="?<?=esc(http_build_query([...$filters,'page'=>$page+1]))?>">Siguiente →</a><?php endif?></nav>
<?php foreach($accounts as $id=>$account):?><dialog id="account-<?=$id?>" class="admin-account-dialog" aria-labelledby="account-title-<?=$id?>">
<form method="dialog"><button class="admin-icon-action" aria-label="Cerrar">×</button></form>
<h2 id="account-title-<?=$id?>"><?=esc($account['display_name'])?></h2>
<dl><dt>Correo</dt><dd><?=esc($account['email'])?></dd><dt>Estado</dt><dd><?=esc(['active'=>'Activa','suspended'=>'Suspendida','pending_activation'=>'Pendiente de activar'][$account['status']]??$account['status'])?></dd>
<dt>Correo verificado</dt><dd><?=esc($account['email_verified_at']?:'Pendiente')?></dd><dt>Negocios asociados</dt><dd><?=(int)$account['business_count']?></dd></dl>
<a href="usuarios.php?q=<?=rawurlencode($account['email'])?>">Abrir cuenta</a></dialog><?php endforeach?>
<?php endif?></main><script src="<?=esc(admin_asset('admin-business-workspace.js'))?>" defer></script></body></html>
