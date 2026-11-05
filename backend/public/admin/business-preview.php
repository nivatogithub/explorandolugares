<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessCategories.php';
require_once dirname(__DIR__,2).'/app/BusinessPreviewSelector.php';
require_once dirname(__DIR__,2).'/app/views/directory-helpers.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit('Solo vista previa.');}
$id=filter_var($_GET['id']??'',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(404);exit('No existe ese negocio.');}
try{
 $preview=(new BusinessPreviewSelector(db()))->find((int)$id);
 if(!$preview){http_response_code(404);exit('No existe ese negocio.');}
 $business=$preview['business'];
 $business=BusinessCategories::enrich(db(),[$business],'es','code','category_label')[0];
 $contacts=BusinessContactVisibility::filter($business['plan_code'],$preview['contacts']);
}catch(PDOException $error){http_response_code(503);exit('No se pudo preparar la vista previa.');}
$locale='es';$localePrefix='';$d=['public_contacts'=>[$id=>$contacts]];
$t=['featured'=>'Destacado','map'=>'Ver ubicación','verified'=>'Información verificada','public_data'=>'Información pública','more_info'=>'Más información','contact_business'=>'Contacto del negocio','business_page'=>'Sobre la página completa'];
$business['demo_page_url']='#pagina-completa';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vista previa de tarjeta | Administración</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/directory-tiers.css"><link rel="stylesheet" href="/assets/business-demo.css"><link rel="stylesheet" href="/assets/portal-identity.css?v=6"><script defer src="/assets/compact-business-cards.js?v=5"></script><script defer src="/assets/load-more-cards.js?v=1"></script></head><body><main class="wrap"><h1>Vista previa de tarjeta</h1><p class="notice">Vista privada con los datos de este negocio y su plan seleccionado. No publica ni cambia información. Si no se seleccionó otro plan, se muestra Gratis.</p><p><strong><?=esc($business['plan_name'])?></strong> · <?=esc($business['publication_status']==='published'?'Publicado':($business['publication_status']==='archived'?'Retirado':'Pendiente de publicación'))?></p>
<section class="directory-tier" data-directory-tier="<?=esc(in_array($business['plan_code'],['page','page_premium'],true)?'page':$business['plan_code'])?>"><div class="directory-grid"><?php require dirname(__DIR__,2).'/app/views/directory-card.php'; ?></div></section>
<?php if(isset($business['features']['business_page'])):?><section id="pagina-completa"><h2>Página comercial</h2><p>Esta pantalla permite revisar la tarjeta. La vista previa privada de la página completa todavía está pendiente; no se muestra un ejemplo ficticio como si fuera este negocio.</p></section><?php endif ?>
<p><a href="businesses.php?tab=lista">Volver a negocios</a> · <a href="businesses.php?id=<?=esc($id)?>">Editar datos administrativos</a> · <a href="subscriptions.php?id=<?=esc($id)?>">Gestionar plan y publicación</a></p></main></body></html>
