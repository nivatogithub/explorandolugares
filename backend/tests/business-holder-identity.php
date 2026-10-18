<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessHolderIdentity.php';
function identityCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function invalidIdentity(array $input):void{try{BusinessHolderIdentity::capture($input);}catch(RuntimeException){return;}throw new RuntimeException('Invalid identity accepted');}
$rut=['holder_kind'=>'legal_entity','holder_name'=>'Titular de prueba','holder_number'=>'800.197.268-4'];
$id=BusinessHolderIdentity::capture($rut);identityCheck($id['number']==='800197268-4'&&$id['document_type']==='NIT'&&!$id['officially_verified']&&!$id['ownership_verified'],'Format must not imply official verification');
invalidIdentity(array_replace($rut,['holder_number'=>'800197268-3']));invalidIdentity(array_replace($rut,['holder_number'=>'800197268']));invalidIdentity(array_replace($rut,['holder_number'=>'000000000-0']));invalidIdentity([]);
$person=['holder_kind'=>'natural_document','holder_name'=>'Persona de prueba','holder_document_type'=>'CC','holder_number'=>'123456789'];
$id=BusinessHolderIdentity::capture($person);identityCheck($id['document_type']==='CC'&&!$id['officially_verified'],'Document must not become RUT');
invalidIdentity(array_replace($person,['holder_number'=>'inventado']));invalidIdentity(array_replace($person,['holder_document_type'=>'FOREIGN_TAX']));
$foreign=BusinessHolderIdentity::capture(['holder_kind'=>'foreign','holder_foreign_kind'=>'person','holder_country_code'=>'US','holder_name'=>'Foreign holder','holder_document_type'=>'PASSPORT','holder_number'=>'AB123456']);identityCheck($foreign['number']==='AB123456','Foreign holder supported');
invalidIdentity(array_replace($person,['holder_document_type'=>'CE']));
invalidIdentity(['holder_kind'=>'foreign','holder_name'=>'Prueba','holder_document_type'=>'PASSPORT','holder_number'=>'AB123456']);
$company=array_replace($rut,['holder_kind'=>'foreign','holder_foreign_kind'=>'company','holder_country_code'=>'US','holder_document_type'=>'NIT']);
identityCheck(BusinessHolderIdentity::capture($company)['document_type']==='NIT','Foreign holder with Colombian RUT');
invalidIdentity(array_replace($company,['holder_document_type'=>'PASSPORT']));
identityCheck(BusinessHolderIdentity::capture($rut)===BusinessHolderIdentity::capture($rut),'Same holder can identify multiple establishments');
echo "OK: NIT check digit, natural person without RUT, foreign holder, missing/invalid values; no false official or ownership verification.\n";
