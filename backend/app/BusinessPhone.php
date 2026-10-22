<?php
declare(strict_types=1);
/** Colombian business phones, stored in international format; no ownership verification implied. */
final class BusinessPhone {
 public const AREAS=['601'=>'Bogotá y Cundinamarca','602'=>'Cauca, Nariño y Valle del Cauca','604'=>'Antioquia, Córdoba y Chocó','605'=>'Región Caribe','606'=>'Caldas, Quindío y Risaralda','607'=>'Santander, Norte de Santander y Arauca','608'=>'Otras regiones'];
 public static function normalize(string $value,string $type='mobile',string $area=''):string{
  if(trim($value)==='')return '';
  if(!preg_match('/^[+0-9 ().-]+$/D',$value))throw new RuntimeException('El teléfono solo admite números y separadores.');
  $n=preg_replace('/\D/','',$value);if(strlen($n)===12&&str_starts_with($n,'57'))$n=substr($n,2);
  if($type==='fixed'){
   if(strlen($n)===7){if(!isset(self::AREAS[$area]))throw new RuntimeException('Selecciona el indicativo del teléfono fijo.');$n=$area.$n;}
   if(!preg_match('/^60[1245678][0-9]{7}$/D',$n))throw new RuntimeException('El fijo requiere su indicativo 60X y siete números.');
  }elseif($type==='mobile'){
   if(!preg_match('/^3[0-9]{9}$/D',$n))throw new RuntimeException('El celular colombiano requiere diez números y empieza por 3.');
  }else throw new RuntimeException('Selecciona celular o teléfono fijo.');
  return '+57'.$n;
 }
 public static function capture(array $input,array $channels):array{
  if(!isset($input['business_phone'])||!is_array($input['business_phone']))return $channels;
  $p=$input['business_phone'];$type=(string)($p['type']??'mobile');
  $phone=self::normalize((string)($p['number']??''),$type,(string)($p['area']??''));
  $same=($p['same_whatsapp']??'')==='1';
  if($same&&$phone==='')throw new RuntimeException('Completa el teléfono antes de usarlo también como WhatsApp.');
  $whatsapp=$same?$phone:self::normalize((string)($p['whatsapp']??''),'mobile');
  $channels['phone']='';$channels['phone_fixed']='';$channels[$type==='fixed'?'phone_fixed':'phone']=$phone;$channels['whatsapp']=$whatsapp;return $channels;
 }
}
