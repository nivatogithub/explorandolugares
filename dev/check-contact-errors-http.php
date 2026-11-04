<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-account-auth-http.php';$previous=ob_get_clean();
$catalog=json_decode(file_get_contents('/srv/portal/app/presentation/contact-errors.json'),true,64,JSON_THROW_ON_ERROR);
$checks=0;
layout_http_session($pdo,$account,'guest',function(string $session,string $csrf)use($catalog,&$checks):void{
 foreach(['es','en','pt-br'] as $locale){
  [$status,$html]=auth_http_request($session,'/contact.php?lang='.$locale,['csrf'=>$csrf,'website'=>'synthetic.invalid']);
  if($status!==400)throw new RuntimeException('Unexpected HTTP status '.$locale);
  $doc=new DOMDocument();$old=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);
  $xpath=new DOMXPath($doc);$alerts=$xpath->query('//main//p[@role="alert"]');
  if($alerts->length!==1||trim($alerts->item(0)->textContent)!==$catalog['validation_failed'][$locale])throw new RuntimeException('Wrong localized error '.$locale);
  $checks++;
 }
});
echo $previous.'OK HTTP/MariaDB: '.$checks." rechazos localizados; sin guardar solicitudes, sesiones sintéticas retiradas.\n";
