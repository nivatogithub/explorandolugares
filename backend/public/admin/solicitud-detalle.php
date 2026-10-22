<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/LeadBusinessLinks.php';
require_once dirname(__DIR__,2).'/app/AdminReturnContext.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el portal.');}
$_SESSION['last_seen']=time();
$typeValue=$_GET['type']??$_GET['tipo']??'';$type=is_string($typeValue)?$typeValue:'';if($type==='participation')$type='lead';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1||!in_array($type,['lead','account'],true)){http_response_code(404);exit('Solicitud no encontrada.');}
$table=$type==='lead'?'leads':'business_account_applications';
$q=db()->prepare('SELECT * FROM '.$table.' WHERE id=?');$q->execute([$id]);$request=$q->fetch(PDO::FETCH_ASSOC);
if(!$request){http_response_code(404);exit('Solicitud no encontrada.');}
$return=$type==='lead'?'index.php':'solicitudes-cuenta.php';
if($type==='lead')$return.='?'.http_build_query(['q'=>is_string($_GET['q']??null)?$_GET['q']:'','status'=>is_string($_GET['status']??null)?$_GET['status']:'','page'=>max(1,(int)($_GET['page']??1))]);
$return=AdminReturnContext::safe($_GET['return']??null,$return);
$error='';$notice='';$links=[];$matches=[];$account=null;$evidence=[];$photos=[];$memberships=[];
$search=is_string($_GET['search']??null)?trim($_GET['search']):'';
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if($type!=='lead'||($_POST['action']??'')!=='link'||($_POST['confirm']??'')!=='1')throw new RuntimeException('Confirma la relación informativa con la ficha seleccionada.');
        $business=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);
        $created=(new LeadBusinessLinks(db()))->link($id,(int)$business,(!empty($_SESSION['admin_account_id'])?'account:'.(int)$_SESSION['admin_account_id']:(string)($_SESSION['admin_user']??$config['admin_user']??'legacy-admin')));
        $_SESSION['lead_link_notice']=$created?'Relación guardada. No se asignaron permisos ni se publicó el negocio.':'La relación ya estaba registrada; se conservó sin duplicarla.';
        header('Location: solicitud-detalle.php?'.http_build_query(['return'=>$return,'type'=>$type,'id'=>$id,'q'=>$_GET['q']??'','status'=>$_GET['status']??'','page'=>$_GET['page']??1]),true,303);exit;
    }
    if($type==='lead'){
        $links=(new LeadBusinessLinks(db()))->forLead($id);
        if($search!==''){
            $q=db()->prepare('SELECT b.id,t.display_name,d.display_name AS place FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations d ON d.destination_id=b.destination_id AND d.locale="es" WHERE t.display_name LIKE ? ORDER BY t.display_name,b.id LIMIT 30');$q->execute(['%'.$search.'%']);$matches=$q->fetchAll(PDO::FETCH_ASSOC);
        }
    }else{
        $q=db()->prepare('SELECT id,display_name AS name,email,status FROM accounts WHERE id=?');$q->execute([$request['account_id']]);$account=$q->fetch(PDO::FETCH_ASSOC);
        $q=db()->prepare('SELECT id,original_name,verified_at FROM business_account_application_evidence WHERE application_id=? ORDER BY id');$q->execute([$id]);$evidence=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=db()->prepare('SELECT id,original_name FROM business_application_images WHERE application_id=?');$q->execute([$id]);$photos=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($request['business_id']))$links=[['business_id'=>$request['business_id']]];
        if(!empty($request['business_id'])){$q=db()->prepare('SELECT status,role,created_at FROM business_account_memberships WHERE business_id=? AND account_id=?');$q->execute([$request['business_id'],$request['account_id']]);$memberships=$q->fetchAll(PDO::FETCH_ASSOC);}
    }
}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'solicitud_detalle');$error=$e instanceof PDOException?'No se pudo cargar la relación. Revisa las migraciones del panel.':$e->getMessage();http_response_code(400);}
$notice=(string)($_SESSION['lead_link_notice']??'');unset($_SESSION['lead_link_notice']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Revisar solicitud | Explorando Lugares</title><?php require __DIR__.'/_theme.php'; ?></head><body class="portal-admin"><main class="wrap admin-page"><h1>Revisar solicitud #<?=esc($id)?></h1><p><a href="<?=esc($return)?>">← Volver a la lista</a></p><?php require __DIR__.'/_navigation.php'; ?>
<?php if($error):?><p role="alert" class="notice"><?=esc($error)?></p><?php endif ?><?php if($notice):?><p role="status" class="notice"><?=esc($notice)?></p><?php endif ?>
<section class="card"><h2>Enviado por la persona</h2><dl><?php foreach(($type==='lead'?['created_at'=>'Fecha','business'=>'Negocio indicado','name'=>'Persona','email'=>'Correo','phone'=>'Teléfono','municipality'=>'Lugar indicado','category'=>'Categoría','interest'=>'Tipo de consulta','description'=>'Mensaje','consent_version'=>'Consentimiento']:['created_at'=>'Fecha','business_name'=>'Nombre solicitado','category_code'=>'Categoría solicitada','application_type'=>'Tipo de solicitud','message'=>'Mensaje','status'=>'Estado']) as $key=>$label):?><dt><?=esc($label)?></dt><dd><?=nl2br(esc((string)($request[$key]??'Dato histórico no disponible')))?></dd><?php endforeach ?></dl>
<?php if($type==='account'):?><h3>Información conservada de la solicitud</h3><?php require __DIR__.'/_request-snapshot.php'; ?><?php foreach($photos as $photo):?><p><a href="imagen-referencia-negocio.php?source=application&amp;id=<?=esc($photo['id'])?>" target="_blank" rel="noopener">Ver imagen enviada: <?=esc($photo['original_name'])?> ↗</a></p><?php endforeach ?><?php foreach($evidence as $document):?><p><a href="representacion-evidencia.php?id=<?=esc($document['id'])?>" target="_blank" rel="noopener"><?=esc($document['original_name'])?> ↗</a> · <?=empty($document['verified_at'])?'Sin revisión documental registrada':'Revisión registrada'?></p><?php endforeach ?><?php endif ?></section>
<section class="card"><h2>Ficha actual</h2><?php if(!$links):?><p>No hay una ficha vinculada a esta solicitud.</p><?php endif ?><?php foreach($links as $link):$q=db()->prepare('SELECT b.id,b.publication_status,b.information_status,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.id=?');$q->execute([$link['business_id']]);$business=$q->fetch(PDO::FETCH_ASSOC);if(!$business)continue;?><p><strong><?=esc($business['display_name'])?></strong> · #<?=esc($business['id'])?> · <?=esc($business['publication_status'])?> · <?=esc($business['information_status'])?> <a href="negocios.php?id=<?=esc($business['id'])?>" target="_blank" rel="noopener">Abrir negocio ↗</a><?php if(isset($link['created_at'])):?><br><small>Relación informativa registrada: <?=esc($link['created_at'])?> por <?=esc($link['linked_by'])?></small><?php endif ?></p><?php endforeach ?></section>
<section class="card"><h2>Validaciones</h2><p>Una relación informativa o una cuenta asociada no certifica titularidad. Revisar esta página no publica ni envía mensajes.</p><?php if($account):?><p>Cuenta #<?=esc($account['id'])?>: <?=esc($account['name'])?> · <?=esc($account['email'])?> · <?=esc($account['status'])?></p><?php endif ?><?php if($type==='account'):?><?php if($request['status']==='approved'&&!$memberships):?><p class="notice" role="alert">La solicitud figura aprobada, pero no hay una asociación de esta cuenta con la ficha. Revisar la inconsistencia; no se ha reparado automáticamente.</p><?php endif ?><?php foreach($memberships as $membership):?><p>Asociación: <?=esc($membership['status'])?> · <?=esc($membership['role'])?> · <?=esc($membership['created_at'])?></p><?php endforeach ?><p><a href="solicitudes-cuenta.php">Consultar y gestionar la asociación en Solicitudes de cuenta</a></p><?php endif ?></section>
<?php if($type==='lead'):?><section class="card"><h2>Responder a esta solicitud</h2><p><a href="responder-solicitud.php?lead=<?=esc($id)?>" target="_blank" rel="noopener">Preparar respuesta y ver historial ↗</a></p><h2>Relacionar con una ficha existente</h2><p>Busca el negocio, comprueba el lugar y confirma su ID. Esta acción conserva los vínculos anteriores y no asigna una cuenta.</p><form method="get" class="registration-form"><input type="hidden" name="type" value="lead"><input type="hidden" name="id" value="<?=esc($id)?>"><?php foreach(['q','status','page','return'] as $key):?><input type="hidden" name="<?=esc($key)?>" value="<?=esc((string)($_GET[$key]??''))?>"><?php endforeach ?><label>Nombre del negocio<input name="search" value="<?=esc($search)?>" required maxlength="190"></label><button class="pill">Buscar fichas</button></form><?php if($search!==''&&!$matches):?><p>No hay coincidencias.</p><?php endif ?><?php foreach($matches as $match):?><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="link"><input type="hidden" name="business_id" value="<?=esc($match['id'])?>"><p><?=esc($match['display_name'])?> · <?=esc($match['place'])?> · #<?=esc($match['id'])?></p><label><input type="checkbox" name="confirm" value="1" required> Confirmo que esta es la ficha relacionada con la consulta</label><button class="pill">Guardar relación con #<?=esc($match['id'])?></button></form><?php endforeach ?></section><?php endif ?></main></body></html>
