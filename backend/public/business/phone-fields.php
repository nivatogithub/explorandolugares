<?php
require_once dirname(__DIR__,2).'/app/BusinessPhone.php';

$phoneValues=$phoneValues??[];
$rawPhone=(string)($phoneValues['phone']??$phoneValues['phone_fixed']??'');
$digits=preg_replace('/\D/','',$rawPhone);
if(strlen($digits)===12&&str_starts_with($digits,'57'))$digits=substr($digits,2);
$fixed=str_starts_with($digits,'60');
$wa=(string)($phoneValues['whatsapp']??'');
$same=$rawPhone!==''&&preg_replace('/\D/','',$wa)===preg_replace('/\D/','',$rawPhone);
$phoneForm=$_POST['business_phone']??[
  'type'=>$fixed?'fixed':'mobile',
  'area'=>$fixed?substr($digits,0,3):'604',
  'number'=>$fixed?substr($digits,3):$digits,
  'whatsapp'=>$wa,
  'same_whatsapp'=>$same?'1':'0',
];
$area=(string)($phoneForm['area']??'604');
if(!isset(BusinessPhone::AREAS[$area]))$area='604';
?>
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
