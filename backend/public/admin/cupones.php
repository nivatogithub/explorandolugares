<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_admin_session();
require_once dirname(__DIR__,2).'/app/BusinessPromotions.php';
$service=new BusinessPromotions(db());$error='';$editing=null;
header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(($_POST['action']??'save')!=='save')throw new RuntimeException('Los cupones no se eliminan. Puedes dejarlos inactivos sin perder su historial.');
        $code=$service->save($_POST,(string)($_SESSION['admin_user']??$_SESSION['admin']));
        header('Location: cupones.php?edit='.rawurlencode($code).'&guardado=1',true,303);exit;
    }
    $codes=$service->listing();$uses=$service->redemptions();
    $places=db()->query('SELECT d.id,d.publication_status,COALESCE(t.display_name,d.slug) AS name,d.department FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    foreach($codes as $item)if($item['code']===($_GET['edit']??''))$editing=$item;
}catch(Throwable $e){$error=$e instanceof PDOException?'No se pudo gestionar el cupón. Revisa las actualizaciones 056 y 057 de la base de datos.':$e->getMessage();http_response_code(400);$codes??=[];$uses??=[];$places??=[];}
$values=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:($editing?['code'=>$editing['code'],'percentage'=>$editing['percentage'],'plans'=>json_decode($editing['plans_json'],true),'periods'=>json_decode($editing['periods_json'],true),'enabled'=>$editing['enabled'],'max_uses'=>$editing['max_uses'],'expires_on'=>$editing['expires_at']?(new DateTimeImmutable($editing['expires_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d'):'']:['enabled'=>1,'percentage'=>100,'plans'=>['page'],'periods'=>['monthly']]);
$money=static fn($cents,$currency):string=>number_format((int)$cents/100,2,',','.').' '.$currency;
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cupones | Suscripciones</title><?php require __DIR__.'/_theme.php';?></head>
<body class="portal-admin"><main class="wrap admin-page"><?php require __DIR__.'/_navigation.php';?>
<p><a href="suscripciones.php">← Suscripciones</a></p><h1>Cupones promocionales</h1>
<p>El descuento aplica una sola vez por negocio al registrar su primer plan de pago. No se repite en renovaciones ni cubre complementos. El período empieza cuando el propietario publica, no cuando escribe el cupón.</p>
<p>Los cupones utilizados y su historial no se eliminan. Déjalos <strong>inactivos</strong> para impedir nuevos canjes: esto no cancela descuentos ya aplicados. Cada cupón requiere uno o varios lugares; los lugares nuevos no se incluyen automáticamente.</p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif?>
<?php if(isset($_GET['guardado'])):?><p class="notice" role="status">Cupón guardado. Los usos anteriores conservan sus condiciones originales.</p><?php endif?>
<section class="destination-admin-list"><h2><?=$editing?'Editar cupón':'Crear cupón'?></h2>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>">
<div class="destination-form-grid">
<label>Código<input name="code" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,31}" required value="<?=esc($values['code']??'')?>" <?=$editing?'readonly':''?> autocomplete="off"><small>Entre 3 y 32 letras, números o guiones. No distingue mayúsculas.</small></label>
<label>Descuento · %<input type="number" name="percentage" min="1" max="100" step="1" required value="<?=esc($values['percentage']??'')?>"></label>
<fieldset><legend>Planes permitidos</legend><?php foreach(BusinessPromotions::PLANS as $key=>$label):?><label class="admin-checkbox-field"><input type="checkbox" name="plans[]" value="<?=$key?>" <?=in_array($key,(array)($values['plans']??[]),true)?'checked':''?>><span><?=$label?></span></label><?php endforeach?></fieldset>
<fieldset><legend>Períodos permitidos</legend><?php foreach(BusinessSubscriptionChoice::PERIODS as $key=>$label):?><label class="admin-checkbox-field"><input type="checkbox" name="periods[]" value="<?=$key?>" <?=in_array($key,(array)($values['periods']??[]),true)?'checked':''?>><span><?=$label?></span></label><?php endforeach?></fieldset>
<?php $selectedPlaces=$_SERVER['REQUEST_METHOD']==='POST'?array_map('intval',(array)($_POST['destinations']??[])):array_column($editing['places']??[],'id');?>
<label class="wide">Lugares donde aplica<select name="destinations[]" multiple size="6" required aria-describedby="coupon-places-help"><?php foreach($places as $place):?><option value="<?=(int)$place['id']?>" <?=in_array((int)$place['id'],$selectedPlaces,true)?'selected':''?>><?=esc($place['name'].($place['department']?' · '.$place['department']:''))?><?=$place['publication_status']==='published'?'':' (no publicado)'?></option><?php endforeach?></select><small id="coupon-places-help">Selecciona al menos un lugar. Para elegir varios, mantén presionada Ctrl (Windows) o Cmd (Mac). Se valida la ubicación del negocio, no la residencia del propietario.</small></label>
<label>Último día para canjear · Colombia<input type="date" name="expires_on" value="<?=esc($values['expires_on']??'')?>"><small>Vacío: sin fecha de vencimiento. No acorta un beneficio ya canjeado.</small></label>
<label>Máximo total de usos<input type="number" name="max_uses" min="1" max="1000000" step="1" value="<?=esc($values['max_uses']??'')?>"><small>Vacío: sin límite global. Siempre una vez por negocio.</small></label>
<label class="wide">Estado del cupón<select name="enabled"><option value="1" <?=!empty($values['enabled'])?'selected':''?>>Activo · permite nuevos usos</option><option value="0" <?=empty($values['enabled'])?'selected':''?>>Inactivo · conserva los usos anteriores</option></select></label>
</div><button class="pill">Guardar cupón</button> <a href="cupones.php">Nuevo cupón</a></form></section>
<section class="destination-admin-list"><h2>Cupones disponibles</h2><div class="destination-admin-table"><table><thead><tr><th>Código</th><th>Descuento</th><th>Planes y períodos</th><th>Lugares</th><th>Estado</th><th>Usos</th><th></th></tr></thead><tbody>
<?php foreach($codes as $item):?><tr><td><?=esc($item['code'])?></td><td><?=esc($item['percentage'])?> %</td><td><?=esc(implode(', ',array_map(static fn($v)=>BusinessPromotions::PLANS[$v],json_decode($item['plans_json'],true))))?><br><small><?=esc(implode(', ',array_map(static fn($v)=>BusinessSubscriptionChoice::PERIODS[$v],json_decode($item['periods_json'],true))))?></small></td><td><?=esc(implode(', ',array_column($item['places'],'name'))?:'Sin lugares configurados')?></td><td><?=!(int)$item['enabled']?'Inactivo':($item['expires_at']&&$item['expires_at']<gmdate('Y-m-d H:i:s')?'Activo · vencido':($item['max_uses']!==null&&(int)$item['uses']>=(int)$item['max_uses']?'Activo · agotado':'Activo'))?></td><td><?=esc($item['uses'])?><?=($item['max_uses']!==null?' / '.esc($item['max_uses']):'')?></td><td><a href="?edit=<?=rawurlencode($item['code'])?>">Editar</a></td></tr><?php endforeach?>
<?php if(!$codes):?><tr><td colspan="7">Aún no hay cupones.</td></tr><?php endif?></tbody></table></div></section>
<section class="destination-admin-list"><h2>Últimos usos registrados</h2><p>Constancia del descuento; no representa un pago recibido. Desactivar o editar el cupón no cambia estos importes.</p><div class="destination-admin-table"><table><thead><tr><th>Negocio · cuenta</th><th>Cupón</th><th>Plan · período</th><th>Precio del plan</th><th>Descuento</th><th>Saldo contratado</th><th>Canje · UTC</th></tr></thead><tbody>
<?php foreach($uses as $use):?><tr><td><a href="paquete-editorial.php?business=<?=(int)$use['business_id']?>"><?=esc($use['business_name'])?></a><br><small><?=esc($use['account_name'])?> · <?=esc($use['destination_name']??'Lugar no disponible')?></small></td><td><?=esc($use['code'])?> · <?=esc($use['percentage'])?> %</td><td><?=esc(BusinessPromotions::PLANS[$use['plan_code']])?> · <?=esc(BusinessSubscriptionChoice::PERIODS[$use['billing_frequency']])?></td><td><?=esc($money($use['list_cents'],$use['currency']))?></td><td><?=esc($money($use['discount_cents'],$use['currency']))?></td><td><?=esc($money($use['due_cents'],$use['currency']))?><?php if((int)$use['due_cents']===0):?><br><small>Cubierto por cupón · sin comprobante</small><?php endif?></td><td><?=esc($use['redeemed_at'])?></td></tr><?php endforeach?>
<?php if(!$uses):?><tr><td colspan="7">Sin usos registrados.</td></tr><?php endif?></tbody></table></div></section>
</main></body></html>
