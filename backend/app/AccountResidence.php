<?php
declare(strict_types=1);
/** Private, self-reported residence, independent of businesses and IP geolocation. */
final class AccountResidence
{
 public static function catalog():array{return json_decode(file_get_contents(__DIR__.'/content/account-geography.json'),true,512,JSON_THROW_ON_ERROR);}
 public static function capture(array $input):array{
  $data=self::catalog();$values=[];
  foreach(['country_code'=>2,'region_code'=>20,'region_name'=>120,'city_code'=>20,'city_name'=>120] as $key=>$max){$v=$input['residence_'.$key]??'';if(!is_string($v)||strlen($v)>$max||preg_match('/[\x00-\x1f\x7f]/',$v))throw new RuntimeException('Revisa tu lugar de residencia.');$values[$key]=trim($v);}
  $c=$values['country_code'];if(!isset($data['countries'][$c]))throw new RuntimeException('Selecciona tu país de residencia.');
  if(isset($data['regions'][$c])&&($c==='CO'||$values['region_code']!=='')){
   if(!isset($data['regions'][$c][$values['region_code']]))throw new RuntimeException('Selecciona un departamento o estado del país elegido.');
   $values['region_name']=$data['regions'][$c][$values['region_code']];
  }else{if($values['region_name']==='')throw new RuntimeException('Escribe tu región, estado o provincia.');$values['region_code']='';}
  if($c==='CO'){
   $matches=array_values(array_filter($data['municipalities'],fn($m)=>$m['code']===$values['city_code']&&$m['department_code']===$values['region_code']));
   if(!$matches)throw new RuntimeException('Selecciona un municipio del departamento elegido.');$values['city_name']=$matches[0]['name'];
  }else{if($values['city_name']==='')throw new RuntimeException('Escribe tu ciudad o municipio.');$values['city_code']='';}
  return $values;
 }
 public static function get(PDO $pdo,int $id):array{$q=$pdo->prepare('SELECT country_code,region_code,region_name,city_code,city_name FROM account_residences WHERE account_id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:[];}
 public static function save(PDO $pdo,int $id,array $values):void{
  $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'INSERT INTO account_residences(account_id,country_code,region_code,region_name,city_code,city_name) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),region_code=VALUES(region_code),region_name=VALUES(region_name),city_code=VALUES(city_code),city_name=VALUES(city_name),updated_at=CURRENT_TIMESTAMP':'INSERT INTO account_residences(account_id,country_code,region_code,region_name,city_code,city_name) VALUES(?,?,?,?,?,?) ON CONFLICT(account_id) DO UPDATE SET country_code=excluded.country_code,region_code=excluded.region_code,region_name=excluded.region_name,city_code=excluded.city_code,city_name=excluded.city_name,updated_at=CURRENT_TIMESTAMP';
  $pdo->prepare($sql)->execute([$id,$values['country_code'],$values['region_code'],$values['region_name'],$values['city_code'],$values['city_name']]);
 }
}
