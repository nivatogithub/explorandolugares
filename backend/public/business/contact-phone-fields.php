<?php
if(!function_exists('esc')){http_response_code(404);exit;}

function contactPhoneField(string $name,string $label,string $value=''):void {
  $digits=preg_replace('/\D/','',$value);
  if(strlen($digits)===12&&str_starts_with($digits,'57'))$digits=substr($digits,2);
  $number=(string)($_POST[$name.'_number']??$digits);
  ?>
  <label class="contact-phone-single wide">
    <span><?=esc($label)?></span>
    <span class="contact-phone-single-control"><span aria-hidden="true">+57</span><input type="tel" name="<?=esc($name)?>_number" inputmode="tel" autocomplete="tel-national" maxlength="24" value="<?=esc($number)?>" placeholder="300 123 4567"></span>
    <small>Escribe un celular de diez dígitos o un fijo con su indicativo 60X. El país es Colombia.</small>
  </label>
  <?php
}
