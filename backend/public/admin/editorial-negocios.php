<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialService.php';
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){http_response_code(403);exit('Inicia sesión en administración.');}
$_SESSION['last_seen']=time();$service=new BusinessEditorialService(db(),private_storage_path('business-image-references'));$ready=$service->ready();$rows=[];
if($ready)$rows=db()->query('SELECT v.id,v.business_id,v.status,v.plan_code,v.updated_at,v.note,t.display_name FROM business_editorial_versions v JOIN business_translations t ON t.business_id=v.business_id AND t.locale="es" WHERE v.id=(SELECT MAX(v2.id) FROM business_editorial_versions v2 WHERE v2.business_id=v.business_id) ORDER BY v.updated_at DESC,v.id DESC')->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preparación editorial | Explorando Lugares</title><?php require __DIR__.'/_theme.php';?><body class="portal-admin"><main class="wrap admin-page">
<?php require __DIR__.'/_navigation.php';?><a href="negocios.php">← Negocios</a><h1>Preparación editorial de negocios</h1>
<p>Versiones privadas, traducciones e imágenes finales. La aprobación del comerciante y el pago no sustituyen la publicación final.</p>
<p><a class="pill light" href="paquete-editorial.php">Descargar o importar paquete editorial</a></p>
<?php if(!$ready):?><p class="notice">Instala la actualización editorial desde Base de datos. No se modificó ninguna ficha.</p><?php endif ?>
<div class="destination-admin-table"><table><thead><tr><th>Negocio</th><th>Estado editorial</th><th>Suscripción</th><th>Actualizado</th><th>Revisión</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=esc($row['display_name'])?></td><td><?=esc(BusinessEditorialService::LABELS[$row['status']]??$row['status'])?><?php if($row['note']):?><p><?=esc($row['note'])?></p><?php endif ?></td><td><?=esc($row['plan_code'])?></td><td><?=esc($row['updated_at'])?></td><td><a href="/business/editorial.php?business=<?=$row['business_id']?>&amp;admin=1">Abrir versión y preparar contenido</a> · <a href="paquete-editorial.php?business=<?=$row['business_id']?>">Paquete</a></td></tr><?php endforeach ?>
<?php if(!$rows):?><tr><td colspan="5">Todavía no hay versiones privadas enviadas.</td></tr><?php endif ?></tbody></table></div>
</main></body></html>
