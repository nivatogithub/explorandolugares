<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/BusinessBulkEditService.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();$error='';$result=null;$ready=false;$destinations=[];$categories=[];$service=null;
try{
    $service=new BusinessBulkEditService(db());
    $destinations=db()->query('SELECT d.slug,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    $categories=db()->query('SELECT c.code,t.label FROM service_categories c JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es" WHERE c.is_active=1 ORDER BY c.display_order')->fetchAll(PDO::FETCH_ASSOC);
    try{db()->query('SELECT id FROM business_bulk_update_audit LIMIT 0');$ready=true;}catch(PDOException $errorIgnored){}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=(string)($_POST['action']??'');
        if($action==='export'){
            $destination=trim((string)($_POST['destination']??''));$package=$service->export($destination,trim((string)($_POST['category']??'')),trim((string)($_POST['q']??'')));
            $json=json_encode($package,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
            if(strlen($json)>2*1024*1024)throw new RuntimeException('El archivo supera 2 MB. Exporta un grupo menor mediante los filtros.');
            header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="negocios-'.preg_replace('/[^a-z0-9-]/','',$destination).'-editar.PRIVADO.json"');echo $json;exit;
        }
        if($action==='preview'){
            unset($_SESSION['business_bulk_edit']);
            if(!$ready)throw new RuntimeException('Instala la estructura de actualización masiva (027) en Base de datos antes de preparar cambios.');
            $file=$_FILES['package']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??''))||(int)($file['size']??0)<2||(int)$file['size']>2*1024*1024)throw new RuntimeException('Selecciona el JSON editado, de hasta 2 MB.');
            $package=json_decode((string)file_get_contents($file['tmp_name']),true,128,JSON_THROW_ON_ERROR);
            if(!is_array($package))throw new RuntimeException('El archivo debe ser un objeto JSON.');
            $preview=$service->preview($package);
            $_SESSION['business_bulk_edit']=['package'=>$package,'preview'=>$preview,'token'=>bin2hex(random_bytes(32)),'created_at'=>time()];
        }elseif($action==='apply'){
            if(!$ready)throw new RuntimeException('Falta instalar la estructura de actualización masiva (027).');
            $pending=$_SESSION['business_bulk_edit']??null;
            if(!is_array($pending)||time()-(int)$pending['created_at']>1200||!is_string($_POST['preview_token']??null)||!hash_equals($pending['token'],$_POST['preview_token']))throw new RuntimeException('La revisión expiró o no corresponde a esta sesión. Carga el JSON otra vez.');
            if(($_POST['confirm']??'')!=='1')throw new RuntimeException('Confirma que revisaste los cambios antes de aplicarlos.');
            try{$result=$service->apply($pending['package'],(string)($config['admin_user']??'administrador'));}finally{unset($_SESSION['business_bulk_edit']);}
        }elseif($action!=='preview')throw new RuntimeException('Acción no válida.');
    }
}catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'actualizar_negocios');http_response_code(400);$error=$exception instanceof PDOException?'No se aplicó el lote. Revisa la estructura y los datos; las actualizaciones se revierten juntas ante un error.':($exception instanceof JsonException?'El archivo no contiene JSON válido.':$exception->getMessage());}
$pending=$_SESSION['business_bulk_edit']??null;if(is_array($pending)&&time()-(int)$pending['created_at']>1200){unset($_SESSION['business_bulk_edit']);$pending=null;}
function bulk_value(mixed $value):string{return is_array($value)?(string)json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):($value===null?'—':(string)$value);}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Exportar y actualizar negocios | Administración</title><link rel="stylesheet" href="../assets/style.css"><link rel="stylesheet" href="../assets/admin.css"><?php require __DIR__.'/_theme.php'; ?></head><body class="portal-admin"><main class="wrap admin-page destination-admin"><h1>Exportar y actualizar negocios</h1><?php require __DIR__.'/_navigation.php'; ?>
<p>Descarga los negocios, complementa el JSON y revisa los cambios antes de aplicarlos. Cada actualización conserva los IDs, las URL, la suscripción y el estado de publicación.</p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?>
<?php if(!$ready):?><p class="notice">Puedes exportar. Para aplicar actualizaciones, instala la estructura 027 desde <a href="base-datos.php">Base de datos</a>.</p><?php endif ?>
<?php if($result):?><p class="notice" role="status">Lote aplicado: <?=esc($result['updated'])?> negocios actualizados y <?=esc($result['unchanged'])?> sin cambios. No se crearon negocios ni se cambió su publicación. Referencia: <?=esc($result['batch_id'])?>.</p><?php endif ?>
<section class="destination-editor"><h2>1. Exportar negocios</h2><p>El archivo contiene información interna y contactos privados. Guárdalo en un lugar privado.</p><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="export"><div class="destination-form-grid"><label>Destino<select name="destination" required><option value="">Selecciona un destino</option><?php foreach($destinations as $destination):?><option value="<?=esc($destination['slug'])?>"><?=esc($destination['display_name'])?></option><?php endforeach ?></select></label><label>Categoría<select name="category"><option value="">Todas las categorías</option><?php foreach($categories as $category):?><option value="<?=esc($category['code'])?>"><?=esc($category['label'])?></option><?php endforeach ?></select></label><label>Nombre contiene<input type="search" name="q" maxlength="200"></label></div><button class="pill compact" type="submit">Descargar JSON para editar</button></form></section>
<section class="destination-editor"><h2>2. Complementar y revisar</h2><p>Conserva <code>id</code>, <code>public_id</code> y <code>version</code>. Edita únicamente los campos que quieras cambiar; puedes quitar del archivo los negocios que no vas a modificar. Los campos omitidos, vacíos o nulos conservan el valor actual. Quitar un contacto o una fuente del archivo no lo elimina de la base.</p><p>Los contactos y fuentes existentes conservan su propio ID. Para añadir uno nuevo, agrega un objeto sin ID. Los contactos nuevos quedan privados. <code>_context</code> y los permisos de publicación y campañas son informativos y no se pueden modificar aquí.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="preview"><label>JSON editado<input type="file" name="package" accept=".json,application/json" required <?=$ready?'':'disabled'?>></label><button class="pill compact" type="submit" <?=$ready?'':'disabled'?>>Revisar cambios del archivo</button></form></section>
<?php if(is_array($pending)):?><section class="destination-admin-list"><h2>3. Confirmar actualización</h2><p><?=esc($pending['preview']['businesses_changed'])?> negocios con cambios de <?=esc($pending['preview']['businesses_in_file'])?> incluidos en el archivo. Todavía no se guardó ninguna actualización.</p>
<?php foreach($pending['preview']['changes'] as $business):?><h3><?=esc($business['name'])?></h3><p><small><?=esc($business['public_id'])?> · <?=esc($business['publication_status'])?></small></p><?php if($business['publication_status']==='published'):?><p class="notice">Este negocio ya está publicado. Los cambios de nombre, categoría o información visible según su suscripción se reflejarán al aplicar el lote. Los contactos privados conservarán su privacidad.</p><?php endif ?><div class="destination-admin-table"><table><thead><tr><th>Campo</th><th>Valor actual</th><th>Valor propuesto</th></tr></thead><tbody><?php foreach($business['changes'] as $change):?><tr><td><?=esc($change['field'])?></td><td><?=esc(bulk_value($change['before']))?></td><td><?=esc(bulk_value($change['after']))?></td></tr><?php endforeach ?></tbody></table></div><?php endforeach ?>
<?php if($pending['preview']['businesses_changed']):?><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="preview_token" value="<?=esc($pending['token'])?>"><label><input type="checkbox" name="confirm" value="1" required> Revisé los valores propuestos y autorizo aplicar estos cambios.</label><button class="pill compact" type="submit">Aplicar actualización revisada</button></form><?php endif ?></section><?php endif ?></main></body></html>
