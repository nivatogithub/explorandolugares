<?php
require ($argv[1]??__DIR__.'/../../backend/app').'/PortalPresentation.php';
foreach(['es','en','pt-BR'] as $locale){
 $header=PortalPresentation::header(['locale'=>$locale,'path'=>'/']);
 foreach(['logo-transparente-20261106-360.webp','logo-transparente-20261106-140.webp 140w','logo-transparente-20261106-280.webp 280w','width="360" height="360"','sizes="(max-width:650px) 70px'] as $expected){
  if(!str_contains($header,$expected))throw new RuntimeException('Missing responsive brand: '.$expected);
 }
 $footer=PortalPresentation::footer(['locale'=>$locale,'path'=>'/']);
 if(substr_count($footer,'class="footer-contact-link footer-help-link"')!==1||!str_contains($footer,'title="'))throw new RuntimeException('Missing accessible help icon');
 if(!str_contains($footer,'href="https://www.youtube.com/@explorandolugaresco" target="_blank" rel="noopener noreferrer"'))throw new RuntimeException('Incorrect YouTube channel');
 $dom=new DOMDocument();@$dom->loadHTML('<!doctype html><html><body>'.$header.$footer.'</body></html>');
 $xpath=new DOMXPath($dom);
 if($xpath->query('//header')->length!==1||$xpath->query('//footer')->length!==1)throw new RuntimeException('Duplicated shell');
 if($xpath->query('//footer/div[contains(@class,"wrap")]/button[contains(@class,"el-measurement-preferences")]')->length!==1)throw new RuntimeException('Privacy control outside footer container');
 foreach(['footer-contact-youtube','footer-help-link'] as $class){
  if($xpath->query('//footer//a[contains(@class,"'.$class.'")]//svg')->length!==1)throw new RuntimeException('Missing server-rendered contact icon');
 }
 if(str_contains($header.$footer,'<script'))throw new RuntimeException('Shell content must not require script injection');
 $contact='/contact.php'.($locale==='es'?'':'?lang='.strtolower($locale));
 if(!str_contains($footer,'href="'.$contact.'"'))throw new RuntimeException('Lost contact language');
}
$css=PortalPresentation::assets(false,'public','css');
if(substr_count($css,'rel="icon"')!==2||!str_contains($css,'apple-touch-icon'))throw new RuntimeException('Missing site icons');
if(str_contains(PortalPresentation::assets(false,'public','js'),'rel="icon"'))throw new RuntimeException('Icons emitted with scripts');
$preview=PortalPresentation::footer(['disable_contacts'=>true]);
if(str_contains($preview,'href="https://www.youtube.com/'))throw new RuntimeException('Demo contact should be disabled');
echo "OK: shared responsive brand and icons, ES/EN/PT-BR\n";
