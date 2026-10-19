<?php
declare(strict_types=1);
/** Contact geography is independent of published travel guides. */
final class LeadGeography {
 public static function resolve(string $value,string $department=''):?array {
  static $geo=null,$options=null;$geo??=json_decode(file_get_contents(__DIR__.'/content/account-geography.json'),true,512,JSON_THROW_ON_ERROR);$options??=json_decode(file_get_contents(__DIR__.'/contact-options.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($geo['municipalities'] as $row)if($row['code']===$value){
   if($department!==''&&$department!==$row['department_code'])return null;
   $region=$geo['regions']['CO'][$row['department_code']]??null;if(!$region)return null;
   $name=mb_convert_case($row['name'],MB_CASE_TITLE,'UTF-8');$region=mb_convert_case($region,MB_CASE_TITLE,'UTF-8');if($row['code']==='11001')$name='Bogotá D. C.';
   return ['label'=>$name.' · '.$region,'department_code'=>$row['department_code'],'prefix'=>$options['departmentPrefixes'][$row['department_code']]??null];
  }
  // Existing clients remain valid during rollout; new forms submit unambiguous DANE codes.
  if($department===''&&in_array($value,$options['municipalities'],true))return ['label'=>$value,'department_code'=>null,'prefix'=>$options['landlinePrefixes'][$value]??null];
  return null;
 }
}
