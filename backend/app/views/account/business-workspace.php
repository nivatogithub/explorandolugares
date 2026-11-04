<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?=$e($context['locale'])?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Mis negocios | Explorando Lugares</title>
<?=PortalPresentation::assets(true,'public','css')?>
<?php foreach(['portal','workspace','grid','qr','identity'] as $key):?><link rel="stylesheet" href="<?=$e($resourceUrls[$key])?>"><?php endforeach ?>
<?=PortalPresentation::assets(true,'public','js')?>
</head>
<body class="portal-account-page"><?=PortalPresentation::header($context)?>
<main class="wrap account-portal">
<div class="account-profile-heading"><div><p class="eyebrow">Mi cuenta</p><h1>Mis negocios</h1><p><?=$e($data['displayName'])?></p></div><?php AccountProfilePresentation::renderLogout($data['csrf']);?></div>
<nav class="account-tabs" aria-label="Secciones de Mi cuenta"><a href="/negocios/portal/?tab=personal">Viajero</a><a href="/negocios/portal/?tab=business" aria-current="page">Mis negocios</a></nav>
<?php if($data['isAdministrator']):?><p><a class="pill light" href="/admin/index.php">Abrir administración</a></p><?php endif?>
<?php if($data['error']):?><p class="notice" role="alert"><?=$e($data['error'])?></p><?php endif?>
<?php if($data['couponApplied']):?><p class="notice" role="status">Cupón aplicado. Tu suscripción quedó cubierta sin pago ni comprobante. La solicitud sigue privada y pendiente de validación; todavía no está publicada.</p><?php endif?>
<?php if($data['result']==='publicado'):?><p class="notice" role="status">Tu negocio quedó publicado.</p><?php elseif($data['result']==='correcciones'):?><p class="notice" role="status">Recibimos tus comentarios. El equipo revisará las correcciones antes de enviarte otra propuesta.</p><?php elseif($data['paymentSent']):?><p class="notice" role="status">Comprobante recibido. Consulta el estado actualizado del negocio en esta lista.</p><?php elseif($data['registrationSaved']):?><p class="notice" role="status">Registro guardado. Puedes seguir su estado en esta lista.</p><?php endif?>
<section class="destination-admin-list">
<div class="business-grid-heading"><h2>Tus negocios</h2><a class="pill light" href="/business/payment.php">Pagar o renovar uno o varios negocios</a><a class="pill" href="/negocios/registro/?tipo=business">Registrar o reclamar un negocio</a></div>
<p>Continúa un borrador, consulta el estado o revisa la publicación desde la fila del negocio.</p>
<?php if(!$data['rows']):?><p>Todavía no tienes negocios ni borradores registrados.</p><?php else:?>
<p class="business-grid-scroll-help">En pantallas pequeñas, desliza la tabla para ver el estado y las acciones.</p>
<div class="destination-admin-table" tabindex="0" role="region" aria-label="Listado de tus negocios"><table class="business-workspace-grid"><caption class="visually-hidden">Negocios, borradores y acciones disponibles</caption><thead><tr><th>Negocio</th><th>Lugar</th><th>Suscripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($data['rows'] as $row):?>
<tr data-business-row="<?=$e($row['kind'])?>">
<td><strong class="business-row-name" title="<?=$e($row['display_name'])?>"><?=$e($row['display_name'])?></strong></td>
<td><?=$e($row['destination_name'])?></td><td><?=$e($data['planNames'][$row['plan_code']]??'Por seleccionar')?></td>
<td><span class="business-status" data-status="<?=$e($row['state']['code'])?>"><?=$e($row['state']['label'])?></span></td>
<td><div class="business-row-actions">
<?php if($row['kind']==='draft'):?><a class="business-icon-action" href="/negocios/registro/?tipo=business&amp;draft=<?=$e($row['draft_id'])?>" aria-label="Continuar registro" title="Continuar registro"><?=BusinessWorkspacePresentation::icon('edit')?></a>
<?php elseif($row['kind']==='business'):?>
<?php $businessQr=$row['qr'];if($businessQr):?><button type="button" class="business-icon-action business-qr-trigger" data-business-qr-url="<?=$e($businessQr['url'])?>" data-business-qr-name="<?=$e($businessQr['name'])?>" data-business-qr-filename="<?=$e($businessQr['filename'])?>" aria-haspopup="dialog" aria-controls="business-qr-dialog" aria-label="Generar QR de <?=$e($businessQr['name'])?>" title="Generar QR"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 13v4h-2v4h4v-2h4v-6h-3M12 3v7M3 12h7M12 12h2M19 21h2"/></svg></button><?php endif?>
<?php if($row['state']['pay']):$paymentLabel=$row['state']['code']==='clarification'?'Corregir soporte de pago':'Continuar al pago';?><a class="business-icon-action" href="/business/payment.php?business=<?=(int)$row['id']?>" aria-label="<?=$e($paymentLabel)?>" title="<?=$e($paymentLabel)?>"><?=BusinessWorkspacePresentation::icon('payment')?></a><?php endif?>
<?php if($row['version']):?><a class="business-icon-action" href="/business/editorial.php?business=<?=(int)$row['id']?>&amp;version=<?=(int)$row['version']['id']?>&amp;preview=card" data-business-preview data-preview-title="Tarjeta · <?=$e($row['display_name'])?>" aria-label="Ver tarjeta" title="Ver tarjeta"><?=BusinessWorkspacePresentation::icon('card')?></a>
<?php if(in_array($row['plan_code'],['page','page_premium'],true)):?><a class="business-icon-action" href="/business/editorial.php?business=<?=(int)$row['id']?>&amp;version=<?=(int)$row['version']['id']?>&amp;preview=page" data-business-preview data-preview-title="Página · <?=$e($row['display_name'])?>" aria-label="Ver página" title="Ver página"><?=BusinessWorkspacePresentation::icon('page')?></a><?php endif?>
<?php endif?>
<?php if($row['state']['publish']):?>
<form method="post" class="business-owner-decision">
<input type="hidden" name="csrf" value="<?=$e($data['csrf'])?>"><input type="hidden" name="business_id" value="<?=(int)$row['id']?>"><input type="hidden" name="version_id" value="<?=(int)$row['version']['id']?>"><input type="hidden" name="payload_hash" value="<?=$e($row['version']['payload_hash'])?>">
<button class="business-icon-action business-icon-action--primary" name="action" value="owner_publish" aria-label="Aprobar y publicar" title="Aprobar y publicar"><?=BusinessWorkspacePresentation::icon('publish')?></button>
<?php if($row['version']['status']==='awaiting_owner'):?><button class="business-icon-action" type="button" data-owner-changes data-business-name="<?=$e($row['display_name'])?>" aria-label="Solicitar correcciones" title="Solicitar correcciones"><?=BusinessWorkspacePresentation::icon('comment')?></button><?php endif?>
</form>
<?php elseif($row['state']['code']==='published'):?><a class="business-icon-action" href="/negocios/<?=$e($row['public_id'])?>/" aria-label="Ver publicación" title="Ver publicación"><?=BusinessWorkspacePresentation::icon('external')?></a><?php endif?>
<?php endif?>
<?php if($row['message']):?><button class="business-icon-action" type="button" data-business-notice="<?=$e($row['message'])?>" aria-label="Detalles del estado" title="Detalles del estado"><?=BusinessWorkspacePresentation::icon('info')?></button><?php endif?>
</div></td></tr>
<?php endforeach?></tbody></table></div><?php endif?>
</section>
<section class="destination-admin-list" id="historial-pagos"><h2>Historial de pagos</h2>
<?php if(!$data['payments']):?><p>Aquí aparecerán los comprobantes que envíes.</p><?php else:?>
<div class="destination-admin-table"><table><thead><tr><th>Negocio</th><th>Plan y periodo</th><th>Importe pagado</th><th>Fecha del pago</th><th>Estado</th><th>Fecha de aprobación</th></tr></thead><tbody>
<?php foreach($data['payments'] as $payment):?><tr>
<td><?php if(!empty($payment['order_items'])):?>Orden <?=$e($payment['checkout_reference'])?><ul><?php foreach($payment['order_items'] as $paidItem):?><li><?=$e($paidItem['business_name'])?> · <?=$e($data['planNames'][$paidItem['quote']['plan_code']])?> · <?=$e($data['periodNames'][$paidItem['quote']['billing_frequency']])?></li><?php endforeach?></ul><?php else:?><?=$e($payment['business_name'])?><?php endif?></td><td><?php if(!empty($payment['order_items'])):?>Varios conceptos<?php else:?><?=$e($data['planNames'][$payment['plan_code']]??$payment['plan_name'])?> · <?=$e($data['periodNames'][$payment['billing_frequency']]??'Sin periodo')?><?php endif?></td>
<td><?=$e(number_format((float)$payment['paid_amount'],2,',','.').' '.$payment['currency'])?></td><td><?=$e($payment['transaction_date']?:$payment['submitted_at'])?></td>
<td><?=$e(['pending'=>'Pendiente de validación','approved'=>'Aprobado','clarification_requested'=>'Requiere aclaración','rejected'=>'Rechazado'][$payment['status']]??$payment['status'])?><?php if($payment['customer_message']):?><p class="business-row-message"><?=$e($payment['customer_message'])?></p><?php endif?></td>
<td><?=$e($payment['status']==='approved'?($payment['reviewed_at']??'Por confirmar'):'Pendiente')?></td>
</tr><?php endforeach?></tbody></table></div><?php endif?>
</section>
<p class="business-workspace-help">La presencia contratada conserva su vigencia. Si no deseas continuar después, no realices una renovación.</p>
<dialog class="business-preview-dialog" aria-labelledby="business-preview-title"><div class="business-preview-toolbar"><h2 id="business-preview-title">Vista previa de tu negocio</h2><button type="button" class="pill light" data-close-business-preview>Cerrar</button></div><iframe title="Vista previa privada del negocio"></iframe></dialog>
<dialog class="business-action-dialog" id="business-changes-dialog" aria-labelledby="business-changes-title"><h2 id="business-changes-title">Solicitar correcciones</h2><p data-changes-business></p><form method="post">
<input type="hidden" name="csrf" value="<?=$e($data['csrf'])?>"><input type="hidden" name="action" value="owner_changes"><input type="hidden" name="business_id"><input type="hidden" name="version_id"><input type="hidden" name="payload_hash">
<label>Motivo de rechazo · máximo <?=$data['reviewNoteLimit']?> caracteres<textarea name="note" minlength="8" maxlength="<?=$data['reviewNoteLimit']?>" rows="6" data-text-counter data-autogrow data-text-counter-limit-message=" · Superas el límite; resume el motivo." required></textarea></label><div class="business-dialog-actions"><button class="pill light" type="button" data-close-dialog>Cancelar</button><button class="pill" type="submit">Enviar comentarios</button></div></form></dialog>
<dialog class="business-action-dialog" id="business-notice-dialog" aria-labelledby="business-notice-title"><h2 id="business-notice-title">Detalles del estado</h2><p data-business-notice-text></p><button class="pill light" type="button" data-close-dialog>Cerrar</button></dialog>
</main><?=PortalPresentation::footer($context)?><script src="<?=$e($resourceUrls['workspaceScript'])?>" defer></script><script src="<?=$e($resourceUrls['countersScript'])?>" defer></script><?php require __DIR__.'/business-qr-dialog.php';?>
<script src="<?=$e($resourceUrls['qrVendor'])?>" defer></script>
<script src="<?=$e($resourceUrls['qrScript'])?>" defer></script></body></html>
