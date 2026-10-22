<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessCategories.php';
require_once dirname(__DIR__,2).'/app/views/directory-helpers.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit('Solo vista previa.');}
$id=filter_var($_GET['id']??'',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(404);exit('No existe ese negocio.');}
try{
 $q=db()->prepare('SELECT b.id,b.public_id,b.publication_status,b.information_status,b.category_code AS code,b.address_text,b.location_reference,b.latitude,b.longitude,b.location_map_url,t.display_name,t.summary,t.description,ct.label AS category_label FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale="es" WHERE b.id=?');$q->execute([$id]);$business=$q->fetch(PDO::FETCH_ASSOC);
 if(!$business){http_response_code(404);exit('No existe ese negocio.');}
 $q=db()->prepare('SELECT plan_code FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$business['plan_code']=$q->fetchColumn()?:'free';
 $q=db()->prepare('SELECT name FROM subscription_plan_translations WHERE plan_code=? AND locale="es"');$q->execute([$business['plan_code']]);$business['plan_name']=$q->fetchColumn()?:$business['plan_code'];
 $q=db()->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$q->execute([$business['plan_code']]);$business['features']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
 $business=BusinessCategories::enrich(db(),[$business],'es','code','category_label')[0];
 $q=db()->prepare('SELECT m.asset_path AS image_path,m.width,m.height FROM business_media bm JOIN media_assets m ON m.id=bm.media_asset_id WHERE bm.business_id=? AND bm.role="logo" ORDER BY bm.display_order,bm.media_asset_id LIMIT 1');$q->execute([$id]);$business=array_merge($business,['image_path'=>null,'width'=>null,'height'=>null],$q->fetch(PDO::FETCH_ASSOC)?:[]);
 $q=db()->prepare('SELECT channel,contact_value FROM business_contacts WHERE business_id=? AND is_public=1 ORDER BY display_order,id');$q->execute([$id]);$contacts=BusinessContactVisibility::filter($business['plan_code'],$q->fetchAll(PDO::FETCH_ASSOC));
}catch(PDOException $error){http_response_code(503);exit('No se pudo preparar la vista previa.');}
$locale='es';$localePrefix='';$d=['public_contacts'=>[$id=>$contacts]];
$t=['featured'=>'Destacado','map'=>'Ver ubicación','verified'=>'Información verificada','public_data'=>'Información pública','more_info'=>'Más información','contact_business'=>'Contacto del negocio','business_page'=>'Sobre la página completa'];
$business['demo_page_url']='#pagina-completa';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vista previa de tarjeta | Administración</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/directory-tiers.css"><link rel="stylesheet" href="/assets/business-demo.css"></head><body><main class="wrap"><h1>Vista previa de tarjeta</h1><p class="notice">Vista privada con los datos de este negocio y su plan seleccionado. No publica ni cambia información. Si no se seleccionó otro plan, se muestra Gratis.</p><p><strong><?=esc($business['plan_name'])?></strong> · <?=esc($business['publication_status']==='published'?'Publicado':($business['publication_status']==='archived'?'Retirado':'Pendiente de publicación'))?></p>
<section class="directory-tier" data-directory-tier="<?=esc(in_array($business['plan_code'],['page','page_premium'],true)?'page':$business['plan_code'])?>"><div class="directory-grid"><?php require dirname(__DIR__,2).'/app/views/directory-card.php'; ?></div></section>
<?php if(isset($business['features']['business_page'])):?><section id="pagina-completa"><h2>Página comercial</h2><p>Esta pantalla permite revisar la tarjeta. La vista previa privada de la página completa todavía está pendiente; no se muestra un ejemplo ficticio como si fuera este negocio.</p></section><?php endif ?>
<p><a href="negocios.php?tab=lista">Volver a negocios</a> · <a href="negocios.php?id=<?=esc($id)?>">Editar datos administrativos</a> · <a href="suscripciones.php?id=<?=esc($id)?>">Gestionar plan y publicación</a></p></main></body></html>
