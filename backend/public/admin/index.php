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
        } else {http_response_code(403);$error='Acción no autorizada.';}
    }
    $admin=!empty($_SESSION['admin']);$rows=[];$total=0;$page=max(1,(int)($_GET['page']??1));
    $status=is_string($_GET['status']??null)?$_GET['status']:'';
    if (!in_array($status,['nuevo','revisado','contactado','archivado'],true)) $status='';
    if ($admin) {
        $_SESSION['last_seen']=time();
        $where=$status!==''?' WHERE status=?':'';$params=$status!==''?[$status]:[];
        $q=db()->prepare('SELECT COUNT(*) FROM leads'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $offset=($page-1)*25;
        $q=db()->prepare('SELECT * FROM leads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$q->execute($params);$rows=$q->fetchAll();
    }
} catch (Throwable $e) {error_log('Explorando Lugares: fallo de administración.');http_response_code(503);exit('El panel no está disponible en este momento.');}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Administración | Explorando Lugares</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap admin-page"><a class="brand" href="../index.html"><img class="brand-logo" src="../assets/logo.svg" alt="" width="64" height="64"><span>explorando<b>lugares</b></span></a><h1>Contactos de negocios</h1><p>Área privada. Un registro no es una publicación ni un aliado aprobado.</p>
<?php if($error): ?><p role="alert"><?=esc($error)?></p><?php endif ?>
<?php if(!$admin): ?>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username" maxlength="120"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="pill">Entrar al panel</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="pill light">Cerrar sesión</button></form>
<form method="get" class="admin-filter"><label>Filtrar por estado<select name="status"><option value="">Todos</option><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$status?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Filtrar</button></form>
<p><?=esc($total)?> solicitudes · Página <?=esc($page)?> · Fechas UTC</p>
<?php if(!$rows): ?><div class="notice">No hay solicitudes para mostrar.</div><?php endif ?>
<?php foreach($rows as $row): ?><article class="admin-lead"><span class="badge"><?=esc($row['status'])?></span><h2><?=esc($row['business'])?></h2><p><?=esc(categories(true)[$row['category']]??$row['category'])?> · <?=esc($row['municipality'])?></p><dl><dt>Persona</dt><dd><?=esc($row['name'])?></dd><dt>Contacto</dt><dd><?=esc($row['email'])?> <?=esc($row['phone'])?><?php if ($row['phone']!==''): ?> · <?=esc(['fijo'=>'Fijo','celular'=>'Celular','whatsapp'=>'WhatsApp'][$row['phone_type']]??'Tipo sin especificar')?><?php endif ?></dd><dt>Solicita contacto</dt><dd><?=$row['wants_contact']?'Sí':'No'?></dd><dt>Descripción</dt><dd class="lead-description"><?=esc($row['description'])?></dd><dt>Conoce el alcance</dt><dd><?=esc($row['participation_version']?:'Sin confirmación registrada')?></dd><dt>Registro</dt><dd><?=esc($row['created_at'])?> · Consentimiento <?=esc($row['consent_version'])?></dd></dl><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=esc($row['id'])?>"><label>Seguimiento<select name="status"><?php foreach(['nuevo','revisado','contactado','archivado'] as $value): ?><option value="<?=$value?>" <?=$value===$row['status']?'selected':''?>><?=esc(ucfirst($value))?></option><?php endforeach ?></select></label><button class="pill light">Guardar estado</button></form></article><?php endforeach ?>
<div class="pagination"><?php if($page>1): ?><a href="?page=<?=$page-1?>&amp;status=<?=esc($status)?>">← Anterior</a><?php endif ?> <?php if($page*25<$total): ?><a href="?page=<?=$page+1?>&amp;status=<?=esc($status)?>">Siguiente →</a><?php endif ?></div>
<?php endif ?></main></body></html>
