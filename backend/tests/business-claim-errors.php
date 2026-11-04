<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessClaimService.php';
require __DIR__.'/../app/BusinessClaimPresentation.php';
$throttleAllowed=true;
function throttle(...$args):bool{global $throttleAllowed;return $throttleAllowed;}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY,account_type TEXT,status TEXT,display_name TEXT,email TEXT)');
$db->exec('CREATE TABLE businesses(id INTEGER PRIMARY KEY,claim_status TEXT,publication_status TEXT,updated_at TEXT)');
$db->exec('CREATE TABLE business_claim_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,business_id INTEGER,account_id INTEGER,claimant_name TEXT,email TEXT,phone TEXT,relationship_code TEXT,message TEXT,consent_version TEXT,status TEXT)');
$db->exec("INSERT INTO accounts VALUES(1,'business','active','Synthetic Owner','test@example.invalid'); INSERT INTO businesses VALUES(1,'NO_RECLAMADO','published',NULL)");
$service=new BusinessClaimService($db);$valid=['business_id'=>1,'relationship_code'=>'owner','consent'=>'1'];
$catalog=json_decode(file_get_contents(__DIR__.'/../app/presentation/business-claim.json'),true,64,JSON_THROW_ON_ERROR);
foreach(['es','en','pt-br'] as $locale)foreach(['account_required','account_inactive','name_required','email_invalid','phone_invalid','contact_required','relationship_required','message_too_long','consent_required','rate_limited','business_unavailable','unavailable','storage'] as $key)
 if(empty($catalog[$locale]['error_'.$key]))throw new RuntimeException('Missing claim error translation: '.$locale.'/'.$key);
function expectClaimError(BusinessClaimService $service,array $input,string $reason,string $spanish):void{
 try{$service->submit($input);throw new RuntimeException('Expected failure was not raised.');}
 catch(BusinessClaimException $error){
  if($error->reason!==$reason||$error->getMessage()!==$spanish)throw new RuntimeException('Claim failure contract changed: '.$reason);
  if(BusinessClaimPresentation::errorMessage($error,'es')!==$spanish)throw new RuntimeException('Spanish error changed.');
  foreach(['en','pt-br'] as $locale){$translated=BusinessClaimPresentation::errorMessage($error,$locale);if($translated===''||$translated===$spanish)throw new RuntimeException('Error not localized: '.$locale.'/'.$reason);}
 }
}
$_SESSION=[];expectClaimError($service,$valid,'account_required','Crea una cuenta empresarial o inicia sesión para solicitar la reclamación.');
$_SESSION=['business_account_id'=>99];expectClaimError($service,$valid,'account_inactive','Inicia sesión con una cuenta empresarial activa.');
$_SESSION=['business_account_id'=>1];
expectClaimError($service,['business_id'=>0]+$valid,'name_required','Completa tu nombre para continuar.');
$db->exec("UPDATE accounts SET email='invalid' WHERE id=1");expectClaimError($service,$valid,'email_invalid','Revisa el correo electrónico.');
$db->exec("UPDATE accounts SET email='test@example.invalid' WHERE id=1");
expectClaimError($service,['phone_number'=>'abc']+$valid,'phone_invalid','El teléfono solo admite números y separadores.');
expectClaimError($service,['phone'=>'invalid']+$valid,'phone_invalid','Revisa el número de teléfono.');
$db->exec("UPDATE accounts SET email='' WHERE id=1");expectClaimError($service,$valid,'contact_required','Indica un correo o un teléfono para poder contactarte.');
$db->exec("UPDATE accounts SET email='test@example.invalid' WHERE id=1");
expectClaimError($service,['relationship_code'=>'wrong']+$valid,'relationship_required','Selecciona tu relación con el negocio.');
expectClaimError($service,['message'=>str_repeat('a',1001)]+$valid,'message_too_long','El mensaje supera los 1.000 caracteres.');
expectClaimError($service,['consent'=>'0']+$valid,'consent_required','Autoriza el uso de estos datos para revisar la solicitud.');
$throttleAllowed=false;expectClaimError($service,$valid,'rate_limited','Se alcanzó el límite de solicitudes para este negocio. Inténtalo más tarde.');$throttleAllowed=true;
$db->exec("UPDATE businesses SET claim_status='EN_TRAMITE' WHERE id=1");expectClaimError($service,$valid,'business_unavailable','Este negocio ya no está disponible para reclamar. Actualiza la búsqueda.');
$db->exec("UPDATE businesses SET claim_status='NO_RECLAMADO' WHERE id=1");
if($service->submit($valid)!==1||(int)$db->query('SELECT COUNT(*) FROM business_claim_requests')->fetchColumn()!==1)throw new RuntimeException('Valid claim no longer succeeds.');
$pdoError=new PDOException('private database detail');foreach(['es','en','pt-br'] as $locale){$text=BusinessClaimPresentation::errorMessage($pdoError,$locale);if(str_contains($text,'private database detail'))throw new RuntimeException('Private database error leaked.');}
$unavailable=new BusinessClaimException('unavailable','La recepción de solicitudes no está habilitada en este momento.');
foreach(['es','en','pt-br'] as $locale)if(BusinessClaimPresentation::errorMessage($unavailable,$locale)!==$catalog[$locale]['error_unavailable'])throw new RuntimeException('Unavailable request message not localized.');
echo "OK: stable claim error reasons and translations, preserved Spanish, and valid submission.\n";
