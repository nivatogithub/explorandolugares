<?php
declare(strict_types=1);
if (!isset($selectedBusiness,$responsibleAccounts)||!$selectedBusiness) return;
?>
<div class="crm-person-section" id="responsable-del-negocio">
<h3>Cuenta responsable</h3>
<p class="crm-help">La cuenta contiene el nombre, correo y teléfono de la persona responsable y puede administrar varios negocios. Estos datos son privados; los canales generales del establecimiento se muestran arriba.</p>
<?php if(!$responsibleAccounts):?>
<div class="crm-empty"><strong>Sin cuenta responsable asociada</strong><p>Este negocio puede tener canales investigados sin ser cliente. La cuenta se asocia cuando se aprueba la solicitud de su responsable.</p></div>
<?php else:$account=$responsibleAccounts[0];?>
<article class="crm-person-card" data-responsible-account-id="<?=esc($account['account_id'])?>"><div class="crm-person-heading"><h4><?=esc($account['display_name'])?></h4><span class="crm-status crm-status-ready">Cuenta responsable</span></div><ul class="crm-person-channels"><li><strong>Correo de la cuenta</strong><span><?=esc($account['email'])?></span></li><li><strong>Teléfono del responsable</strong><span><?=esc($account['contact_phone']?:'No registrado')?></span></li></ul><p class="crm-help">Cuenta <?=$account['account_status']==='disabled'?'deshabilitada':'asociada'?>. El acceso se gestiona en <a href="usuarios.php">Cuentas</a>.</p>
<?php if($accountProfileReady):?><details class="crm-person-edit"><summary>Actualizar teléfono del responsable</summary><form method="post" class="crm-person-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save_responsible_phone"><input type="hidden" name="business_id" value="<?=esc($selectedBusiness['id'])?>"><div class="crm-person-fields"><?php require_once dirname(__DIR__,2).'/business/contact-phone-fields.php';contactPhoneField('contact_phone','Teléfono de la persona responsable',(string)$account['contact_phone']);?></div><p class="crm-help">Se actualiza en la cuenta y estará disponible desde todos los negocios que administra.</p><button class="pill compact" type="submit">Guardar teléfono</button></form></details><?php endif ?></article>
<?php endif ?>
</div>
