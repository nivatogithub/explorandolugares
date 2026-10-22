<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/LeadAdminService.php';
if (strlen($config['app_key']??'')<32 || (empty($config['unified_admin_only']) && (empty($config['admin_user']) || empty($config['admin_password_hash'])))) {http_response_code(503);exit('Administración pendiente de configuración.');}
$error='';
if(!empty($config['unified_admin_only'])&&empty($_SESSION['admin'])){header('Location: /negocios/acceso/',true,303);exit;}
try {
    if (isset($_SESSION['admin']) && time()-($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();$action=$_POST['action']??'';
        if ($action==='login') {
            if (!empty($config['unified_admin_only'])) { http_response_code(403);exit('Inicia sesión desde el portal.'); }
            if (!throttle('login',10,900)) {$error='Demasiados intentos. Intenta nuevamente más tarde.';http_response_code(429);}
            else {
                $user=is_string($_POST['username']??null)?$_POST['username']:'';
                $password=is_string($_POST['password']??null)?$_POST['password']:'';
                $valid=password_verify($password,$config['admin_password_hash']);
                if (hash_equals($config['admin_user'],$user) && $valid) {
                    session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
                    header('Location: index.php',true,303);exit;
                }
                $error='Usuario o contraseña incorrectos.';
            }
        } elseif (!empty($_SESSION['admin']) && $action==='logout') {
            $_SESSION=[];session_destroy();header('Location: index.php',true,303);exit;
        } elseif (!empty($_SESSION['admin']) && $action==='status') {
            $status=$_POST['status']??'';$id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);
            if ($id && $id>0 && in_array($status,['nuevo','revisado','contactado','archivado'],true)) {
                $result=(new LeadAdminService(db()))->changeStatus((int)$id,$status);
                $_SESSION['lead_status_notice']=match($result){'saved'=>'Estado guardado.','unchanged'=>'Sin cambios: la solicitud ya tenía ese estado.',default=>'No se guardó: la solicitud ya no existe.'};
                $returnStatus=is_string($_POST['return_status']??null)?$_POST['return_status']:'';$returnQuery=is_string($_POST['return_q']??null)?trim($_POST['return_q']):'';$returnPage=max(1,(int)($_POST['return_page']??1));
                if($result==='saved' && $returnStatus!=='' && $returnStatus!==$status) $_SESSION['lead_status_notice'].=' La solicitud dejó de aparecer porque ya no coincide con el filtro de estado.';
                header('Location: index.php?'.http_build_query(['status'=>$returnStatus,'q'=>$returnQuery,'page'=>$returnPage]),true,303);exit;
            }
            $error='Estado o registro inválido.';
        } elseif (!empty($_SESSION['admin']) && $action==='delete') {
            $ids=[];
            foreach ((array)($_POST['ids']??[]) as $value) {
                $id=filter_var($value,FILTER_VALIDATE_INT);
                if ($id && $id>0) $ids[$id]=$id;
            }
            if (!$ids) {$error='Selecciona al menos una solicitud para eliminar.';}
            else {
                $marks=implode(',',array_fill(0,count($ids),'?'));
                $q=db()->prepare('DELETE FROM leads WHERE id IN ('.$marks.')');$q->execute(array_values($ids));
                header('Location: index.php',true,303);exit;
            }
        } else {http_response_code(403);$error='Acción no autorizada.';}
    }
    $admin=!empty($_SESSION['admin']);$rows=[];$total=0;$system=[];$page=max(1,(int)($_GET['page']??1));
    $tab=is_string($_GET['tab']??null)?$_GET['tab']:'solicitudes';
    if (!in_array($tab,['solicitudes','sistema'],true)) $tab='solicitudes';
    $status=is_string($_GET['status']??null)?$_GET['status']:'';$query=is_string($_GET['q']??null)?trim($_GET['q']):'';
    if (!in_array($status,['nuevo','revisado','contactado','archivado'],true)) $status='';
    if ($admin && $tab==='solicitudes') {
        $_SESSION['last_seen']=time();
        $conditions=[];$params=[];if($status!==''){$conditions[]='status=?';$params[]=$status;}if($query!==''){$conditions[]='(business LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR municipality LIKE ?)';$needle='%'.$query.'%';array_push($params,$needle,$needle,$needle,$needle,$needle);}$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $q=db()->prepare('SELECT COUNT(*) FROM leads'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/25)));
        $offset=($page-1)*25;
        $q=db()->prepare('SELECT * FROM leads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$q->execute($params);$rows=$q->fetchAll();
    }
    if ($admin && $tab==='sistema') {
        $_SESSION['last_seen']=time();

    }
} catch (Throwable $e) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'index');error_log('Explorando Lugares: fallo de administración.');http_response_code(503);exit('El panel no está disponible en este momento.');}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Administración | Explorando Lugares</title><link rel="stylesheet" href="../assets/admin.css?v=system-health-20261016"><?php require __DIR__.'/_theme.php'; ?></head><body class="portal-admin"><main class="wrap admin-page"><a class="brand" href="../index.html"><img class="brand-logo" src="../assets/brand/explorando-lugares-logo-v2-transparent.png" alt="" width="64" height="64"><span>Explorando <b>Lugares</b></span></a><h1><?=($tab??'')==='sistema'?'Estado del sistema':'Solicitudes de participación'?></h1><?php if(($tab??'')!=='sistema'): ?><p>Personas que pidieron información o solicitaron participar con un negocio. Esto no es la lista de contactos comerciales que Explorando Lugares busca contactar.</p><?php endif ?>
<?php if($admin && isset($_SESSION['lead_status_notice'])): ?><p class="notice" role="status"><?=esc($_SESSION['lead_status_notice'])?></p><?php unset($_SESSION['lead_status_notice']); endif ?>
<?php if($error): ?><p role="alert"><?=esc($error)?></p><?php endif ?>
<?php if(!$admin): ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username" maxlength="120"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="pill">Entrar al panel</button></form>
<?php else: ?>

<?php require __DIR__.'/_navigation.php'; ?>
<?php if($tab==='sistema'): ?>
<?php require __DIR__.'/_system.php'; ?>
<?php else: ?>
<form method="get" class="admin-inbox-filter"><label>Buscar solicitud<input type="search" name="q" value="<?=esc($query)?>" placeholder="Negocio, persona, correo, teléfono o municipio"></label><label>Estado<select data-native-select name="status"><option value="">Todos</option><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$status?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light compact">Filtrar</button><a class="text-link" href="index.php">Limpiar</a></form>
<p><?=esc($total)?> solicitudes · Página <?=esc($page)?> · Fechas UTC</p>
<?php if(!$rows): ?><div class="notice">No hay solicitudes que coincidan con esos filtros.</div><?php else: ?><form id="bulk-delete" method="post" class="bulk-delete"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><button class="pill danger compact" disabled>Eliminar seleccionadas</button></form><div class="destination-admin-table admin-inbox-table"><table><thead><tr><th><input type="checkbox" data-select-all aria-label="Seleccionar todas las solicitudes de esta página"></th><th>Negocio y destino</th><th>Persona y contacto</th><th>Solicitud</th><th>Descripción</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><input form="bulk-delete" type="checkbox" name="ids[]" value="<?=esc($row['id'])?>" aria-label="Seleccionar solicitud de <?=esc($row['business'])?>"></td><td data-label="Negocio"><strong><?=esc($row['business'])?></strong><br><small><?=esc(categories(true)[$row['category']]??$row['category'])?> · <?=esc($row['municipality'])?></small></td><td data-label="Persona y contacto"><?=esc($row['name'])?><br><small><?=esc($row['email'])?><?php if($row['phone']!==''):?><br><?=esc($row['phone'])?> · <?=esc(['fijo'=>'Fijo','celular'=>'Celular','whatsapp'=>'WhatsApp'][$row['phone_type']]??'Tipo sin especificar')?><?php endif ?></small></td><td data-label="Solicitud"><?=esc($row['interest']==='impulsor_local'?'Impulsor local':'Información para negocios')?><br><small>Solicita contacto: <?=$row['wants_contact']?'Sí':'No'?></small></td><td data-label="Mensaje"><details><summary>Ver mensaje</summary><p><?=esc($row['description']?:'Sin descripción')?></p><small>Alcance: <?=esc($row['participation_version']?:'Sin confirmación')?> · Consentimiento: <?=esc($row['consent_version'])?></small></details><a href="solicitud-detalle.php?<?=esc(http_build_query(['type'=>'lead','id'=>$row['id'],'q'=>$query,'status'=>$status,'page'=>$page]))?>" target="_blank" rel="noopener">Revisar solicitud ↗</a><br><a href="responder-solicitud.php?lead=<?=esc($row['id'])?>" target="_blank" rel="noopener">Ver historial de respuestas ↗</a></td><td data-label="Fecha"><?=esc($row['created_at'])?></td><td data-label="Estado"><form method="post" class="admin-status-form admin-status-form--row"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=esc($row['id'])?>"><input type="hidden" name="return_status" value="<?=esc($status)?>"><input type="hidden" name="return_q" value="<?=esc($query)?>"><input type="hidden" name="return_page" value="<?=esc($page)?>"><select data-native-select name="status" aria-label="Estado de solicitud de <?=esc($row['business'])?>"><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$row['status']?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select><button class="pill light compact">Guardar estado</button></form></td></tr><?php endforeach ?>
</tbody></table></div><script>(function(){var boxes=[...document.querySelectorAll('[form="bulk-delete"][type="checkbox"]')],all=document.querySelector('[data-select-all]'),button=document.querySelector('#bulk-delete button');function update(){button.disabled=!boxes.some(function(box){return box.checked});if(all)all.checked=boxes.length>0&&boxes.every(function(box){return box.checked})}boxes.forEach(function(box){box.addEventListener('change',update)});if(all)all.addEventListener('change',function(){boxes.forEach(function(box){box.checked=all.checked});update()});document.getElementById('bulk-delete').addEventListener('submit',function(event){if(!confirm('¿Eliminar definitivamente las solicitudes seleccionadas?'))event.preventDefault()})})();</script><?php endif ?>
<div class="pagination"><?php if($page>1): ?><a href="?page=<?=$page-1?>&amp;status=<?=rawurlencode($status)?>&amp;q=<?=rawurlencode($query)?>">← Anterior</a><?php endif ?> <?php if($page*25<$total): ?><a href="?page=<?=$page+1?>&amp;status=<?=rawurlencode($status)?>&amp;q=<?=rawurlencode($query)?>">Siguiente →</a><?php endif ?></div>
<?php endif ?><script src="<?=esc(admin_asset('select-flotante.js'))?>" defer></script>
<?php endif ?></main></body></html>
