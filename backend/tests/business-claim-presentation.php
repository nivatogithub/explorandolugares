<?php
declare(strict_types=1);
require_once __DIR__.'/../app/BusinessClaimPresentation.php';
$public=__DIR__.'/../public';PortalPresentation::setPublicRoot($public);
$data=['places'=>[['id'=>1,'display_name'=>'Nombre']], 'placeId'=>1,
 'categories'=>[['code'=>'hotel','label'=>'Alojamiento']], 'category'=>'hotel','name'=>'<script>',
 'results'=>[['id'=>5,'display_name'=>'Nombre','information_status'=>'VERIFICADO','address_text'=>'<Calle>']],
 'account'=>['display_name'=>'Nombre','email'=>'test@example.invalid'],'error'=>'','success'=>false,
 'csrf'=>'synthetic-csrf','phoneNumber'=>'<300>','registerUrl'=>'/negocios/registro/','loginUrl'=>'/negocios/acceso/'];
foreach(['es'=>['¿Tu negocio ya aparece?','Información verificada'],
         'en'=>['Is your business already listed?','Verified information'],
         'pt-br'=>['Seu negócio já aparece?','Informações verificadas']] as $locale=>$expected){
    ob_start();BusinessClaimPresentation::render(['locale'=>$locale],$data,$public);$html=ob_get_clean();
    foreach($expected as $text)if(!str_contains($html,$text))throw new RuntimeException('Missing translation: '.$locale);
    foreach(['value="&lt;script&gt;"','value="&lt;300&gt;"','&lt;Calle&gt;','synthetic-csrf','data-update-error=','>Nombre</option>','<strong>Nombre</strong>'] as $text)
        if(!str_contains($html,$text))throw new RuntimeException('Data, escaping or field lost: '.$locale.' / '.$text);
    if(str_contains($html,'<script>'))throw new RuntimeException('Unescaped input.');
    $prefix=$locale==='es'?'':('/'.$locale);
    foreach(['name="lang" value="'.$locale.'"','href="'.$prefix.'/negocios-locales/interes/"','href="'.$prefix.'/privacidad/"'] as $expectedUrl)
        if(!str_contains($html,$expectedUrl))throw new RuntimeException('Language navigation missing: '.$locale.' / '.$expectedUrl);
}
echo "OK: claim interface in three languages; stored names preserved and inputs escaped.\n";
