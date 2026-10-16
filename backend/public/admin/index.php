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
        } elseif (!empty($_SESSION['admin']) && $action==='business_key') {
            $operation=$_POST['operation']??'';
            if (!in_array($operation,['generate','revoke'],true)) {
                $error='Acción de llave no válida.';
            } else {
                $nextKey=$operation==='generate'?bin2hex(random_bytes(32)):'';
                try {
                    BusinessPortalSettings::save($configFile,false,$nextKey);
                    $config['business_accounts_enabled']=false;
                    $config['business_accounts_test_key']=$nextKey;
                    if ($nextKey!=='') $_SESSION['admin_generated_business_test_key']=$nextKey;
                    else unset($_SESSION['admin_generated_business_test_key']);
                    header('Location: index.php?tab=sistema&access_notice='.($nextKey!==''?'key-generated':'key-revoked'),true,303);exit;
                } catch (Throwable $e) {
                    error_log('Explorando Lugares: no se pudo guardar la llave privada empresarial.');
                    $error='No se pudo guardar la llave en la configuración privada. Comprueba permisos del archivo.';
                }
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
        $_SESSION['last_seen']=time();
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
            'business_public'=>!empty($config['business_accounts_enabled']),
            'business_test_key'=>is_string($config['business_accounts_test_key']??null)&&strlen($config['business_accounts_test_key'])>=32,
        ];
        $generatedBusinessKey=is_string($_SESSION['admin_generated_business_test_key']??null)?$_SESSION['admin_generated_business_test_key']:'';
        $generatedBusinessTestUrl=$generatedBusinessKey!==''?site_path('/negocios/registro/').'?prueba='.rawurlencode($generatedBusinessKey):'';
        unset($_SESSION['admin_generated_business_test_key']);
        $accessNotice=match($_GET['access_notice']??'') {
            'mode-saved'=>'Se guardó el estado del registro empresarial.',
            'key-generated'=>'Llave nueva guardada. Cópiala y guárdala ahora; no volverá a mostrarse.',
            'key-revoked'=>'Llave de prueba eliminada. Las sesiones iniciadas con la llave anterior quedan invalidadas.',
            default=>'',
        };
    }
} catch (Throwable $e) {error_log('Explorando Lugares: fallo de administración.');http_response_code(503);exit('El panel no está disponible en este momento.');}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Administración | Explorando Lugares</title><link rel="stylesheet" href="../assets/style.css?v=admin-testing-controls-20261015"><link rel="stylesheet" href="../assets/admin.css?v=admin-workflow-20261015-2"></head><body><main class="wrap admin-page"><a class="brand" href="../index.html"><img class="brand-logo" src="../assets/logo.svg" alt="" width="64" height="64"><span>explorando<b>lugares</b></span></a><h1>Contactos de negocios</h1><p>Área privada. Un registro no es una publicación ni un aliado aprobado.</p>
<?php if($error): ?><p role="alert"><?=esc($error)?></p><?php endif ?>
<?php if(!$admin): ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username" maxlength="120"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="pill">Entrar al panel</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="pill light">Cerrar sesión</button></form>
<?php require __DIR__.'/_navigation.php'; ?>
<?php if($tab==='sistema'): ?>
<?php if($accessNotice): ?><p class="notice" role="status"><?=esc($accessNotice)?></p><?php endif ?>
<section class="admin-system"><p class="eyebrow">Pruebas privadas</p><h2>Acceso de prueba al registro de negocios</h2><p>Genera una clave para probarlo durante cuatro horas desde tu navegador; al borrar la clave se deshabilita el acceso de prueba.</p><?php if(!empty($system['business_public'])): ?><p class="notice" role="alert"><strong>El registro está abierto al público.</strong> Al generar una clave de prueba se cerrará automáticamente y quedará privado.</p><?php else: ?><p class="notice">Acceso para visitantes: <strong>cerrado</strong>.</p><?php endif ?>
<fieldset class="business-access-keyset"><legend>Estado de las pruebas</legend><p>Pruebas: <strong><?=!empty($system['business_test_key'])?'habilitadas':'deshabilitadas'?></strong>. La clave guardada no se vuelve a mostrar; solo aparece al generarla.</p><?php if($generatedBusinessKey!==''): ?><label>Clave nueva · cópiala ahora<input id="generated-business-test-key" type="text" readonly value="<?=esc($generatedBusinessKey)?>" autocomplete="off" spellcheck="false"></label><button class="pill light compact" type="button" data-copy-business-key="generated-business-test-key">Copiar clave</button><p id="business-key-copy-status" role="status" aria-live="polite"></p><label>Enlace para abrir las pruebas<input id="generated-business-test-url" type="text" readonly value="<?=esc($generatedBusinessTestUrl)?>" autocomplete="off"></label><button class="pill light compact" type="button" data-copy-business-key="generated-business-test-url" data-copy-label="Enlace de prueba copiado.">Copiar enlace</button><?php endif ?>
<div class="admin-test-actions"><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="business_key"><input type="hidden" name="operation" value="generate"><button class="pill compact" type="submit"><?=!empty($system['business_public'])?'Cerrar acceso público y habilitar pruebas':(!empty($system['business_test_key'])?'Generar clave nueva':'Habilitar pruebas y generar clave')?></button></form><form method="post" onsubmit="return confirm('¿Borrar la clave y cerrar las sesiones de prueba?')"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="business_key"><input type="hidden" name="operation" value="revoke"><button class="pill light compact" type="submit" <?=empty($system['business_test_key'])?'disabled':''?>>Deshabilitar pruebas y borrar clave</button></form></div><p>El enlace incluye la clave y se muestra solo al generarla. Ábrelo una vez en el navegador donde probarás el flujo. Si borras o reemplazas la clave, el enlace anterior deja de funcionar.</p></fieldset></section>
<section class="admin-system"><p class="eyebrow">Diagnóstico seguro</p><h2>Sistema y base de datos</h2><p>Este resumen confirma el estado de la conexión y las tablas necesarias. No muestra contraseñas, usuarios, cadenas de conexión ni datos de los contactos.</p><dl><dt>Conexión</dt><dd><?=esc($system['database'])?></dd><dt>Motor</dt><dd><?=esc($system['driver'])?></dd><dt>Solicitudes (<code>leads</code>)</dt><dd><?=esc($system['leads'])?></dd><dt>Protección contra abuso (<code>rate_limits</code>)</dt><dd><?=esc($system['limits'])?></dd><dt>Información de privacidad</dt><dd><?=esc($system['privacy'])?></dd><dt>Recepción del formulario</dt><dd><?=esc($system['registration'])?></dd></dl><div class="notice"><strong>Configuración privada</strong><p>La contraseña MySQL y el usuario administrador permanecen fuera de la web. Este panel solo actualiza los dos controles del acceso empresarial.</p></div></section>
<?php else: ?>
<form method="get" class="admin-filter"><label>Filtrar por estado<select name="status"><option value="">Todos</option><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$status?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Filtrar</button></form>
<p><?=esc($total)?> solicitudes · Página <?=esc($page)?> · Fechas UTC</p>
<?php if(!$rows): ?><div class="notice">No hay solicitudes para mostrar.</div><?php endif ?>
<?php foreach($rows as $row): ?><article class="admin-lead"><div class="admin-lead-head"><label class="admin-select"><input form="bulk-delete" type="checkbox" name="ids[]" value="<?=esc($row['id'])?>"> <span>Seleccionar</span></label><div class="admin-lead-title"><h2><?=esc($row['business'])?></h2><p><?=esc(categories(true)[$row['category']]??$row['category'])?> · <?=esc($row['municipality'])?></p></div><div class="admin-lead-status"><span class="badge"><?=esc($row['status'])?></span><form method="post" class="admin-status-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=esc($row['id'])?>"><label><span>Estado</span><select name="status"><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$row['status']?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Guardar</button></form></div></div><dl class="admin-lead-details"><div><dt>Persona</dt><dd><?=esc($row['name'])?></dd></div><div class="admin-contact"><dt>Contacto</dt><dd><?=esc($row['email'])?> <?=esc($row['phone'])?><?php if ($row['phone']!==''): ?> · <?=esc(['fijo'=>'Fijo','celular'=>'Celular','whatsapp'=>'WhatsApp'][$row['phone_type']]??'Tipo sin especificar')?><?php endif ?></dd></div><div><dt>Tipo de solicitud</dt><dd><?=esc($row['interest']==='impulsor_local'?'Postulación de impulsor local':'Registro de negocio')?></dd></div><div><dt>Solicita contacto</dt><dd><?=$row['wants_contact']?'Sí':'No'?></dd></div><div class="admin-description"><dt>Descripción</dt><dd class="lead-description"><?=esc($row['description'])?></dd></div><div><dt>Conoce el alcance</dt><dd><?=esc($row['participation_version']?:'Sin confirmación registrada')?></dd></div><div><dt>Registro</dt><dd><?=esc($row['created_at'])?> · Consentimiento <?=esc($row['consent_version'])?></dd></div></dl><div class="admin-lead-actions"><form method="post" class="admin-delete"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="ids[]" value="<?=esc($row['id'])?>"><button class="pill danger" onclick="return confirm('¿Eliminar esta solicitud de forma definitiva?')">Eliminar</button></form></div></article><?php endforeach ?>
<?php if($rows): ?><form id="bulk-delete" method="post" class="bulk-delete"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><button class="pill danger" disabled>Eliminar seleccionadas</button></form><script>document.getElementById('bulk-delete').addEventListener('submit',function(event){if(!confirm('¿Eliminar definitivamente las solicitudes seleccionadas?'))event.preventDefault()});document.querySelectorAll('[form="bulk-delete"][type="checkbox"]').forEach(function(item){item.addEventListener('change',function(){document.querySelector('#bulk-delete button').disabled=!document.querySelector('[form="bulk-delete"][type="checkbox"]:checked')})})</script><?php endif ?>
<div class="pagination"><?php if($page>1): ?><a href="?page=<?=$page-1?>&amp;status=<?=esc($status)?>">← Anterior</a><?php endif ?> <?php if($page*25<$total): ?><a href="?page=<?=$page+1?>&amp;status=<?=esc($status)?>">Siguiente →</a><?php endif ?></div>
<?php endif ?><script src="../assets/select-flotante.js" defer></script><?php if($tab==='sistema'): ?><script src="../assets/admin-business-access.js" defer></script><?php endif ?>
<?php endif ?></main></body></html>
