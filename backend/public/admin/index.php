<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if (empty($config['admin_user']) || empty($config['admin_password_hash']) || strlen($config['app_key']??'')<32) {http_response_code(503);exit('Administración pendiente de configuración.');}
$error='';
try {
    if (isset($_SESSION['admin']) && time()-($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();$action=$_POST['action']??'';
        if ($action==='login') {
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
            if ($id && in_array($status,['nuevo','revisado','contactado','archivado'],true)) {
                $q=db()->prepare('UPDATE leads SET status=? WHERE id=?');$q->execute([$status,$id]);
                header('Location: index.php',true,303);exit;
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
    $status=is_string($_GET['status']??null)?$_GET['status']:'';
    if (!in_array($status,['nuevo','revisado','contactado','archivado'],true)) $status='';
    if ($admin && $tab==='solicitudes') {
        $_SESSION['last_seen']=time();
        $where=$status!==''?' WHERE status=?':'';$params=$status!==''?[$status]:[];
        $q=db()->prepare('SELECT COUNT(*) FROM leads'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $offset=($page-1)*25;
        $q=db()->prepare('SELECT * FROM leads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$q->execute($params);$rows=$q->fetchAll();
    }
    if ($admin && $tab==='sistema') {
        $pdo=db();
        $driver=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        // Usar solo SELECT: el usuario de producción no requiere permisos SHOW.
        $pdo->query('SELECT 1 FROM leads LIMIT 1');
        $pdo->query('SELECT 1 FROM rate_limits LIMIT 1');
        $system=[
            'database'=>'Conectada',
            'driver'=>$driver==='mysql'?'MySQL/MariaDB':'Controlador no esperado',
            'leads'=>'Tabla accesible',
            'limits'=>'Tabla accesible',
            'privacy'=>!empty($config['privacy']['operator']) && !empty($config['privacy']['contact']) && !empty($config['privacy']['retention']) && !empty($config['privacy']['retention_en'])?'Completa':'Pendiente',
            'registration'=>ready()?'Habilitada':'Deshabilitada',
            'server'=>substr((string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),0,80),
        ];
    }
} catch (Throwable $e) {error_log('Explorando Lugares: fallo de administración.');http_response_code(503);exit('El panel no está disponible en este momento.');}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Administración | Explorando Lugares</title><link rel="stylesheet" href="../assets/style.css?v=admin-compact-20261013"></head><body><main class="wrap admin-page"><a class="brand" href="../index.html"><img class="brand-logo" src="../assets/logo.svg" alt="" width="64" height="64"><span>explorando<b>lugares</b></span></a><h1>Contactos de negocios</h1><p>Área privada. Un registro no es una publicación ni un aliado aprobado.</p>
<?php if($error): ?><p role="alert"><?=esc($error)?></p><?php endif ?>
<?php if(!$admin): ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username" maxlength="120"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="pill">Entrar al panel</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="pill light">Cerrar sesión</button></form>
<nav class="admin-tabs" aria-label="Administración"><a href="?tab=solicitudes" <?=$tab==='solicitudes'?'aria-current="page"':''?>>Solicitudes</a><a href="?tab=sistema" <?=$tab==='sistema'?'aria-current="page"':''?>>Sistema y base de datos</a></nav>
<?php if($tab==='sistema'): ?>
<section class="admin-system"><p class="eyebrow">Diagnóstico seguro</p><h2>Sistema y base de datos</h2><p>Este resumen confirma el estado de la conexión y las tablas necesarias. No muestra contraseñas, usuarios, cadenas de conexión ni datos de los contactos.</p><dl><dt>Conexión</dt><dd><?=esc($system['database'])?></dd><dt>Motor</dt><dd><?=esc($system['driver'])?></dd><dt>Solicitudes (<code>leads</code>)</dt><dd><?=esc($system['leads'])?></dd><dt>Protección contra abuso (<code>rate_limits</code>)</dt><dd><?=esc($system['limits'])?></dd><dt>Información de privacidad</dt><dd><?=esc($system['privacy'])?></dd><dt>Recepción del formulario</dt><dd><?=esc($system['registration'])?></dd></dl><div class="notice"><strong>Configuración privada</strong><p>La contraseña MySQL y el usuario administrador se configuran únicamente en <code>private/config.php</code>, fuera de la web. Para activar el formulario se completa ese archivo y se vuelve a comprobar aquí. No ejecutes SQL arbitrario desde el navegador.</p></div></section>
<?php else: ?>
<form method="get" class="admin-filter"><label>Filtrar por estado<select name="status"><option value="">Todos</option><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$status?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Filtrar</button></form>
<p><?=esc($total)?> solicitudes · Página <?=esc($page)?> · Fechas UTC</p>
<?php if(!$rows): ?><div class="notice">No hay solicitudes para mostrar.</div><?php endif ?>
<?php foreach($rows as $row): ?><article class="admin-lead"><div class="admin-lead-head"><label class="admin-select"><input form="bulk-delete" type="checkbox" name="ids[]" value="<?=esc($row['id'])?>"> <span>Seleccionar</span></label><div class="admin-lead-title"><h2><?=esc($row['business'])?></h2><p><?=esc(categories(true)[$row['category']]??$row['category'])?> · <?=esc($row['municipality'])?></p></div><div class="admin-lead-status"><span class="badge"><?=esc($row['status'])?></span><form method="post" class="admin-status-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=esc($row['id'])?>"><label><span>Estado</span><select name="status"><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$row['status']?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Guardar</button></form></div></div><dl class="admin-lead-details"><div><dt>Persona</dt><dd><?=esc($row['name'])?></dd></div><div class="admin-contact"><dt>Contacto</dt><dd><?=esc($row['email'])?> <?=esc($row['phone'])?><?php if ($row['phone']!==''): ?> · <?=esc(['fijo'=>'Fijo','celular'=>'Celular','whatsapp'=>'WhatsApp'][$row['phone_type']]??'Tipo sin especificar')?><?php endif ?></dd></div><div><dt>Tipo de solicitud</dt><dd><?=esc($row['interest']==='impulsor_local'?'Postulación de impulsor local':'Registro de negocio')?></dd></div><div><dt>Solicita contacto</dt><dd><?=$row['wants_contact']?'Sí':'No'?></dd></div><div class="admin-description"><dt>Descripción</dt><dd class="lead-description"><?=esc($row['description'])?></dd></div><div><dt>Conoce el alcance</dt><dd><?=esc($row['participation_version']?:'Sin confirmación registrada')?></dd></div><div><dt>Registro</dt><dd><?=esc($row['created_at'])?> · Consentimiento <?=esc($row['consent_version'])?></dd></div></dl><div class="admin-lead-actions"><form method="post" class="admin-delete"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="ids[]" value="<?=esc($row['id'])?>"><button class="pill danger" onclick="return confirm('¿Eliminar esta solicitud de forma definitiva?')">Eliminar</button></form></div></article><?php endforeach ?>
<?php if($rows): ?><form id="bulk-delete" method="post" class="bulk-delete"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><button class="pill danger" disabled>Eliminar seleccionadas</button></form><script>document.getElementById('bulk-delete').addEventListener('submit',function(event){if(!confirm('¿Eliminar definitivamente las solicitudes seleccionadas?'))event.preventDefault()});document.querySelectorAll('[form="bulk-delete"][type="checkbox"]').forEach(function(item){item.addEventListener('change',function(){document.querySelector('#bulk-delete button').disabled=!document.querySelector('[form="bulk-delete"][type="checkbox"]:checked')})})</script><?php endif ?>
<div class="pagination"><?php if($page>1): ?><a href="?page=<?=$page-1?>&amp;status=<?=esc($status)?>">← Anterior</a><?php endif ?> <?php if($page*25<$total): ?><a href="?page=<?=$page+1?>&amp;status=<?=esc($status)?>">Siguiente →</a><?php endif ?></div>
<?php endif ?>
<?php endif ?></main></body></html>
