<link rel="stylesheet" href="<?=esc($resourceUrls['subscription-examples.css'])?>">
<script defer src="<?=esc($resourceUrls['subscription-examples.js'])?>">
</script>
<fieldset class="subscription-choice">
<legend>Elige la suscripción de tu negocio</legend>
<p>Selecciona cómo quieres aparecer. Esta solicitud no realiza ningún cobro ni publica el negocio.</p>
<div class="subscription-choice-options">
<?php foreach($subscriptionOptions as $choice):?>
<div class="subscription-choice-card">
<label>
<input type="radio" name="plan_code" value="<?=esc($choice['code'])?>" <?=$selectedPlan===$choice['code']?'checked':''?> required>
<span>
<strong>
<?=esc(['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$choice['code']]??$choice['name'])?>
</strong>
<span class="subscription-choice-description">
<?=esc(['free'=>'Nombre y categoría en el directorio. Sin contacto ni página propia.','featured'=>'Tarjeta destacada con descripción y contacto. Sin fotos ni página propia.','page'=>'Página propia, contacto y una foto de portada.','page_premium'=>'Página ampliada con estrella Premium, portada, hasta cinco productos o servicios con imagen y todos los canales de contacto.'][$choice['code']]??$choice['summary'])?>
</span>
<?php if($choice['code']==='free'):?>
<span class="subscription-choice-price">Gratis · Sin pago ni renovación</span>
<?php else:?>
<span class="subscription-choice-prices">
<?php foreach($periodLabels as $period=>$periodLabel):$quote=$choice['prices'][$period];?>
<span>
<b>
<?=esc($periodLabel)?>
</b> <?= $quote['quoted_amount']===null?'Por cotizar':esc('$'.number_format((float)$quote['quoted_amount'],0,',','.').' '.$quote['currency'])?>
</span>
<?php endforeach?>
</span>
<?php endif?>
</span>
</label>
<a class="text-link subscription-example" href="/business/demo.php?view=<?=esc($choice['code'])?>" data-subscription-example>
<?=in_array($choice['code'],['page','page_premium'],true)?'Ver tarjeta y página de ejemplo':'Ver tarjeta de ejemplo'?>
</a>
</div>
<?php endforeach?>
</div>
<label class="subscription-choice-period">Periodicidad para suscripciones pagas<select name="billing_frequency" required>
<?php foreach($periodLabels as $period=>$periodLabel):?>
<option value="<?=esc($period)?>" <?=$selectedPeriod===$period?'selected':''?>>
<?=esc($periodLabel)?>
</option>
<?php endforeach?>
</select>
<small>Los importes son el total del período e incluyen el descuento indicado frente a pagar mes a mes. El complemento de experiencia tiene el mismo descuento por período. Para Gratis esta selección no aplica.</small>
</label>
<p class="subscription-choice-note">Si el período figura “Por cotizar”, guardamos tu elección con precio pendiente; no se calcula multiplicando otra tarifa. En las suscripciones pagas, la validación y las instrucciones de pago se completan después. La elección no activa beneficios ni sustituye la verificación del negocio.</p>
</fieldset>
