<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessPhone.php';
/** Shared person/contact phone capture. Prefixes are composed again on the server. */
final class ContactPhone {
 public static function capture(array $input,string $name='phone'):string {
  if(!array_key_exists($name.'_national',$input))return trim((string)($input[$name]??''));
  $number=trim((string)($input[$name.'_national']??''));if($number==='')return '';
  $country=trim((string)($input[$name.'_country_code']??''));$type=(string)($input[$name.'_kind']??'');$area=(string)($input[$name.'_area']??'');
  if(!preg_match('/^[1-9][0-9]{0,2}$/D',$country)||!in_array($type,['mobile','fixed'],true))throw new RuntimeException('Selecciona celular o fijo e indica el código de país.');
  if($country==='57')return BusinessPhone::normalize($number,$type,$area);
  if(!preg_match('/^[0-9 () .-]{4,24}$/D',$number))throw new RuntimeException('Revisa los números del teléfono.');
  $n=preg_replace('/\D/','',$number);$foreignArea=$type==='fixed'?trim((string)($input[$name.'_foreign_area']??'')):'';
  if($type==='fixed'&&!preg_match('/^[0-9]{1,5}$/D',$foreignArea))throw new RuntimeException('Indica el código regional del teléfono fijo.');
  $result='+'.$country.$foreignArea.$n;if(strlen($result)<9||strlen($result)>16)throw new RuntimeException('Revisa el teléfono y sus indicativos.');return $result;
 }
}
