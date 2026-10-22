<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/BusinessChannelImporter.php';
require dirname(__DIR__,2).'/app/BusinessCrmRepository.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();$error='';$result=null;$ready=false;$rows=[];$total=0;
$crm=null;$overview=['rows'=>[],'total'=>0,'with_contact'=>0,'without_contact'=>0,'page'=>1,'page_count'=>1];$selectedBusiness=null;$profileContacts=[];$destinations=[];
$accountService=null;$accountProfileReady=false;$responsibleAccounts=[];$accountResult='';$recipientReady=false;
try{$accountService=new AccountProfileService(db());$accountProfileReady=$accountService->ready();}
catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'contactos_negocios');$error='No se pudo consultar la cuenta responsable. Revisa Base de datos.';}
try{db()->query('SELECT id FROM business_research_contacts LIMIT 1');$ready=true;$recipientReady=BusinessResearchContactImporter::recipientReady(db());}
catch(PDOException $exception){$error='Falta habilitar la carga de canales privados (026). Puedes revisarla en Base de datos.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();$action=(string)($_POST['action']??'');
        if($action==='save_channel_recipient'){
            BusinessChannelImporter::saveRecipient(db(),(int)($_POST['business_id']??0),(int)($_POST['channel_id']??0),$_POST['recipient_label']??'');
            $accountResult='Destinatario del canal guardado. No se creó ninguna cuenta.';
        }elseif($action==='save_responsible_phone'){
            if(!$accountProfileReady)throw new RuntimeException('Falta habilitar el teléfono de la cuenta en Base de datos.');
            $accountService->savePhoneForBusiness((int)($_POST['business_id']??0),$_POST['contact_phone']??'');
            $accountResult='Teléfono del responsable guardado en su cuenta.';
        }elseif(in_array($action,['import_private_contacts','import_business_channels'],true)){
        if(!$ready)throw new RuntimeException($error);
        $upload=$_FILES['package']??null;
        if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??''))||(int)($upload['size']??0)<2||(int)$upload['size']>2*1024*1024)throw new RuntimeException('Selecciona un archivo de canales JSON de hasta 2 MB.');
        $package=json_decode((string)file_get_contents($upload['tmp_name']),true,128,JSON_THROW_ON_ERROR);
        if(!is_array($package))throw new RuntimeException('El paquete debe ser un objeto JSON.');
        $result=BusinessChannelImporter::import(db(),$package);
        }else throw new RuntimeException('Acción no válida.');
    }catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'contactos_negocios');http_response_code(400);$error=$exception instanceof PDOException?'No se guardaron los datos. Revisa la estructura de Base de datos.':($exception instanceof JsonException?'El archivo no contiene JSON válido.':$exception->getMessage());}
}
$tab=($_GET['tab']??'')==='cargar'?'cargar':'consultar';
if($_SERVER['REQUEST_METHOD']==='POST')$tab=in_array($_POST['action']??'',['import_private_contacts','import_business_channels'],true)?'cargar':'consultar';
$query=trim((string)($_GET['q']??''));$businessId=max(0,(int)($_GET['business_id']??($_POST['business_id']??0)));$page=max(1,(int)($_GET['page']??1));$pageSize=50;$pageCount=1;
$accountScope=(string)($_GET['account']??'without');if(!in_array($accountScope,['with','without'],true))$accountScope='without';
$crmFilters=['account'=>$accountScope,'query'=>$query,'destination'=>(string)($_GET['destination']??''),'subscription'=>(string)($_GET['subscription']??'free'),'contact'=>(string)($_GET['contact']??'')];
try{
    $crm=new BusinessCrmRepository(db());$overview=$crm->overview($crmFilters,max(1,(int)($_GET['crm_page']??1)));
    $destinations=db()->query('SELECT d.slug,COALESCE(t.display_name,d.slug) AS display_name FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY display_name')->fetchAll(PDO::FETCH_ASSOC);
    if($businessId){
        $selectedBusiness=$crm->find($businessId);$q=db()->prepare('SELECT channel,contact_value,is_public FROM business_contacts WHERE business_id=? ORDER BY display_order,id');$q->execute([$businessId]);$profileContacts=$q->fetchAll(PDO::FETCH_ASSOC);
        if($selectedBusiness&&$accountProfileReady){$responsibleAccounts=$accountService->forBusiness($businessId);}
    }
}catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'contactos_negocios');$error=$exception instanceof PDOException?'No se pudo consultar el CRM. Revisa la estructura de negocios en Base de datos.':$exception->getMessage();}
if($ready&&$businessId){
    $where=' WHERE 1=1';$params=[];
    if($businessId){$where.=' AND b.id=?';$params[]=$businessId;}
    $from=' FROM business_research_contacts r JOIN businesses b ON b.id=r.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id';
    $q=db()->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($params);$total=(int)$q->fetchColumn();$pageCount=max(1,(int)ceil($total/$pageSize));$page=min($page,$pageCount);
    $q=db()->prepare('SELECT r.*,b.public_id,t.display_name,d.slug AS destination_slug'.$from.$where.' ORDER BY t.display_name,r.channel,r.id LIMIT '.$pageSize.' OFFSET '.(($page-1)*$pageSize));$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
}
$emailPermissions=[];try{foreach(db()->query('SELECT email,decision FROM business_email_permissions')->fetchAll(PDO::FETCH_ASSOC) as $permission)$emailPermissions[$permission['email']]=$permission['decision'];}catch(PDOException){}
$labels=['email'=>'Correo','phone'=>'Celular / teléfono','phone_fixed'=>'Teléfono fijo','whatsapp'=>'WhatsApp','instagram'=>'Instagram','facebook'=>'Facebook','website'=>'Sitio web','linkedin'=>'LinkedIn'];
$evidenceLabels=['DECLARADO_RNT_SIN_CORROBORACION_CLIENTES'=>'Declarado en RNT; atención a clientes pendiente de corroborar','NUMERO_INCOMPLETO_O_FORMATO_ANTIGUO'=>'Número incompleto o de formato antiguo; revisar','PUBLICADO_PARA_CLIENTES'=>'Anunciado públicamente para atención a clientes'];
$planLabels=['free'=>'Gratis','basic'=>'Básico','featured'=>'Tarjeta Ampliada','page'=>'Página Comercial','page_premium'=>'Premium'];
$stageLabels=['INVESTIGADO_NO_CONTACTADO'=>'Investigado, sin contactar','INTERES_REPORTADO_POR_PROPIETARIO'=>'Interés reportado por el propietario','INTERES_REPORTADO_IDENTIDAD_PENDIENTE'=>'Interés reportado; identidad pendiente'];
function crm_link(array $filters,array $extra=[]):string{return '?'.http_build_query([...$filters,...$extra]);}

$subscriptionLabels=['pending_publication'=>'Pendiente de publicación','pending'=>'Pendiente de revisión','active'=>'Vigente','expired'=>'Vencida','cancelled'=>'Cancelada'];
$authorizationLabels=['NO_OBTENIDA'=>'Sin autorización registrada','OBTENIDA'=>'Autorización registrada'];
$sourceLabels=['RNT'=>'Registro Nacional de Turismo','WEB_PROPIA'=>'Sitio web del negocio','DIRECTORIO_TURISTICO_FECHA_NO_ACREDITADA'=>'Directorio turístico; fecha pendiente de confirmar','DIRECTORIO_CLIENTES'=>'Directorio de atención a clientes','DIRECTORIO_ARTESANAL'=>'Directorio artesanal','AUTOR_DECLARA_CANAL_COMERCIAL'=>'Canal declarado por su autor','PUBLICACION_TURISTICA'=>'Publicación turística'];
$filterState=['account'=>$accountScope,'q'=>$query,'destination'=>$crmFilters['destination'],'subscription'=>$crmFilters['subscription'],'contact'=>$crmFilters['contact'],'crm_page'=>$overview['page']];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Gestión comercial | Administración</title>
<link rel="stylesheet" href="../assets/admin.css?v=20261015-channel-dialog">
<script src="../assets/admin-contact-file.js" defer></script>
<script src="../assets/admin-business-channels.js" defer></script>
<?php require __DIR__.'/_theme.php'; ?></head>
<body class="portal-admin"><main class="wrap admin-page destination-admin crm-page">
<?php require __DIR__.'/_navigation.php'; ?>
<div class="crm-heading"><div><p class="crm-eyebrow">Seguimiento comercial</p><h1>Gestión comercial</h1><p>Consulta contactos para seguimiento y campañas. Los negocios sin cuenta asociada se muestran separados de los que ya tienen responsable. Los datos personales de la cuenta y los canales del establecimiento conservan su procedencia y privacidad.</p></div><span class="crm-private-badge">Solo administración</span></div>
<nav class="admin-workflow-tabs" aria-label="Tareas de canales y responsable">
<a href="contactos-negocios.php" <?=$tab==='consultar'?'aria-current="page"':''?>>Consultar negocios</a>
<a href="contactos-negocios.php?tab=cargar" <?=$tab==='cargar'?'aria-current="page"':''?>>Carga masiva de canales</a>
</nav>
<?php if($error):?><div class="notice crm-feedback" role="alert"><strong>No se pudo completar la operación</strong><p><?=esc($error)?></p><?php if(!$ready):?><a href="base-datos.php">Revisar configuración</a><?php endif ?></div><?php endif ?>
<?php if($accountResult):?><div class="crm-feedback crm-feedback-success" role="status"><?=esc($accountResult)?></div><?php endif ?>
<?php if($result):?><div class="crm-feedback crm-feedback-success" role="status"><strong>Canales guardados para <?=esc($result['businesses'])?> negocios</strong><p><?=esc($result['inserted'])?> registros de canal y fuente añadidos · <?=esc($result['existing'])?> ya estaban guardados.</p><p>Se conservaron las fichas existentes y su publicación.</p><a href="contactos-negocios.php">Consultar los canales guardados →</a></div><?php endif ?>
<?php if($tab==='cargar'):?>
<section class="crm-panel crm-upload-panel" aria-labelledby="crm-upload-title">
<div class="crm-section-heading"><div><p class="crm-eyebrow">Completar información</p><h2 id="crm-upload-title">Añadir canales a negocios registrados</h2><p>Usa el archivo de canales para completar las fichas que ya importaste. La asociación se hace por nombre y destino; no necesitas exportar IDs.</p></div></div>
<ol class="crm-upload-steps"><li><span>1</span><div><strong>Negocios ya registrados</strong><p>Las fichas deben existir antes de cargar sus canales.</p></div></li><li><span>2</span><div><strong>Selecciona el archivo de canales</strong><p>Elige el JSON complementario de la investigación.</p></div></li><li><span>3</span><div><strong>Guarda y consulta</strong><p>Los canales quedan disponibles aquí, para administración.</p></div></li></ol>
<form method="post" enctype="multipart/form-data" class="crm-upload-form" data-contact-upload>
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import_business_channels">
<div class="crm-file-picker"><svg aria-hidden="true" width="32" height="38" viewBox="0 0 32 38" fill="none"><path d="M6 1h13l12 12v21a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V6a5 5 0 0 1 5-5Z" stroke="currentColor" stroke-width="1.6"/><path d="M19 1v12h12M8 22h16M8 28h10" stroke="currentColor" stroke-width="1.6"/></svg>
<div class="crm-file-copy"><strong>Archivo de canales del negocio</strong><p>Formato JSON (.json) · hasta 2 MB</p><label class="crm-file-control" for="crm-contact-file"><span>Seleccionar archivo</span><input class="crm-file-native" id="crm-contact-file" type="file" name="package" accept=".json,application/json" aria-describedby="crm-file-name crm-file-help" required <?=$ready?'':'disabled'?>></label><p id="crm-file-name" class="crm-file-name" role="status" aria-live="polite">Ningún archivo seleccionado</p></div>
</div>
<p id="crm-file-help" class="crm-help">Selecciona el archivo complementario de canales. Se aceptan también los archivos anteriores llamados contactos privados.</p>
<p class="crm-file-error" data-file-error role="alert" hidden></p>
<div class="crm-upload-actions"><button class="pill compact" type="submit" <?=$ready?'':'disabled'?>>Guardar canales del archivo</button><a href="contactos-negocios.php">Volver a consultar</a></div>
</form>
<div class="crm-upload-note"><strong>¿Qué cambia al guardar?</strong><p>Se añaden canales y sus fuentes a los negocios existentes. No se crean otras fichas, no se publican estos datos ni se envían mensajes. Una misma información encontrada en varias fuentes puede tener varios registros de evidencia.</p></div>
</section>
<?php else:?>
<div class="crm-metrics" aria-label="Resumen de negocios"><a href="<?=esc(crm_link($filterState,['contact'=>'','crm_page'=>1]))?>" <?=($crmFilters['contact']==='')?'aria-current="true"':''?>><span>Negocios del grupo</span><strong><?=esc($overview['summary_total']??0)?></strong></a><a href="<?=esc(crm_link($filterState,['contact'=>'with','crm_page'=>1]))?>" <?=$crmFilters['contact']==='with'?'aria-current="true"':''?>><span>Con canales</span><strong><?=esc($overview['summary_with_contact']??0)?></strong></a><a href="<?=esc(crm_link($filterState,['contact'=>'without','crm_page'=>1]))?>" <?=$crmFilters['contact']==='without'?'aria-current="true"':''?>><span>Sin canales</span><strong><?=esc($overview['summary_without_contact']??0)?></strong></a></div>
<section class="crm-panel" aria-labelledby="crm-search-title">
<div class="crm-section-heading"><div><h2 id="crm-search-title"><?=esc($accountScope==='without'?'Prospectos sin cuenta asociada':'Negocios con cuenta asociada')?></h2><p>Busca por nombre, correo o teléfono. También puedes ver cuáles siguen sin canales generales.</p></div><a class="crm-secondary-action" href="contactos-negocios.php?tab=cargar">Añadir canales desde un archivo</a></div>
<form method="get" class="crm-filter-bar">
<label class="crm-search-field">Negocio, persona, correo o teléfono<input type="search" name="q" value="<?=esc($query)?>" maxlength="200" placeholder="Ej.: nombre del hotel, persona o correo"></label>
<label>Grupo de contactos<select name="account"><option value="without" <?=$accountScope==='without'?'selected':''?>>Prospectos sin cuenta asociada</option><option value="with" <?=$accountScope==='with'?'selected':''?>>Negocios con cuenta asociada</option></select></label><label>Destino<select name="destination"><option value="">Todos los destinos</option><?php foreach($destinations as $destination):?><option value="<?=esc($destination['slug'])?>" <?=$crmFilters['destination']===$destination['slug']?'selected':''?>><?=esc($destination['display_name'])?></option><?php endforeach ?></select></label>
<label>Suscripción<select name="subscription"><?php foreach(['free'=>'Gratuita','paid'=>'Paga vigente','all'=>'Todas'] as $value=>$label):?><option value="<?=$value?>" <?=$crmFilters['subscription']===$value?'selected':''?>><?=$label?></option><?php endforeach ?></select></label>
<label>Canales generales<select name="contact"><?php foreach([''=>'Con y sin canales','with'=>'Con canales','without'=>'Sin canales'] as $value=>$label):?><option value="<?=$value?>" <?=$crmFilters['contact']===$value?'selected':''?>><?=$label?></option><?php endforeach ?></select></label>
<div class="crm-filter-actions"><button class="pill compact" type="submit">Buscar negocios</button><a href="contactos-negocios.php?subscription=all&amp;account=<?=esc($accountScope)?>">Limpiar filtros</a></div>
</form>
<p class="crm-help">La búsqueda solo consulta información guardada en el portal. Al abrir la pantalla se muestran prospectos Gratis sin cuenta asociada. Cambiar de grupo no mueve ni duplica contactos; tener una cuenta no habilita campañas automáticamente.</p>
<div class="crm-result-heading"><h3>Resultados</h3><p><?=esc($overview['total'])?> negocios · página <?=esc($overview['page'])?> de <?=esc($overview['page_count'])?></p></div>
<div class="destination-admin-table crm-table"><table data-crm-businesses><thead><tr><th>Negocio</th><th>Suscripción</th><th>Canales del negocio</th><th>Persona responsable</th><th>Seguimiento comercial</th><th><span class="crm-visually-hidden">Acciones</span></th></tr></thead><tbody>
<?php foreach($overview['rows'] as $business):$hasContact=(int)$business['research_contact_count']+(int)$business['profile_contact_count']>0;?>
<tr data-crm-business-id="<?=esc($business['id'])?>" data-public-id="<?=esc($business['public_id'])?>"><td><strong><?=esc($business['display_name'])?></strong><small><?=esc(implode(' · ',$business['category_labels']))?><?php if($crmFilters['destination']===''):?> · <?=esc($business['destination_name'])?><?php endif ?></small></td><td><?=esc($planLabels[$business['current_plan']]??$business['current_plan'])?><small><?=esc($subscriptionLabels[$business['subscription_status']]??'Por revisar')?></small></td><td><?php if($hasContact):?><span class="crm-status crm-status-ready">Con canales</span><small><?=esc($business['research_contact_count'])?> canales de investigación<?php if((int)$business['profile_contact_count']):?> · <?=esc($business['profile_contact_count'])?> en la ficha<?php endif ?></small><?php else:?><span class="crm-status">Sin canales</span><small>Pendiente de completar</small><?php endif ?></td><td><?=esc($business['responsible_name']?:'Sin cuenta asociada')?></td><td><?=esc($stageLabels[$business['crm_stage']]??($hasContact?'Pendiente de seguimiento':'Pendiente de investigar'))?><small>Suscripción posible: <?=esc($planLabels[$business['potential_subscription']]??'Por evaluar')?></small></td><td class="crm-row-actions"><a class="crm-open-channels" data-open-channels href="<?=esc(crm_link($filterState,['business_id'=>$business['id']]))?>" aria-haspopup="dialog">Ver canales</a><a href="correos.php?business_id=<?=esc($business['id'])?>">Preparar correo</a><a href="correos.php?tab=history&amp;business_id=<?=esc($business['id'])?>">Historial de correos</a><a href="negocios.php?id=<?=esc($business['id'])?>">Editar ficha</a></td></tr>
<?php endforeach ?>
<?php if(!$overview['rows']):?><tr><td colspan="6"><div class="crm-empty"><strong>No encontramos negocios con estos filtros</strong><p>Prueba otro nombre o selecciona todas las suscripciones.</p><a href="contactos-negocios.php?subscription=all&amp;account=<?=esc($accountScope)?>">Ver todos los negocios</a></div></td></tr><?php endif ?>
</tbody></table></div>
<?php if($overview['page_count']>1):?><nav class="admin-pagination" aria-label="Páginas de negocios"><?php for($n=1;$n<=$overview['page_count'];$n++):?><a href="<?=esc(crm_link($filterState,['crm_page'=>$n]))?>" <?=$overview['page']===$n?'aria-current="page"':''?> aria-label="Página <?=$n?>"><?=$n?></a><?php endfor ?></nav><?php endif ?>
</section>
<?php if($selectedBusiness):?>
<section class="crm-panel" id="contactos-del-negocio" data-business-id="<?=esc($businessId)?>" aria-labelledby="crm-detail-title"><div class="crm-section-heading"><div><p class="crm-eyebrow">Detalle del negocio</p><h2 id="crm-detail-title"><?=esc($selectedBusiness['display_name'])?></h2><p>Canales generales del establecimiento y, cuando existe una cuenta asociada, los canales de su persona responsable.</p></div><a class="crm-secondary-action" data-close-channels href="<?=esc(crm_link($filterState))?>">Cerrar detalle</a></div>
<?php if($profileContacts):?><h3>Canales generales guardados en la ficha</h3><ul class="crm-profile-contacts"><?php foreach($profileContacts as $contact):?><li><strong><?=esc($labels[$contact['channel']]??$contact['channel'])?></strong><span><?=esc($contact['contact_value'])?></span><small><?=!empty($contact['is_public'])?'Visibilidad en el portal según suscripción':'Uso privado de administración'?></small></li><?php endforeach ?></ul><?php endif ?>
<?php if($accountResult):?><p class="crm-feedback crm-feedback-success" role="status"><?=esc($accountResult)?></p><?php endif ?><?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><h3>Canales del negocio</h3><p class="crm-help"><?=esc($total)?> referencias de origen. Cada canal se muestra una sola vez, con sus fuentes agrupadas.</p>
<?php $channelGroups=[];foreach($rows as $channelRow){$key=$channelRow['channel']."\0".$channelRow['contact_value'];$channelGroups[$key][]=$channelRow;} ?>
<div class="crm-channel-cards">
<?php foreach($channelGroups as $observations):$row=$observations[0];foreach($observations as $observation)if(!empty($observation['recipient_label'])){$row=$observation;break;}$emailDecision=$row['channel']==='email'?($emailPermissions[strtolower(trim($row['contact_value']))]??'needs_review'):'';?>
<article class="crm-channel-card"><div class="crm-channel-card-heading"><strong><?=esc($labels[$row['channel']]??$row['channel'])?></strong><button type="button" class="crm-copy-channel" data-copy-channel="<?=esc($row['contact_value'])?>" aria-label="Copiar <?=esc($labels[$row['channel']]??'canal')?>">Copiar</button></div><p class="crm-channel-card-value"><?=esc($row['contact_value'])?></p>
<?php if(!empty($row['recipient_label'])):?><p>A la atención de <?=esc($row['recipient_label'])?></p><?php endif ?>
<p class="crm-channel-usage"><?=!empty($row['suppressed'])||$emailDecision==='suppressed'?'Excluido de comunicaciones':($emailDecision==='allowed'?'Correo habilitado para campañas':($row['channel']==='email'?'Correo pendiente de revisión':'Canal del negocio'))?><?php if($row['channel']==='email'):?> · <a href="campanas.php?tab=correos">Revisar para campañas</a><?php endif ?></p>
<details class="crm-channel-sources"><summary>Fuentes y opciones (<?=count($observations)?>)</summary><?php foreach($observations as $source):?><section class="crm-channel-source"><p><a href="<?=esc($source['source_url'])?>" target="_blank" rel="noopener noreferrer">Ver fuente ↗</a> · <?=esc($sourceLabels[$source['source_type']]??'Fuente de investigación')?></p><p>Consultada el <?=esc($source['consulted_at'])?> · <?=esc($evidenceLabels[$source['evidence_status']]??'Pendiente de verificación')?></p><?php if($source['source_period']):?><p><?=esc($source['source_period'])?></p><?php endif ?><?php if($source['source_note']):?><p><?=esc($source['source_note'])?></p><?php endif ?>
<?php if($recipientReady):?><form method="post" class="crm-recipient-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save_channel_recipient"><input type="hidden" name="business_id" value="<?=esc($businessId)?>"><input type="hidden" name="channel_id" value="<?=esc($source['id'])?>"><label>A la atención de<input name="recipient_label" maxlength="180" value="<?=esc($source['recipient_label']??'')?>" placeholder="Ej.: Administración o nombre conocido"></label><button class="pill compact" type="submit">Guardar destinatario</button></form><?php endif ?>
<details><summary>Información de uso registrada</summary><p><?=esc($authorizationLabels[$source['authorization_status']]??'Revisar autorización registrada')?></p><p><?=esc($source['legal_basis_status'])?></p></details></section><?php endforeach ?></details></article><?php endforeach ?>
</div>
<?php if(!$rows):?><div class="crm-empty"><strong>Este negocio todavía no tiene canales de investigación</strong><p>Carga un archivo que incluya sus canales y fuentes para completar esta sección.</p><a href="contactos-negocios.php?tab=cargar">Cargar archivo de canales</a></div><?php endif ?>
<?php if($pageCount>1):?><nav class="admin-pagination" aria-label="Páginas de fuentes de canales"><?php for($n=1;$n<=$pageCount;$n++):?><a href="<?=esc(crm_link($filterState,['business_id'=>$businessId,'page'=>$n]))?>#contactos-del-negocio" <?=$page===$n?'aria-current="page"':''?>><?=$n?></a><?php endfor ?></nav><?php endif ?>
<?php require __DIR__.'/partials/business-responsible-account.php'; ?>
</section>
<?php endif ?>
<p><a class="pill compact" href="campanas.php?tab=correos">Preparar campañas y revisar destinatarios</a></p><div class="crm-footer-note"><p>Son los mismos negocios del directorio. Los canales generales y los datos de la cuenta responsable se gestionan por separado; la información de investigación permanece privada.</p><a href="negocios.php?tab=revisar">Revisar y publicar fichas importadas →</a></div>
<?php endif ?>
</main></body></html>
