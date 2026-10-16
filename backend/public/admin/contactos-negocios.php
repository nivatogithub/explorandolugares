<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/BusinessResearchContactImporter.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();$error='';$result=null;$ready=false;$rows=[];$total=0;
try{db()->query('SELECT id FROM business_research_contacts LIMIT 1');$ready=true;}
catch(PDOException $exception){$error='Falta instalar la estructura de contactos privados (026). Puedes revisarla en Base de datos.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();if(!$ready)throw new RuntimeException($error);
        if(($_POST['action']??'')!=='import_private_contacts')throw new RuntimeException('Acción no válida.');
        $upload=$_FILES['package']??null;
        if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??''))||(int)($upload['size']??0)<2||(int)$upload['size']>2*1024*1024)throw new RuntimeException('Selecciona un JSON privado de hasta 2 MB.');
        $package=json_decode((string)file_get_contents($upload['tmp_name']),true,128,JSON_THROW_ON_ERROR);
        if(!is_array($package))throw new RuntimeException('El paquete debe ser un objeto JSON.');
        $result=BusinessResearchContactImporter::import(db(),$package);
    }catch(Throwable $exception){http_response_code(400);$error=$exception instanceof PDOException?'No se cargaron los contactos. Revisa la estructura de Base de datos.':($exception instanceof JsonException?'El archivo no contiene JSON válido.':$exception->getMessage());}
}
$query=trim((string)($_GET['q']??''));$businessId=max(0,(int)($_GET['business_id']??0));$page=max(1,(int)($_GET['page']??1));$pageSize=50;$pageCount=1;
if($ready){
    $where=' WHERE 1=1';$params=[];
    if($businessId){$where.=' AND b.id=?';$params[]=$businessId;}
    if($query!==''){$where.=' AND (t.display_name LIKE ? OR r.contact_value LIKE ?)';$params[]='%'.$query.'%';$params[]='%'.$query.'%';}
    $from=' FROM business_research_contacts r JOIN businesses b ON b.id=r.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id';
    $q=db()->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($params);$total=(int)$q->fetchColumn();$pageCount=max(1,(int)ceil($total/$pageSize));$page=min($page,$pageCount);
    $q=db()->prepare('SELECT r.*,b.public_id,t.display_name,d.slug AS destination_slug'.$from.$where.' ORDER BY t.display_name,r.channel,r.id LIMIT '.$pageSize.' OFFSET '.(($page-1)*$pageSize));$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
}
$labels=['email'=>'Correo','phone'=>'Celular / teléfono','phone_fixed'=>'Teléfono fijo','whatsapp'=>'WhatsApp','instagram'=>'Instagram','facebook'=>'Facebook','website'=>'Sitio web','linkedin'=>'LinkedIn'];
$evidenceLabels=['DECLARADO_RNT_SIN_CORROBORACION_CLIENTES'=>'Declarado en RNT; atención a clientes pendiente de corroborar','NUMERO_INCOMPLETO_O_FORMATO_ANTIGUO'=>'Número incompleto o de formato antiguo; revisar','PUBLICADO_PARA_CLIENTES'=>'Anunciado públicamente para atención a clientes'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Contactos privados | Administración</title><link rel="stylesheet" href="../assets/style.css"><link rel="stylesheet" href="../assets/admin.css"></head><body><main class="wrap admin-page destination-admin"><h1>Contactos privados de negocios</h1><?php require __DIR__.'/_navigation.php'; ?>
<p>Consulta los canales encontrados durante la investigación, asociados a cada negocio y acompañados de su procedencia. Se conservan privados aunque el negocio tenga suscripción Gratis o esté publicado.</p>
<p><a href="negocios.php?tab=revisar">Revisar y publicar negocios importados</a></p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?> <a href="base-datos.php">Revisar base de datos</a></p><?php endif ?>
<?php if($result):?><p class="notice" role="status">Contactos vinculados a <?=esc($result['businesses'])?> negocios: <?=esc($result['inserted'])?> observaciones añadidas y <?=esc($result['existing'])?> ya existentes. No se crearon negocios ni se modificó su publicación.</p><?php endif ?>
<section class="destination-editor"><h2>Cargar investigación de contactos</h2><p>Esta carga complementa los negocios que ya existen. Puedes repetirla sin duplicar las observaciones. No acredita autorizaciones comerciales ni envía mensajes.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import_private_contacts"><label>Archivo JSON de contactos privados<input type="file" name="package" accept=".json,application/json" required <?=$ready?'':'disabled'?>></label><button class="pill compact" type="submit" <?=$ready?'':'disabled'?>>Vincular contactos privados</button></form></section>
<section class="destination-admin-list"><h2>Contactos y fuentes <small>(<?=esc($total)?> observaciones)</small></h2><form method="get"><input type="hidden" name="business_id" value="<?=esc($businessId)?>"><label>Buscar negocio o contacto<input type="search" name="q" value="<?=esc($query)?>" maxlength="200"></label><button type="submit">Buscar</button></form>
<div class="destination-admin-table"><table><thead><tr><th>Negocio</th><th>Canal</th><th>Contacto</th><th>Procedencia y revisión</th><th>Uso comercial</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><a href="negocios.php?id=<?=esc($row['business_id'])?>"><?=esc($row['display_name'])?></a><br><small><?=esc($row['destination_slug'])?> · <?=esc($row['potential_subscription'])?></small></td><td><?=esc($labels[$row['channel']]??$row['channel'])?></td><td><?=esc($row['contact_value'])?></td><td><a href="<?=esc($row['source_url'])?>" target="_blank" rel="noopener noreferrer">Consultar fuente</a><br><?=esc($row['source_type'])?> · <?=esc($row['consulted_at'])?><br><small><?=esc($row['source_period'])?></small><br><?=esc($evidenceLabels[$row['evidence_status']]??$row['evidence_status'])?><details><summary>Ver procedencia</summary><p><?=esc($row['source_note'])?></p></details></td><td><?=esc($row['authorization_status'])?><br><small><?=esc($row['legal_basis_status'])?></small><br><?=!empty($row['suppressed'])?'Contacto excluido':(!empty($row['campaign_allowed'])?'Revisar autorización registrada':'Campañas no habilitadas')?></td></tr><?php endforeach ?><?php if(!$rows):?><tr><td colspan="5">Todavía no hay contactos privados que coincidan. Carga el archivo complementario después de importar los negocios.</td></tr><?php endif ?></tbody></table></div>
<?php if($pageCount>1):?><nav class="admin-pagination" aria-label="Páginas de contactos"><?php for($n=1;$n<=$pageCount;$n++):?><a href="?q=<?=rawurlencode($query)?>&amp;business_id=<?=$businessId?>&amp;page=<?=$n?>" <?=$page===$n?'aria-current="page"':''?>><?=$n?></a><?php endfor ?></nav><?php endif ?></section></main></body></html>
