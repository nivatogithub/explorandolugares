<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/app/bootstrap.php';
// session_start puede sustituir las cabeceras de caché del bootstrap.
header('Cache-Control: no-store');
require dirname(__DIR__,3).'/app/advance/AdvanceAdmin.php';
if(empty($config['admin_user'])||empty($config['admin_password_hash'])||strlen($config['app_key']??'')<32){http_response_code(503);exit('Administración pendiente de configuración.');}
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();
$service=new AdvanceAdmin(db(),$config['admin_user'],$config);$error='';
$section=is_string($_GET['section']??null)?$_GET['section']:'dashboard';
$sections=['dashboard'=>'Resumen CRM y campañas','contacts'=>'CRM y aliados','establishments'=>'Establecimientos','templates'=>'Plantillas HTML','campaigns'=>'Campañas'];
if(!isset($sections[$section])){http_response_code(404);exit('Sección inexistente.');}
$id=max(0,(int)($_GET['id']??0));$record=[];$rows=[];$deliveries=[];$stats=[];
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=$_POST['action']??'';
        if($action==='save'){$id=$service->save($section,$_POST);}
        elseif($action==='link_contact'&&$section==='establishments'){$id=$service->businesses()->linkContact($_POST);}
        elseif($action==='import'&&$section==='contacts'){$id=$service->importLead((int)($_POST['lead_id']??0));}
        elseif($action==='create'&&$section==='campaigns'){$id=$service->createCampaign($_POST);}
        elseif($action==='approve'&&$section==='campaigns'){
            if(($_POST['confirm']??'')!=='1')throw new InvalidArgumentException('Confirma la revisión del contenido y los destinatarios.');
            $id=(int)($_POST['id']??0);$service->approve($id,(int)($_POST['version']??0));
        }elseif($action==='send'&&$section==='campaigns'){
            if(($_POST['confirm_send']??'')!=='1')throw new InvalidArgumentException('Confirma el envío real de este correo.');
            if(!throttle('advance-send',2,1)) {http_response_code(429);exit('Espera antes de enviar el siguiente correo.');}
            $d=$service->find('deliveries',(int)($_POST['delivery_id']??0));$id=(int)$d['campaign_id'];
            $service->sendDelivery((int)$d['id'],new ResendClient($config['resend']??[]));
        }else throw new InvalidArgumentException('Acción inválida.');
        header('Location: index.php?section='.$section.'&id='.$id.'&saved=1',true,303);exit;
    }
}catch(Throwable $e){http_response_code($e instanceof PDOException?503:400);$error=$e instanceof PDOException?'No se pudo guardar. Revisa la migración y los datos relacionados.':$e->getMessage();}
try{
    if($section==='dashboard')$stats=$service->dashboard();
    else{
        $search=is_string($_GET['search']??null)?$_GET['search']:'';
        $rows=$service->rows($section,$search,max(1,(int)($_GET['page']??1)));
        if($id)$record=$service->find($section,$id);
        if($section==='campaigns'&&$id)$deliveries=$service->deliveries($id);
    }
}catch(Throwable $e){http_response_code(503);$error='Advance Admin no está disponible. Comprueba el esquema compartido y las migraciones 007 y 008.';}
function stateLabel(string $value): string {
    return ['prospect'=>'Prospecto','under_review'=>'En revisión','partner'=>'Aliado','archived'=>'Archivado',
        'draft'=>'Borrador','approved'=>'Aprobada','pending'=>'Pendiente','sending'=>'Enviando',
        'skipped'=>'Omitida','uncertain'=>'Resultado incierto','accepted'=>'Aceptada','published'=>'Publicado'][$value]??$value;
}
function token(): void {echo '<input type="hidden" name="csrf" value="'.esc($_SESSION['csrf']).'">';}
function inputField(string $key,string $label,array $record,bool $required=false,string $type='text'): void {
    echo '<label>'.esc($label).'<input type="'.esc($type).'" name="'.esc($key).'" value="'.esc($record[$key]??'').'" '.($required?'required':'').'></label>';
}
function areaField(string $key,string $label,array $record): void {echo '<label>'.esc($label).'<textarea name="'.esc($key).'" rows="5">'.esc($record[$key]??'').'</textarea></label>';}
function selectField(string $key,string $label,array $options,array $record): void {
    echo '<label>'.esc($label).'<select name="'.esc($key).'">';foreach($options as $value=>$text)echo '<option value="'.esc($value).'" '.((string)($record[$key]??array_key_first($options))===(string)$value?'selected':'').'>'.esc($text).'</option>';echo '</select></label>';
}
function preview(string $html): void {
    // Atributo escapado, origen opaco, sin scripts, formularios, navegación ni recursos remotos.
    $csp='<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;; img-src data:; form-action &#39;none&#39;; base-uri &#39;none&#39;">';
    echo '<iframe title="Vista previa del correo" sandbox="" referrerpolicy="no-referrer" srcdoc="'.esc($csp.$html).'"></iframe>';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Advance Admin | Explorando Lugares</title><link rel="stylesheet" href="style.css"></head><body><main>
<a href="../index.php">← Administración segura</a> · <a href="../reports.php">Reportes generales</a><h1>Advance Admin</h1><p>Explorando Lugares · Área privada</p><nav aria-label="Advance Admin"><?php foreach($sections as $key=>$label):?><a href="?section=<?=esc($key)?>" <?=$key===$section?'aria-current="page"':''?>><?=esc($label)?></a><?php endforeach ?></nav>
<?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif ?><?php if(isset($_GET['saved'])):?><p role="status">Operación guardada.</p><?php endif ?>
<?php if($section==='dashboard'):?><h2>Resumen de CRM y campañas</h2><p><a href="../reports.php">Abrir reportes generales del negocio →</a></p><div class="metrics"><?php foreach(['contacts'=>'Contactos','establishments'=>'Negocios del catálogo compartido','campaigns'=>'Campañas','deliveries'=>'Correos preparados'] as $key=>$label):?><article><strong><?=esc($stats[$key]??0)?></strong><p><?=esc($label)?></p></article><?php endforeach ?></div><h2>Contactos por municipio</h2><?php foreach($stats['municipalities']??[] as $item):?><p><?=esc($item['municipality'])?>: <?=esc($item['total'])?></p><?php endforeach ?><h2>Estado de correos</h2><?php foreach($stats['delivery_status']??[] as $item):?><p><?=esc(stateLabel($item['status']))?>: <?=esc($item['total'])?></p><?php endforeach ?><p>“Aceptada” significa aceptada por Resend; no acredita entrega, lectura ni ventas.</p>
<?php else:?>
<h2><?=esc($sections[$section])?></h2><form method="get" class="filter"><input type="hidden" name="section" value="<?=esc($section)?>"><label>Buscar por nombre<input name="search" value="<?=esc($_GET['search']??'')?>"></label><button>Buscar</button></form>
<table><thead><tr><th>ID</th><th>Nombre</th><th>Estado / relación</th><th></th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=esc($row['id'])?></td><td><?=esc($row['name'])?></td><td><?=esc(stateLabel($row['publication_status']??$row['status']??$row['relationship']??''))?></td><td><a href="?section=<?=esc($section)?>&amp;id=<?=esc($row['id'])?>">Abrir</a></td></tr><?php endforeach ?></tbody></table>
<?php $page=max(1,(int)($_GET['page']??1));?><p><?php if($page>1):?><a href="?section=<?=esc($section)?>&amp;page=<?=$page-1?>&amp;search=<?=esc(urlencode($search??''))?>">Anterior</a><?php endif ?> · Página <?=$page?> <?php if(count($rows)===25):?> · <a href="?section=<?=esc($section)?>&amp;page=<?=$page+1?>&amp;search=<?=esc(urlencode($search??''))?>">Siguiente</a><?php endif ?></p><p><a href="?section=<?=esc($section)?>">Crear nuevo</a></p>
<?php if($section==='establishments'&&$record):?>
<p>Negocio #<?=esc($record['id'])?> · ID público <?=esc($record['public_id'])?> · <?=esc($record['municipality'])?></p>
<p>Información: <?=esc($record['information_status'])?> · Reclamación: <?=esc($record['claim_status'])?> · Operación: <?=esc($record['operation_status'])?> · Publicación: <?=esc($record['publication_status'])?></p>
<h3>Canales del negocio</h3><?php foreach($record['channels'] as $channel):?><p><?=esc($channel['channel'])?>: <?=esc($channel['contact_value'])?> · <?=empty($channel['is_public'])?'Privado':'Público'?></p><?php endforeach ?>
<h3>Fuentes existentes</h3><?php foreach($record['sources'] as $source):?><p><?=esc($source['source_url'])?> · <?=esc($source['note'])?> · <?=esc($source['checked_at'])?></p><?php endforeach ?>
<form method="post"><?php token();?><input type="hidden" name="action" value="link_contact"><input type="hidden" name="id" value="<?=esc($record['id'])?>"><input type="hidden" name="revision" value="<?=esc($record['revision'])?>"><?php inputField('contact_id','ID de persona del CRM (vacío para desvincular)',$record,false,'number');?><button>Guardar vínculo CRM</button></form>
<?php endif ?>
<?php if($section!=='campaigns'&&($section!=='establishments'||($record['publication_status']??'draft')!=='published')):?>
<form method="post"><?php token();?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=esc($record['id']??0)?>"><input type="hidden" name="version" value="<?=esc($record['version']??0)?>"><input type="hidden" name="revision" value="<?=esc($record['revision']??'')?>">
<?php inputField('name','Nombre',$record,true);?>
<?php if($section==='contacts'):?>
<?php inputField('email','Correo',$record,false,'email');inputField('phone','Teléfono',$record);selectField('destination_id','Destino del catálogo (opcional)',[''=>'Sin destino vinculado']+array_column($service->businesses()->destinations(),'name','id'),$record);selectField('relationship','Relación',['prospect'=>'Prospecto','under_review'=>'En revisión','partner'=>'Aliado confirmado por una persona','archived'=>'Archivado'],$record);areaField('notes','Notas internas',$record);areaField('source','Procedencia y fecha',$record);?>
<label><input type="checkbox" name="marketing_consent" value="1" <?=!empty($record['marketing_consent'])?'checked':''?>> Autorización explícita de campañas</label><?php areaField('consent_evidence','Evidencia de autorización y fecha',$record);?><label><input type="checkbox" name="suppressed" value="1" <?=!empty($record['suppressed'])?'checked':''?>> Excluir este correo de campañas</label><p>El consentimiento de una solicitud no autoriza campañas automáticamente.</p>
<?php elseif($section==='establishments'):?>
<p>Se guarda en businesses y business_translations. No publica ni confirma una alianza. Busca el negocio existente antes de crear uno.</p><?php inputField('contact_id','ID del contacto CRM (opcional)',$record,false,'number');selectField('destination_id','Destino',array_column($service->businesses()->destinations(),'name','id'),$record);selectField('category_code','Categoría del catálogo',array_column($service->businesses()->categories(),'name','code')+(isset($record['category_code'])?[$record['category_code']=>$record['category_code']]:[]),$record);inputField('slug','Identificador URL único dentro del destino',$record,true);areaField('summary','Resumen español',$record);areaField('description','Descripción español',$record);inputField('source_url','Nueva fuente editorial (URL)',[],false,'url');areaField('source_note','Nota de la nueva fuente (máximo 500 bytes)',[]);selectField('publication_status','Estado',['draft'=>'Borrador','archived'=>'Archivado'],$record);?>
<?php else:?>
<?php inputField('subject','Asunto',$record,true);areaField('html','HTML del correo',$record);?><p>Variables: {{name}} y {{municipality}}. Incluye instrucciones claras para dejar de recibir correos y el contacto real del responsable antes de aprobar campañas.</p>
<?php endif ?><button>Guardar</button></form>
<?php if($section==='contacts'):?><h3>Vincular solicitud existente</h3><form method="post"><?php token();?><input type="hidden" name="action" value="import"><?php inputField('lead_id','ID de solicitud',[],true,'number');?><button>Crear contacto desde solicitud</button></form><?php endif ?>
<?php if($section==='templates'&&$record):?><h3>Vista previa aislada</h3><?php preview($record['html']);?><?php endif ?>
<?php elseif($section==='establishments'):?><p>Este negocio está publicado. Advance Admin permite vincularlo al CRM; su edición pública debe pasar por el flujo editorial.</p>
<?php else:?>
<?php if(!$record):?><h3>Preparar campaña</h3><form method="post"><?php token();?><input type="hidden" name="action" value="create"><?php inputField('name','Nombre interno',[],true);inputField('template_id','ID de plantilla',[],true,'number');inputField('contact_ids','IDs de contactos separados por coma (máximo 100)',[],true);?><button>Crear borrador para revisión</button></form>
<?php else:?><h3><?=esc($record['name'])?> · <?=esc(stateLabel($record['status']))?></h3><p>Versión <?=esc($record['version'])?> · Aprobación: <?=esc($record['approved_by']??'Pendiente')?> <?=esc($record['approved_at']??'')?> UTC</p><p>La campaña conserva una copia del contenido. Editar una plantilla no modifica esta campaña.</p>
<?php foreach($deliveries as $delivery):?><article><h4><?=esc($delivery['email'])?> · <?=esc(stateLabel($delivery['status']))?></h4><p><?=esc($delivery['subject'])?></p><?php preview($delivery['html']);?>
<?php if($record['status']==='approved'&&$delivery['status']==='pending'):?><form method="post"><?php token();?><input type="hidden" name="action" value="send"><input type="hidden" name="delivery_id" value="<?=esc($delivery['id'])?>"><label><input type="checkbox" name="confirm_send" value="1" required> Confirmo enviar este correo real</label><button <?=empty($config['resend']['enabled'])?'disabled':''?>>Enviar con Resend</button></form><?php endif ?></article><?php endforeach ?>
<?php if($record['status']==='draft'):?><form method="post"><?php token();?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=esc($record['id'])?>"><input type="hidden" name="version" value="<?=esc($record['version'])?>"><label><input type="checkbox" name="confirm" value="1" required> Revisé esta versión, cada destinatario, la autorización y las instrucciones de baja</label><button>Aprobar versión para envío manual</button></form><?php endif ?><p>Resend: <?=empty($config['resend']['enabled'])?'deshabilitado':'habilitado'?>. No hay envíos automáticos.</p>
<?php endif ?><?php endif ?><?php endif ?></main></body></html>
