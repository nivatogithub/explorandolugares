<?php
declare(strict_types=1);
// Included only after authenticated business selection; this file has no standalone actions.
if (!isset($selectedBusiness,$peopleReady,$personContacts,$personOptions)||!$selectedBusiness) return;
?>
<div class="crm-person-section" id="personas-del-negocio">
<h3>Personas de contacto</h3>
<p class="crm-help">El propietario, representante, administrador u otra persona identificada. Sus canales se guardan separados de los correos, teléfonos y redes generales del negocio.</p>
<?php if(!$peopleReady):?>
<p class="crm-help">La gestión de personas requiere habilitar su estructura en <a href="base-datos.php">Base de datos</a>. Puedes seguir cargando los canales generales del negocio.</p>
<?php else:?>
<details class="crm-person-create"><summary>Recuperar responsables aprobados antes de esta actualización</summary><p class="crm-help">Si este negocio ya tenía cuentas autorizadas, puedes incorporar como contactos a sus responsables usando sus solicitudes aprobadas. Conserva los contactos que ya gestionaste y no cambia los permisos de las cuentas.</p><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="sync_approved_people"><input type="hidden" name="business_id" value="<?=esc($selectedBusiness['id'])?>"><button class="pill compact" type="submit">Vincular responsables ya aprobados</button></form></details>
<?php if(!$personContacts):?><div class="crm-empty crm-person-empty"><strong>No hay personas identificadas para este negocio</strong><p>Los canales empresariales pueden guardarse sin crear una persona. Añade un contacto cuando conozcas quién es y su relación con el negocio.</p></div><?php endif ?>
<div class="crm-person-list">
<?php foreach($personContacts as $person):?>
<article class="crm-person-card" data-person-contact-id="<?=esc($person['id'])?>">
<div class="crm-person-heading"><div><h4><?=esc($person['display_name'])?></h4><p><?=esc(implode(' · ',array_map(static fn($role)=>BusinessPersonContactService::ROLES[$role]??'Otro vínculo',$person['roles'])))?></p></div><span class="crm-status <?=$person['status']==='active'?'crm-status-ready':''?>"><?=$person['status']==='inactive'?'Contacto inactivo':($person['is_primary']?'Contacto principal':'Contacto activo')?></span></div>
<?php if($person['channels']):?><ul class="crm-person-channels"><?php foreach($person['channels'] as $personChannel):?><li><strong><?=esc($labels[$personChannel['channel']]??$personChannel['channel'])?></strong><span><?=esc($personChannel['channel_value'])?></span><small><?=$personChannel['business_id']===null?'Canal personal':'Canal de esta persona para este negocio'?><?=!empty($personChannel['suppressed'])?' · Excluido de comunicaciones':''?></small></li><?php endforeach ?></ul><?php else:?><p class="crm-help">Todavía no se guardaron canales de esta persona.</p><?php endif ?>
<p class="crm-help"><?=$person['account_id']?'Tiene una cuenta de acceso asociada. Sus permisos se administran en Cuentas.':'Sin cuenta de acceso asociada. Ser contacto no crea una cuenta.'?></p>
<details class="crm-person-edit"><summary><?=$person['status']==='active'?'Actualizar relación o añadir canales':'Reactivar contacto y revisar su relación'?></summary>
<form method="post" class="crm-person-form">
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save_person_contact"><input type="hidden" name="business_id" value="<?=esc($selectedBusiness['id'])?>"><input type="hidden" name="person_id" value="<?=esc($person['person_id'])?>">
<fieldset><legend>Relación con este negocio</legend><div class="crm-role-options"><?php foreach(BusinessPersonContactService::ROLES as $role=>$roleLabel):?><label><input type="checkbox" name="roles[]" value="<?=$role?>" <?=in_array($role,$person['roles'],true)?'checked':''?>><?=$roleLabel?></label><?php endforeach ?></div></fieldset>
<label class="crm-person-checkbox"><input type="checkbox" name="is_primary" value="1" <?=$person['is_primary']?'checked':''?>>Usar como contacto principal de este negocio</label>
<div class="crm-person-fields"><label>Añadir correo de esta persona<input type="email" name="email" maxlength="254" placeholder="Opcional"></label><label>Añadir teléfono de esta persona<input type="tel" name="phone" maxlength="40" placeholder="Opcional"></label><label>Ámbito de los canales añadidos<select name="channel_scope"><option value="business">Para comunicarse con esta persona sobre este negocio</option><option value="personal">Personales, compartidos entre sus negocios</option></select></label><label>Origen o explicación de la actualización<textarea name="source_note" rows="2" maxlength="1000" required><?=esc($person['source_note'])?></textarea></label></div>
<button class="pill compact" type="submit">Guardar cambios del contacto</button>
</form></details>
<?php if($person['status']==='active'):?><form method="post" class="crm-person-end"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="deactivate_person_contact"><input type="hidden" name="business_id" value="<?=esc($selectedBusiness['id'])?>"><input type="hidden" name="contact_id" value="<?=esc($person['id'])?>"><button type="submit">Marcar como contacto inactivo</button></form><?php endif ?>
</article>
<?php endforeach ?>
</div>
<details class="crm-person-create"><summary>Añadir una persona identificada</summary>
<form method="post" class="crm-person-form">
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save_person_contact"><input type="hidden" name="business_id" value="<?=esc($selectedBusiness['id'])?>">
<div class="crm-person-fields"><label>Persona ya registrada<select name="person_id"><option value="">Es una persona nueva</option><?php foreach($personOptions as $option):?><option value="<?=esc($option['id'])?>"><?=esc($option['display_name'])?> · referencia <?=esc($option['id'])?></option><?php endforeach ?></select><small>Selecciona a la misma persona si ya es contacto de otro negocio.</small></label><label>Nombre de la persona nueva<input name="display_name" maxlength="180" autocomplete="off" placeholder="Solo si seleccionaste persona nueva"><small>Usa el nombre que conoces; no escribas aquí el nombre del negocio.</small></label></div>
<fieldset><legend>Relación con este negocio · puedes seleccionar varias</legend><div class="crm-role-options"><?php foreach(BusinessPersonContactService::ROLES as $role=>$roleLabel):?><label><input type="checkbox" name="roles[]" value="<?=$role?>"><?=$roleLabel?></label><?php endforeach ?></div></fieldset>
<label class="crm-person-checkbox"><input type="checkbox" name="is_primary" value="1">Usar como contacto principal de este negocio</label>
<div class="crm-person-fields"><label>Correo de esta persona<input type="email" name="email" maxlength="254" placeholder="Opcional"></label><label>Teléfono de esta persona<input type="tel" name="phone" maxlength="40" placeholder="Opcional"></label><label>Ámbito de estos canales<select name="channel_scope"><option value="business">Para comunicarse con esta persona sobre este negocio</option><option value="personal">Personales, compartidos entre sus negocios</option></select></label><label>Cómo conociste a la persona u obtuviste sus datos<textarea name="source_note" rows="2" maxlength="1000" placeholder="Ej.: datos facilitados por la persona al solicitar la suscripción" required></textarea></label></div>
<p class="crm-help">Guarda únicamente canales atribuibles a esta persona. Los canales generales del establecimiento se cargan en su sección. Este registro no habilita campañas ni concede acceso al negocio.</p>
<button class="pill compact" type="submit">Guardar persona de contacto</button>
</form></details>
<?php endif ?>
</div>
