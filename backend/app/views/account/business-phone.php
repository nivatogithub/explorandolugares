<?php declare(strict_types=1); ?>
<fieldset class="wide business-phone-fields" data-business-phone data-plan-field="phone">
  <legend>Teléfono y WhatsApp</legend>
  <p>Colombia · el código <strong>+57</strong> y, para fijos, el indicativo del lugar se agregan automáticamente.</p>
  <input type="hidden" name="business_phone[area]" value="<?=esc($area)?>" data-phone-area>
  <section class="business-phone-section">
    <span class="business-phone-label">Número de teléfono</span>
    <div class="business-phone-entry">
      <div class="business-phone-types" aria-label="Tipo de teléfono">
        <label><input type="radio" name="business_phone[type]" value="mobile" <?=($phoneForm['type']??'mobile')==='mobile'?'checked':''?>> Celular</label>
        <label><input type="radio" name="business_phone[type]" value="fixed" <?=($phoneForm['type']??'')==='fixed'?'checked':''?>> Teléfono fijo</label>
      </div>
      <span class="business-phone-control"><input class="business-phone-prefix" type="text" value="+57" disabled aria-label="Prefijo automático" data-phone-prefix><input type="tel" name="business_phone[number]" inputmode="tel" maxlength="24" autocomplete="tel-national" value="<?=esc($phoneForm['number']??'')?>" aria-label="Número de teléfono" aria-describedby="business-phone-help"></span>
    </div>
    <small id="business-phone-help" data-phone-help></small>
  </section>
  <section class="business-phone-section">
    <span class="business-phone-label">WhatsApp</span>
    <div class="business-phone-entry">
      <label class="registration-consent"><input type="checkbox" name="business_phone[same_whatsapp]" value="1" <?=($phoneForm['same_whatsapp']??'')==='1'?'checked':''?>><span>Usa el mismo número</span></label>
      <span class="business-phone-control"><input class="business-phone-prefix" type="text" value="+57" disabled aria-label="Prefijo automático" data-wa-prefix><input type="tel" name="business_phone[whatsapp]" inputmode="tel" maxlength="24" value="<?=esc($phoneForm['whatsapp']??'')?>" aria-label="Número de WhatsApp"></span>
    </div>
  </section>
  <p data-phone-preview aria-live="polite"></p>
</fieldset>
