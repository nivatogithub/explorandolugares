<?php
if(!function_exists('db')||empty($_SESSION['admin'])||!isset($request)){http_response_code(404);exit;}
$snapshot=json_decode((string)($request['business_details_json']??'{}'),true);
if(!is_array($snapshot)||!$snapshot){echo '<p>Dato histórico no disponible.</p>';return;}
$fields=[];
foreach(['summary'=>'Presentación','description'=>'Descripción','brand_story'=>'Historia','featured_services'=>'Servicios destacados'] as $key=>$label)if(!empty($snapshot['text'][$key]))$fields[$label]=$snapshot['text'][$key];
foreach(['address_text'=>'Dirección enviada','location_reference'=>'Referencia enviada','opening_hours'=>'Horarios enviados','price_range'=>'Rango de precios','capacity_count'=>'Capacidad','menu_url'=>'Menú o catálogo'] as $key=>$label)if(isset($snapshot['location'][$key])&&$snapshot['location'][$key]!=='')$fields[$label]=$snapshot['location'][$key];
$categoryNames=db()->query('SELECT category_code,label FROM service_category_translations WHERE locale="es"')->fetchAll(PDO::FETCH_KEY_PAIR);
if(!empty($snapshot['categories']))$fields['Categorías enviadas']=implode(' · ',array_map(static fn($code)=>$categoryNames[$code]??$code,$snapshot['categories']));
foreach($snapshot['channels']??[] as $channel=>$value)$fields['Canal enviado · '.(['email'=>'Correo','phone'=>'Teléfono','whatsapp'=>'WhatsApp','website'=>'Sitio web','instagram'=>'Instagram','facebook'=>'Facebook','linkedin'=>'LinkedIn'][$channel]??$channel)]=$value;
$choice=$snapshot['requested_subscription']??null;
if(is_array($choice)){
 $fields['Suscripción solicitada']=['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$choice['plan_code']??'']??($choice['plan_code']??'Dato histórico no disponible');
 $fields['Periodicidad solicitada']=['monthly'=>'Mensual','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'][$choice['billing_frequency']??'']??($choice['billing_frequency']??'Dato histórico no disponible');
 if(isset($choice['quoted_amount']))$fields['Importe conservado de la solicitud']='$'.number_format((float)$choice['quoted_amount'],0,',','.').' '.($choice['currency']??'COP');
}
if(isset($snapshot['responsibility_declared']))$fields['Declaración de responsabilidad']=$snapshot['responsibility_declared']?'Aceptada':'No registrada';
?><dl class="admin-request-snapshot"><?php foreach($fields as $label=>$value):?><dt><?=esc($label)?></dt><dd><?=nl2br(esc(is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))?></dd><?php endforeach ?></dl><p class="small-note">Datos conservados en la solicitud. Las validaciones internas y la ficha actual se presentan por separado; la asociación no certifica titularidad.</p>
