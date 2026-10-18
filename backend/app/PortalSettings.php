<?php
declare(strict_types=1);
/** Editable non-secret privacy policy, private per environment; infrastructure stays in config.php. */
final class PortalSettings
{
 public static function load(string $directory):array{
  if(is_link($directory))throw new RuntimeException('Carpeta de configuración no válida.');$path=$directory.'/portal-settings.json';
  if(!is_file($path))return [];
  if(is_link($path))throw new RuntimeException('Archivo de configuración no válido.');
  $data=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
  if(!is_array($data)||!is_array($data['privacy']??null))throw new RuntimeException('Configuración de privacidad no válida.');
  return ['privacy'=>self::validate($data['privacy'])];
 }
 public static function validate(array $input):array{
  $result=[];foreach(['operator'=>180,'contact'=>254,'retention'=>2000,'retention_en'=>2000,'retention_pt'=>2000] as $field=>$max){$value=$input[$field]??'';if(!is_string($value)||strlen($value)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new RuntimeException('Revisa los campos de privacidad.');$result[$field]=trim($value);}
  if($result['operator']===''||$result['retention']===''||!filter_var($result['contact'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Completa el responsable, un correo válido y el criterio de conservación en español.');
  return $result;
 }
 public static function retention(array $privacy,string $locale):string{
  $locale=strtolower(str_replace('_','-',$locale));$field=match($locale){'en'=>'retention_en','pt','pt-br'=>'retention_pt',default=>'retention'};return is_string($privacy[$field]??null)?$privacy[$field]:'';
 }
 public static function save(string $directory,array $input,int $actor):void{
  $data=['privacy'=>self::validate($input),'updated_at'=>gmdate('c'),'updated_by_account_id'=>$actor];
  if(is_link($directory)||is_link($directory.'/portal-settings.json'))throw new RuntimeException('Ruta de configuración no válida.');
  if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('No se pudo preparar la configuración privada.');
  $temp=tempnam($directory,'.portal-');if(!$temp)throw new RuntimeException('No se pudo guardar la configuración.');
  try{chmod($temp,0600);$json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);if(file_put_contents($temp,$json,LOCK_EX)!==strlen($json)||!rename($temp,$directory.'/portal-settings.json'))throw new RuntimeException('No se pudo guardar la configuración.');}finally{if(is_file($temp))unlink($temp);}
 }
}
