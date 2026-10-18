<?php
if(!function_exists('db')){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/AccountResidence.php';
$residenceCatalog=AccountResidence::catalog();$residenceValues=$residenceValues??[];
$residenceValue=static fn($key)=>esc($_POST['residence_'.$key]??$residenceValues[$key]??'');
?>
<fieldset class="account-residence" data-residence><legend>¿Dónde vives?</legend><p>Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.</p>
<div class="destination-form-grid">
<label>País<select name="residence_country_code" autocomplete="country" required><option value="">Selecciona tu país</option><?php foreach($residenceCatalog['countries'] as $code=>$label):?><option value="<?=esc($code)?>" <?=$residenceValue('country_code')===$code?'selected':''?>><?=esc(['CO'=>'Colombia','US'=>'Estados Unidos','BR'=>'Brasil'][$code]??$label)?></option><?php endforeach ?></select></label>
<label><span data-region-label>Departamento / estado / provincia</span><select name="residence_region_code"><option value="">Selecciona</option><?php foreach($residenceCatalog['regions'] as $country=>$regions):?><optgroup label="<?=esc($country)?>"><?php foreach($regions as $code=>$label):?><option value="<?=esc($code)?>" data-country="<?=esc($country)?>" <?=$residenceValue('country_code')===$country&&$residenceValue('region_code')===$code?'selected':''?>><?=esc($label)?></option><?php endforeach ?></optgroup><?php endforeach ?></select><input name="residence_region_name" maxlength="120" autocomplete="address-level1" value="<?=$residenceValue('region_name')?>" placeholder="Región, estado o provincia"></label>
<label><span data-city-label>Ciudad / municipio</span><select name="residence_city_code"><option value="">Selecciona un municipio</option><?php foreach($residenceCatalog['municipalities'] as $city):?><option value="<?=esc($city['code'])?>" data-region="<?=esc($city['department_code'])?>" <?=$residenceValue('city_code')===$city['code']?'selected':''?>><?=esc($city['name'])?></option><?php endforeach ?></select><input name="residence_city_name" maxlength="120" autocomplete="address-level2" value="<?=$residenceValue('city_name')?>" placeholder="Ciudad o municipio"></label>
</div><noscript><p>Colombia: selecciona departamento y municipio. Estados Unidos y Brasil: selecciona estado y escribe ciudad. Otros países: escribe región y ciudad.</p></noscript></fieldset>
<script src="/assets/account-residence.js?v=1" defer></script>
