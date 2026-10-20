<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessServiceSections.php';
final class PremiumBusinessProfile
{
 public static function alias(string $value):string{
  $value=strtolower(trim($value));if($value!==''&&(strlen($value)<3||strlen($value)>70||!preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D',$value)))throw new RuntimeException('La URL admite entre 3 y 70 letras minúsculas, números y guiones; comienza con una letra.');return $value;
 }
 public static function currentAlias(PDO $pdo,int $id):string{
  try{$q=$pdo->prepare('SELECT alias FROM business_page_aliases WHERE business_id=? AND is_current=1');$q->execute([$id]);return (string)($q->fetchColumn()?:'');}catch(PDOException $e){if(($e->errorInfo[1]??0)===1146||str_contains($e->getMessage(),'no such table'))return '';throw $e;}
 }
 public static function resolve(PDO $pdo,string $alias):?string{
  try{if(self::alias($alias)!==$alias||$alias==='')return null;}catch(RuntimeException){return null;}
  try{$q=$pdo->prepare('SELECT b.public_id FROM business_page_aliases a JOIN businesses b ON b.id=a.business_id WHERE a.alias=? AND EXISTS(SELECT 1 FROM business_subscriptions s WHERE s.business_id=b.id AND s.plan_code="page_premium" AND s.status="active" AND s.starts_at<=CURRENT_TIMESTAMP AND s.ends_at>CURRENT_TIMESTAMP)');$q->execute([$alias]);return $q->fetchColumn()?:null;}catch(PDOException $e){if(($e->errorInfo[1]??0)===1146||str_contains($e->getMessage(),'no such table'))return null;throw $e;}
 }
 public static function save(PDO $pdo,int $account,int $business,array $sections,string $alias):void{
  $alias=self::alias($alias);BusinessServiceSections::validate($sections);$pdo->beginTransaction();
  try{
   $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
   $q=$pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$q->execute([$business]);if(!$q->fetchColumn())throw new RuntimeException('No puedes editar este negocio.');
   $q=$pdo->prepare('SELECT m.business_id FROM business_account_memberships m JOIN accounts a ON a.id=m.account_id WHERE m.business_id=? AND m.account_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active"');$q->execute([$business,$account]);if(!$q->fetchColumn())throw new RuntimeException('No puedes editar este negocio.');
   $q=$pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=? AND plan_code="page_premium" AND status="active" AND starts_at<=CURRENT_TIMESTAMP AND ends_at>CURRENT_TIMESTAMP');$q->execute([$business]);if(!$q->fetchColumn())throw new RuntimeException('La edición ampliada requiere Premium vigente.');
   BusinessServiceSections::save($pdo,$business,$sections);BusinessServiceSections::publication($pdo,$business);
   $pdo->prepare('UPDATE business_page_aliases SET is_current=0 WHERE business_id=?')->execute([$business]);
   if($alias!==''){$q=$pdo->prepare('SELECT business_id FROM business_page_aliases WHERE alias=?');$q->execute([$alias]);$owner=$q->fetchColumn();if($owner!==false&&(int)$owner!==$business)throw new RuntimeException('Esta URL ya pertenece a otro negocio. Elige otra.');
    if($owner===false){try{$pdo->prepare('INSERT INTO business_page_aliases(alias,business_id,is_current) VALUES(?,?,1)')->execute([$alias,$business]);}catch(PDOException $e){if(in_array($e->getCode(),['23000','23505'],true))throw new RuntimeException('Esta URL acaba de ser utilizada. Elige otra.');throw $e;}}
    else $pdo->prepare('UPDATE business_page_aliases SET is_current=1 WHERE alias=? AND business_id=?')->execute([$alias,$business]);
   }
   $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
