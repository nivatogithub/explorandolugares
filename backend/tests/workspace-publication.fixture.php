<?php
declare(strict_types=1);
// Isolated registration fixture only. No hosting credentials, external mail or real accounts.
if(PHP_SAPI!=='cli'||getenv('EL_WORKSPACE_TEST')!=='1')exit(1);
$database=realpath($argv[1]??'');$local=realpath(dirname(__DIR__,2).'/.local');
if(!$database||!$local||!str_starts_with($database,$local.DIRECTORY_SEPARATOR)||!is_file(dirname($database).'/registration-fixture.marker'))throw new RuntimeException('Isolated fixture required.');
require dirname(__DIR__).'/app/BusinessEditorialService.php';
require_once dirname(__DIR__).'/app/BusinessOwnershipEvidence.php';
$p=new PDO('sqlite:'.$database,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$business=(int)($argv[2]??0);
$ownership=new BusinessOwnershipEvidence($p,dirname($database).'/business-representation-evidence');
foreach($ownership->documents($business) as $document)$ownership->verify($business,(int)$document['id'],'fixture-admin');
$editorial=new BusinessEditorialService($p,dirname($database).'/business-image-references');$v=$editorial->latest($business);
if(!$v||$v['plan_code']!=='free')throw new RuntimeException('Only the Free fixture can be prepared.');
$payload=json_decode($v['payload_json'],true,32,JSON_THROW_ON_ERROR);
// The administrator supplies the reviewed physical point before prepublication,
// even though a Free card does not expose a public contact/map channel.
$payload['latitude']=6.2442;$payload['longitude']=-75.5812;
foreach(['en','pt-BR'] as $lang)$payload['translations'][$lang]=$payload['translations']['es'];
$p->prepare('UPDATE business_editorial_versions SET payload_json=?,payload_hash=?,status="in_editorial" WHERE id=?')->execute([json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),BusinessEditorialService::hash($payload),$v['id']]);
$v=$editorial->latest($business);$editorial->transition((int)$v['id'],'prepare',0,'fixture-admin','',$v['payload_hash'],['translations'=>'1','images'=>'1','layout'=>'1']);
echo json_encode(['id'=>(int)$v['id'],'hash'=>$v['payload_hash']],JSON_THROW_ON_ERROR);
