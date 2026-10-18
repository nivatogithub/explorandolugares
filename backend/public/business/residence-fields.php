<?php
if(!function_exists('db')){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/AccountResidence.php';
$residenceCatalog=AccountResidence::catalog();$residenceValues=$residenceValues??[];
$residenceLabel=static fn($value)=>mb_convert_case((string)$value,MB_CASE_TITLE,'UTF-8');
$residenceValue=static fn($key)=>esc($_POST['residence_'.$key]??$residenceValues[$key]??($key==='country_code'?'CO':''));
?>
<fieldset class="account-residence" data-residence><legend>¿Dónde vives?</legend><p>Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.</p>
<div class="destination-form-grid">
<label>País<select name="residence_country_code" autocomplete="country" required><?php foreach($residenceCatalog['countries'] as $code=>$label):?><option value="<?=esc($code)?>" <?=$residenceValue('country_code')===$code?'selected':''?>><?=esc($label)?></option><?php endforeach ?></select></label>
<label><span data-region-label>Departamento</span><select name="residence_region_code" autocomplete="address-level1" <?=$residenceValue('country_code')==='CO'?'required':'hidden disabled'?>><option value="">Selecciona un departamento</option><?php foreach($residenceCatalog['regions']['CO'] as $code=>$label):?><option value="<?=esc($code)?>" <?=$residenceValue('country_code')==='CO'&&$residenceValue('region_code')===$code?'selected':''?>><?=esc($residenceLabel($label))?></option><?php endforeach ?></select><input name="residence_region_name" autocomplete="address-level1" maxlength="120" <?=$residenceValue('country_code')==='CO'?'hidden disabled':'required'?> value="<?=$residenceValue('region_name')?>"></label>
<label><span data-city-label>Municipio / ciudad o pueblo</span><select name="residence_city_code" autocomplete="address-level2" <?=$residenceValue('country_code')==='CO'?'required':'hidden disabled'?>><option value="">Selecciona un municipio</option><?php foreach($residenceCatalog['municipalities'] as $city):?><option value="<?=esc($city['code'])?>" data-region="<?=esc($city['department_code'])?>" <?=$residenceValue('country_code')==='CO'&&$residenceValue('city_code')===$city['code']?'selected':''?>><?=esc($residenceLabel($city['name']))?></option><?php endforeach ?></select><input name="residence_city_name" autocomplete="address-level2" maxlength="120" <?=$residenceValue('country_code')==='CO'?'hidden disabled':'required'?> value="<?=$residenceValue('city_name')?>"></label>
</div><noscript><p>Colombia usa listas de departamento y municipio; otros países usan región y ciudad. Activa JavaScript para cambiar de país.</p></noscript></fieldset>
<script src="/assets/account-residence.js?v=1" defer></script>
