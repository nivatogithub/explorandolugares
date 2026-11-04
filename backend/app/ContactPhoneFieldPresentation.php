<?php
declare(strict_types=1);
final class ContactPhoneFieldPresentation
{
    public static function render(string $name,string $label,string $number,string $help='Escribe un celular de diez dígitos o un fijo con su indicativo 60X. El país es Colombia.'):void
    {
  ?>
  <label class="contact-phone-single wide">
    <span><?=htmlspecialchars($label,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></span>
    <span class="contact-phone-single-control"><span aria-hidden="true">+57</span><input type="tel" name="<?=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>_number" inputmode="tel" autocomplete="tel-national" maxlength="24" value="<?=htmlspecialchars($number,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>" placeholder="300 123 4567"></span>
    <small><?=htmlspecialchars($help,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></small>
  </label>
  <?php
    }
}
