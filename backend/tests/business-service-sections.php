<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessServiceSections.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON;CREATE TABLE businesses(id INTEGER PRIMARY KEY);INSERT INTO businesses VALUES(1)');
function section_check(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
section_check(BusinessServiceSections::read($p,1)===[],'Old schema compatibility');
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/045-business-service-sections.sqlite.sql');$p->exec($sql);$p->exec($sql);
$text=['es'=>['lodging'=>str_repeat('á',1200),'restaurant'=>'Cocina local','other'=>''],'en'=>['lodging'=>'Rooms','restaurant'=>'Local cuisine'],'pt-BR'=>['lodging'=>'Quartos','restaurant'=>'Cozinha local']];
$p->beginTransaction();BusinessServiceSections::save($p,1,$text);$p->commit();BusinessServiceSections::publication($p,1);
section_check(BusinessServiceSections::read($p,1)['es']['lodging']===$text['es']['lodging'],'Unicode limit and persistence');
section_check(!isset(BusinessServiceSections::read($p,1)['es']['other']),'Empty sections omitted');
foreach([['es'=>['lodging'=>str_repeat('á',1201)]],['es'=>['tours'=>'Not a place experience']],['fr'=>['other'=>'Invalid locale']]] as $bad){$rejected=false;try{BusinessServiceSections::validate($bad);}catch(RuntimeException){$rejected=true;}section_check($rejected,'Invalid section accepted');}
$p->beginTransaction();BusinessServiceSections::save($p,1,['es'=>['restaurant'=>'Changed']]);$p->rollBack();section_check(BusinessServiceSections::read($p,1)['es']['restaurant']==='Cocina local','Caller transaction rollback');
BusinessServiceSections::save($p,1,['en'=>[]]);$rejected=false;try{BusinessServiceSections::publication($p,1);}catch(RuntimeException){$rejected=true;}section_check($rejected,'Missing translation accepted');
$p->exec('DELETE FROM businesses WHERE id=1');section_check(BusinessServiceSections::read($p,1)===[],'Cascade cleanup');
echo "OK: optional Premium sections, 1200 Unicode characters, persistence, empty omission, invalid types/locales, rollback, translations, idempotent migration and cascade.\n";
