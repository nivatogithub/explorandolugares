<?php
require_once dirname(__DIR__,2).'/app/BusinessServiceSections.php';
$sectionLocales=($serviceSectionAdmin??false)?['es'=>'Español','en'=>'English','pt-BR'=>'Português']:['es'=>'Español'];
$sectionValues=$_POST['service_sections']??($record['service_sections']??[]);
?>
<fieldset data-premium-sections <?=($selectedPlan??$_POST['plan_code']??'free')==='page_premium'?'':'hidden disabled'?>><legend>Secciones de servicios · Premium</legend><p>Activa una sección escribiendo su descripción. Las vacías no se muestran. Hasta 1.200 caracteres por sección e idioma. No crea experiencias ni incluye presencia como operador. La portada y las dos fotos adicionales son el límite de toda la página.</p>
<?php foreach($sectionLocales as $sectionLocale=>$sectionLanguage):?><div class="destination-form-grid"><h3 class="wide"><?=esc($sectionLanguage)?></h3><?php foreach(BusinessServiceSections::labels($sectionLocale) as $sectionType=>$sectionLabel):?><label class="wide"><?=esc($sectionLabel)?><textarea name="service_sections[<?=esc($sectionLocale)?>][<?=esc($sectionType)?>]" maxlength="1200" rows="3"><?=esc($sectionValues[$sectionLocale][$sectionType]??'')?></textarea></label><?php endforeach;?></div><?php endforeach;?></fieldset>
<script src="/assets/business-service-sections.js?v=1" defer></script>
