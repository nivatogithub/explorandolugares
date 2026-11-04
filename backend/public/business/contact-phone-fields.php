<?php
if(!function_exists('esc')){http_response_code(404);exit;}
require_once __DIR__.'/../../app/ContactPhoneFieldPresentation.php';
function contactPhoneField(string $name,string $label,string $value=''):void {
  $digits=preg_replace('/\D/','',$value);
  if(strlen($digits)===12&&str_starts_with($digits,'57'))$digits=substr($digits,2);
  ContactPhoneFieldPresentation::render($name,$label,(string)($_POST[$name.'_number']??$digits));
}
