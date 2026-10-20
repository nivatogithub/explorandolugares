<?php
declare(strict_types=1);
/** Optional Premium service descriptions. These are not place experiences or operator adverts. */
final class BusinessServiceSections
{
 public const TYPES=['lodging','restaurant','other'];
 public const LIMIT=1200;
 public static function labels(string $locale):array{return match($locale){'en'=>['lodging'=>'Accommodation','restaurant'=>'Restaurant','other'=>'Other services'],'pt-BR'=>['lodging'=>'Hospedagem','restaurant'=>'Restaurante','other'=>'Outros serviços'],default=>['lodging'=>'Alojamiento','restaurant'=>'Restaurante','other'=>'Otros servicios']};}
 public static function validate(mixed $input):array{
  if(!is_array($input))throw new RuntimeException('Revisa las secciones de servicios.');$result=[];
  foreach($input as $locale=>$sections){if(!in_array($locale,['es','en','pt-BR'],true)||!is_array($sections))throw new RuntimeException('Idioma no válido para las secciones.');
   foreach($sections as $type=>$value){if(!in_array($type,self::TYPES,true)||!is_string($value))throw new RuntimeException('Sección de servicios no válida.');$value=trim($value);$count=preg_match_all('/./us',$value);if($count===false||$count>self::LIMIT)throw new RuntimeException('Cada sección admite hasta 1.200 caracteres.');if($value!=='')$result[$locale][$type]=$value;}
  }return $result;
 }
 public static function read(PDO $pdo,int $id):array{
  try{$q=$pdo->prepare('SELECT locale,section_code,description FROM business_service_sections WHERE business_id=?');$q->execute([$id]);}
  catch(PDOException $e){if(($e->errorInfo[1]??0)===1146||str_contains($e->getMessage(),'no such table'))return [];throw $e;}
  $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['locale']][$row['section_code']]=$row['description'];return $out;
 }
 /** Caller owns the transaction. Empty sections are hidden; omitted locales are retained. */
 public static function save(PDO $pdo,int $id,array $input):void{
  $clean=self::validate($input);
  foreach(array_keys($input) as $locale){$pdo->prepare('DELETE FROM business_service_sections WHERE business_id=? AND locale=?')->execute([$id,$locale]);$q=$pdo->prepare('INSERT INTO business_service_sections(business_id,locale,section_code,description) VALUES(?,?,?,?)');foreach($clean[$locale]??[] as $code=>$text)$q->execute([$id,$locale,$code,$text]);}
 }
 public static function publication(PDO $pdo,int $id):void{
  $rows=self::read($pdo,$id);self::validate($rows);
  foreach($rows['es']??[] as $type=>$text)foreach(['en','pt-BR'] as $locale)if(trim($rows[$locale][$type]??'')==='')throw new RuntimeException('Traduce las secciones Premium a inglés y portugués antes de publicar.');
 }
}
