<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessAdminService.php';
require_once __DIR__.'/BusinessSubscriptionAdminService.php';
require_once __DIR__.'/BusinessContactVisibility.php';
require_once __DIR__.'/PremiumBusinessProfile.php';
require_once __DIR__.'/BusinessShowcase.php';
/** Immutable payloads and explicit approval of the exact version. Never sends mail or generates images on save. */
final class BusinessEditorialService
{
 public const LABELS=['draft'=>'Borrador privado','in_editorial'=>'En preparación editorial','awaiting_owner'=>'Pendiente de tu aprobación','changes_requested'=>'Correcciones solicitadas','rejected'=>'No aprobado para publicación','owner_approved'=>'Listo para publicación final','published'=>'Publicado'];
 public function __construct(private PDO $pdo,private string $storage){}
 public function ready():bool{try{$this->pdo->query('SELECT id FROM business_editorial_versions LIMIT 1');return true;}catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return false;throw $e;}}
 private function admin():BusinessAdminService{return new BusinessAdminService($this->pdo,$this->storage);}
 private function lock(int $business):void{$q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$business]);if(!$q->fetchColumn())throw new RuntimeException('El negocio no existe.');}
 public function authorize(int $account,int $business):void{$q=$this->pdo->prepare('SELECT 1 FROM business_account_memberships m JOIN accounts a ON a.id=m.account_id WHERE m.account_id=? AND m.business_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active"');$q->execute([$account,$business]);if(!$q->fetchColumn())throw new RuntimeException('Este negocio no está asociado a tu cuenta.');}
 public function latest(int $business):?array{$q=$this->pdo->prepare('SELECT * FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC LIMIT 1'.($this->pdo->inTransaction()&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$business]);$v=$q->fetch(PDO::FETCH_ASSOC);return $v?:null;}
 public function version(int $id):array{$q=$this->pdo->prepare('SELECT * FROM business_editorial_versions WHERE id=?'.($this->pdo->inTransaction()&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:throw new RuntimeException('La versión no existe.');}
 public function live(int $business):array{
  $r=$this->admin()->find($business)??throw new RuntimeException('El negocio no existe.');
  $r['showcase']=BusinessShowcase::published($this->pdo,$business);
  return array_intersect_key($r,array_flip(['id','destination_id','category_code','category_codes','address_text','location_reference','latitude','longitude','location_source','location_map_url','location_confirmed','opening_hours','price_range','capacity_count','menu_url','reservations_url','image_assistance_requested','translations','contacts','service_sections','media','showcase']));
 }
 public static function hash(array $payload):string{$sort=function(array $value)use(&$sort):array{foreach($value as &$item)if(is_array($item))$item=$sort($item);unset($item);if(!array_is_list($value))ksort($value);return $value;};return hash('sha256',json_encode($sort($payload),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
 public function options(int $business):array{
  $q=$this->pdo->prepare('SELECT p.* FROM subscription_plans p JOIN business_subscriptions s ON s.plan_code=p.code WHERE s.business_id=? ORDER BY s.id DESC LIMIT 1');$q->execute([$business]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p)throw new RuntimeException('Primero selecciona una suscripción.');
  return $p;
 }
 public function payload(?array $v,int $business):array{return $v?json_decode($v['payload_json'],true,32,JSON_THROW_ON_ERROR):$this->live($business);}
 /** Normalize by the real subscription. Hidden/private CRM channels cannot enter a public revision. */
 private function clean(int $business,array $input,array $previous,bool $editor):array{
  $plan=$this->options($business);$code=$plan['code'];$p=$previous;
  $p['id']=$business;$p['plan_code']=$code;
  $p['category_code']=(string)($input['category_code']??$p['category_code']);$category=$this->pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');$category->execute([$p['category_code']]);if(!$category->fetchColumn())throw new RuntimeException('Selecciona una categoría activa.');
  $p['category_codes']=BusinessCategories::validate($this->pdo,(isset($input['category_codes_present'])?($input['category_codes']??[]):($input['category_codes']??($p['category_codes']??[]))),$p['category_code']);
  $locales=$editor?['es','en','pt-BR']:['es'];
  foreach($locales as $locale){foreach(['display_name'=>180,'summary'=>(int)$plan['max_summary_chars'],'description'=>(int)$plan['max_description_chars'],'brand_story'=>(int)$plan['max_brand_story_chars'],'featured_services'=>(int)$plan['max_featured_services_chars']] as $key=>$limit){
   if($key!=='display_name'&&$limit===0){$p['translations'][$locale][$key]='';continue;}
   $value=trim((string)($input['translations'][$locale][$key]??$p['translations'][$locale][$key]??''));if(preg_match_all('/./us',$value)>$limit||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value))throw new RuntimeException('Revisa el tamaño y contenido de '.$key.'.');$p['translations'][$locale][$key]=$value;
  }}
  if(trim($p['translations']['es']['display_name'])==='')throw new RuntimeException('Completa el nombre del negocio.');
  if($code!=='free'){
   foreach(['address_text'=>300,'location_reference'=>500,'location_map_url'=>1000,'opening_hours'=>2000,'price_range'=>200,'menu_url'=>1000,'reservations_url'=>1000] as $key=>$max){$v=trim((string)($input[$key]??$p[$key]??''));if(strlen($v)>$max)throw new RuntimeException('Revisa '.$key.'.');if(str_ends_with($key,'url')&&$v!==''&&(!filter_var($v,FILTER_VALIDATE_URL)||!in_array(parse_url($v,PHP_URL_SCHEME),['http','https'],true)))throw new RuntimeException('Usa un enlace http o https válido.');$p[$key]=$v;}
   foreach(['latitude'=>90,'longitude'=>180] as $key=>$max){$v=$input[$key]??$p[$key]??null;if($v==='')$v=null;if($v!==null&&(!is_numeric($v)||abs((float)$v)>$max))throw new RuntimeException('Revisa las coordenadas.');$p[$key]=$v===null?null:(float)$v;}
   if(($p['latitude']===null)!==($p['longitude']===null))throw new RuntimeException('Completa ambas coordenadas.');
   if(isset($input['location_address_kind']))$p['location_source']=$input['location_address_kind']==='rural'?'map_rural':'map';
  $p['location_confirmed']=array_key_exists('location_confirmed',$input)?(!empty($input['location_confirmed'])?1:0):(int)($p['location_confirmed']??0);
   require_once __DIR__.'/BusinessPhone.php';$input['contacts']=BusinessPhone::capture($input,(array)($input['contacts']??[]));
   foreach(BusinessContactVisibility::channels($code) as $channel){if(!array_key_exists($channel,$input['contacts']??[]))continue;$value=trim((string)$input['contacts'][$channel]);if($value!==''&&!$this->contactValid($channel,$value))throw new RuntimeException('Revisa el canal '.$channel.'.');$p['contacts'][$channel]=['channel'=>$channel,'contact_value'=>$value,'is_public'=>1];}
  }
  if(in_array($code,['page','page_premium'],true)&&isset($input['hours'])){$location=$this->admin()->submittedLocation(array_replace($p,['hours'=>$input['hours']]));$p['opening_hours']=$location['opening_hours'];}
  if(in_array($code,['page','page_premium'],true)&&array_key_exists('capacity_count',$input)){$capacity=trim((string)$input['capacity_count']);if($capacity!==''&&(filter_var($capacity,FILTER_VALIDATE_INT)===false||(int)$capacity<0||(int)$capacity>65535))throw new RuntimeException('Revisa la capacidad aproximada.');$p['capacity_count']=$capacity===''?null:(int)$capacity;}
  if($code==='page_premium'&&array_key_exists('page_alias',$input)){$p['page_alias']=PremiumBusinessProfile::alias((string)$input['page_alias']);}
  if($code==='page_premium'&&isset($input['service_sections']))$p['service_sections']=BusinessServiceSections::validate($input['service_sections']);
  if($editor&&isset($input['media'])){
   $media=['logo'=>max(0,(int)($input['media']['logo']??0)),'cover'=>max(0,(int)($input['media']['cover']??0)),'gallery'=>array_values(array_filter(array_map('intval',(array)($input['media']['gallery']??[]))))];
   if(count($media['gallery'])>(int)$plan['max_gallery_photos'])throw new RuntimeException('La galería supera la suscripción.');
   if(!in_array($code,['page','page_premium'],true)&&($media['cover']||$media['gallery']))throw new RuntimeException('Esta suscripción no incluye imágenes públicas.');
   foreach(array_filter([$media['logo'],$media['cover'],...$media['gallery']]) as $id){$q=$this->pdo->prepare('SELECT 1 FROM media_assets WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('La imagen preparada no existe.');}
   $ids=array_values(array_filter([$media['logo'],$media['cover'],...$media['gallery']]));if(count($ids)!==count(array_unique($ids)))throw new RuntimeException('Usa una imagen distinta para cada posición.');$p['media']=$media;
  }
  if($code==='page_premium'&&isset($input['showcase'])){$data=$input['showcase'];$data['category_codes']=[$p['category_code'],...$p['category_codes']];$p['showcase']=BusinessShowcase::clean($this->pdo,$business,$data,$p['showcase']??[],$editor);}
  return $p;
 }
 private function contactValid(string $channel,string $value):bool{if(strlen($value)>500)return false;return match($channel){'email'=>(bool)filter_var($value,FILTER_VALIDATE_EMAIL),'phone','phone_fixed','whatsapp'=>preg_match('/^\+?[0-9() .-]{7,24}$/D',$value)===1,default=>(bool)filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true)};}
 /** Every edit creates a new version; previous approvals cannot approve changed content. */
 public function save(int $account,int $business,int $expected,array $input,bool $imageConsent=false,bool $editor=false,string $actor='',mixed $uploads=null):int{
  if($editor&&trim($actor)==='')throw new RuntimeException('La preparación editorial requiere un administrador.');$storedFiles=[];$this->pdo->beginTransaction();try{$this->lock($business);if(!$editor)$this->authorize($account,$business);$latest=$this->latest($business);if((int)($latest['id']??0)!==$expected)throw new RuntimeException('Otra persona cambió esta versión. Recarga antes de guardar.');
   if($editor&&(!$latest||!in_array($latest['status'],['in_editorial','changes_requested'],true)))throw new RuntimeException('Primero debe enviarse el contenido a preparación.');
   $payload=$this->clean($business,$input,$this->payload($latest,$business),$editor);$plan=$this->options($business)['code'];$status=$editor?'in_editorial':'draft';
   $q=$this->pdo->prepare('INSERT INTO business_editorial_versions(business_id,account_id,parent_id,status,plan_code,base_hash,payload_json,payload_hash,image_consent,note) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$business,$editor?$latest['account_id']:$account,$latest['id']??null,$status,$plan,$latest&&$latest['status']!=='published'?$latest['base_hash']:self::hash($this->live($business)),json_encode($payload,JSON_THROW_ON_ERROR),self::hash($payload),$editor?(int)$latest['image_consent']:(int)$imageConsent,'']);$id=(int)$this->pdo->lastInsertId();
   if(!$latest)$this->pdo->prepare('INSERT INTO business_editorial_images(version_id,storage_name,original_name,mime_type,file_size,width,height) SELECT ?,storage_name,original_name,mime_type,file_size,width,height FROM business_image_references WHERE business_id=?')->execute([$id,$business]);
   if($latest)$this->pdo->prepare('INSERT INTO business_editorial_images(version_id,storage_name,original_name,mime_type,file_size,width,height) SELECT ?,storage_name,original_name,mime_type,file_size,width,height FROM business_editorial_images WHERE version_id=?')->execute([$id,$latest['id']]);
   if(is_array($uploads)&&!array_filter((array)($uploads['error']??[]),static fn($error)=>(int)$error!==UPLOAD_ERR_NO_FILE))$uploads=null;
   $remove=$input['remove_reference_ids']??[];if(!is_array($remove)||count($remove)>20)throw new RuntimeException('Revisa las referencias que quieres retirar.');
   foreach($remove as $reference){$q=$this->pdo->prepare('SELECT storage_name FROM business_editorial_images WHERE id=? AND version_id=?');$q->execute([(int)$reference,(int)($latest['id']??0)]);$name=$q->fetchColumn();if(!$name)throw new RuntimeException('Esa referencia no pertenece a la versión anterior.');$this->pdo->prepare('DELETE FROM business_editorial_images WHERE version_id=? AND storage_name=?')->execute([$id,$name]);}
   // A replacement retires the previous source only from the new version, keeping history intact.
   foreach((array)($uploads['error']??[]) as $slot=>$uploadError){if((int)$uploadError!==UPLOAD_ERR_OK||!in_array((string)$slot,['cover','product_0','product_1','product_2','product_3'],true))continue;$old=$payload['source_images'][$slot]??'';if($old&&count(array_keys($payload['source_images'],$old,true))===1)$this->pdo->prepare('DELETE FROM business_editorial_images WHERE version_id=? AND storage_name=?')->execute([$id,$old]);}
   if($uploads!==null){if(!$imageConsent&&!$editor)throw new RuntimeException('Autoriza la preparación editorial de tus imágenes antes de cargarlas.');$this->admin()->storeEditorialReferences($id,$uploads,$storedFiles,match($plan){'featured'=>1,'page'=>2,'page_premium'=>9,default=>0});}
   // Stable private storage names survive immutable version copies; never accept another business's reference.
   $sources=$input['source_images']??($payload['source_images']??[]);if(!is_array($sources))throw new RuntimeException('Revisa las fotografías asociadas.');
   $allowedSlots=in_array($plan,['page','page_premium'],true)?['cover']:[];if($plan==='page_premium')$allowedSlots=[...$allowedSlots,'product_0','product_1','product_2','product_3'];
   $removedNames=[];foreach($remove as $reference){$q=$this->pdo->prepare('SELECT storage_name FROM business_editorial_images WHERE id=? AND version_id=?');$q->execute([(int)$reference,(int)($latest['id']??0)]);if($removedName=$q->fetchColumn())$removedNames[]=$removedName;}
   $selected=[];foreach($sources as $slot=>$name){if(!in_array($slot,$allowedSlots,true))throw new RuntimeException('La fotografía no corresponde a esta suscripción.');$name=(string)$name;if($name===''||in_array($name,$removedNames,true)||(int)($uploads['error'][$slot]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK)continue;$q=$this->pdo->prepare('SELECT 1 FROM business_editorial_images WHERE version_id=? AND storage_name=?');$q->execute([$id,$name]);if(!$q->fetchColumn())throw new RuntimeException('La fotografía no pertenece a esta versión.');$selected[$slot]=$name;}
   foreach(array_values(array_filter(array_keys((array)($uploads['name']??[])),static fn($slot)=>(int)($uploads['error'][$slot]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK)) as $offset=>$slot){if(in_array((string)$slot,$allowedSlots,true)&&isset($storedFiles[$offset]))$selected[$slot]=basename($storedFiles[$offset]);}
   // Empty/deleted products cannot leave an orphaned visible association.
   foreach($selected as $slot=>$name)if(str_starts_with($slot,'product_')&&!isset($payload['showcase']['products'][(int)substr($slot,8)])){if((int)($uploads['error'][$slot]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK)throw new RuntimeException('Completa el nombre del producto al que pertenece la fotografía.');unset($selected[$slot]);}
   $payload['source_images']=$selected;$this->pdo->prepare('UPDATE business_editorial_versions SET payload_json=?,payload_hash=? WHERE id=?')->execute([json_encode($payload,JSON_THROW_ON_ERROR),self::hash($payload),$id]);
   $this->event($id,$editor?'admin:'.$actor:'account:'.$account,'saved');$this->pdo->commit();return $id;
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();foreach($storedFiles as $file)if(is_file($file))@unlink($file);throw $e;}
 }
 public function transition(int $id,string $action,int $account=0,string $actor='',string $note='',string $expectedHash='',array $quality=[]):void{
  $initial=$this->version($id);$this->pdo->beginTransaction();try{$this->lock((int)$initial['business_id']);$v=$this->version($id);if((int)$this->latest((int)$v['business_id'])['id']!==$id)throw new RuntimeException('Esta versión fue reemplazada.');
   if($expectedHash===''||!hash_equals($v['payload_hash'],$expectedHash))throw new RuntimeException('La versión revisada cambió. Recarga.');
   $admin=$actor!=='';if(!$admin)$this->authorize($account,(int)$v['business_id']);if($action==='approve'&&!$admin&&$v['status']==='published'&&!empty($v['owner_approved_at'])){$this->pdo->commit();return;}
   $status=match($action){'reject'=>$admin&&in_array($v['status'],['in_editorial','owner_approved','awaiting_owner'],true)?'rejected':null,'submit'=>!$admin&&in_array($v['status'],['draft','changes_requested','rejected'],true)?'in_editorial':null,'prepare'=>$admin&&$v['status']==='in_editorial'?'awaiting_owner':null,'approve'=>!$admin&&$v['status']==='awaiting_owner'?'owner_approved':null,'request_changes'=>!$admin&&$v['status']==='awaiting_owner'?'changes_requested':null,default=>null};
   if(!$status)throw new RuntimeException('Esta acción no corresponde al estado de la versión.');
   if($action==='prepare'){foreach(['translations','images','layout'] as $check)if(($quality[$check]??'')!=='1')throw new RuntimeException('El portal debe revisar las traducciones, las imágenes y la presentación antes de enviar la prepublicación.');$this->validatePrepared($v);$p=$this->payload($v,(int)$v['business_id']);foreach(['es','en','pt-BR'] as $locale)if(trim($p['translations'][$locale]['display_name']??'')==='')throw new RuntimeException('Completa los tres idiomas antes de enviar la prepublicación.');if(in_array($v['plan_code'],['page','page_premium'],true)&&empty($p['media']['cover']))throw new RuntimeException('Prepara la portada antes de enviar la vista final.');}
   if(in_array($action,['request_changes','reject'],true)&&strlen(trim($note))<5)throw new RuntimeException('Indica qué quieres corregir.');if(strlen($note)>2000)throw new RuntimeException('La nota es demasiado larga.');
   $q=$this->pdo->prepare('UPDATE business_editorial_versions SET status=?,note=?,owner_approved_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$q->execute([$status,trim($note),$status==='owner_approved'?gmdate('Y-m-d H:i:s'):null,$id]);if($action==='prepare')$this->event($id,'admin:'.$actor,'quality_checked');$this->event($id,$admin?'admin:'.$actor:'account:'.$account,$action);if($action==='approve')$this->publishApproved($id,'account:'.$account,$expectedHash);$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function event(int $id,string $actor,string $action):void{$this->pdo->prepare('INSERT INTO business_editorial_events(version_id,actor,action) VALUES(?,?,?)')->execute([$id,$actor,$action]);}
 private function applyPayload(int $business,array $payload):void{
$adminInput=$payload;
   foreach($payload['contacts']??[] as $channel=>$contact)$adminInput['contacts'][$channel]=['value'=>$contact['contact_value']??'','public'=>!empty($contact['is_public'])?'1':'0'];
   $this->admin()->save($adminInput);
   $this->pdo->prepare('DELETE FROM business_media WHERE business_id=?')->execute([$business]);foreach(['logo','cover','gallery'] as $role)foreach((array)($payload['media'][$role]??[]) as $order=>$asset){if((int)$asset>0)$this->pdo->prepare('INSERT INTO business_media(business_id,media_asset_id,role,display_order) VALUES(?,?,?,?)')->execute([$business,(int)$asset,$role,$order]);}
 }
 private function validatePrepared(array $v):void{
  $business=(int)$v['business_id'];if($this->options($business)['code']!==$v['plan_code'])throw new RuntimeException('La suscripción cambió; prepara otra versión.');
  $p=$this->payload($v,$business);
  if($v['plan_code']==='page_premium'){BusinessShowcase::validate($p['showcase']??[]);BusinessShowcase::clean($this->pdo,$business,array_merge($p['showcase']??[],['category_codes'=>[$p['category_code'],...($p['category_codes']??[])]]),$p['showcase']??[],true);}
  if($v['plan_code']!=='free'&&(empty($p['location_confirmed'])||trim((string)($p['location_map_url']??''))===''))throw new RuntimeException('Completa y confirma la ubicación antes de preparar la versión final.');
  // Validate production rules in a rollback-only savepoint. No public change is committed.
  $state=$this->pdo->prepare('SELECT publication_status FROM businesses WHERE id=?');$state->execute([$business]);$wasPublished=$state->fetchColumn()==='published';
  $this->pdo->exec('SAVEPOINT editorial_validation');
  try{$this->applyPayload($business,$p);$publisher=new BusinessSubscriptionAdminService($this->pdo);$publisher->validateContent($business,$v['plan_code']);if($wasPublished)$publisher->validateEditorialUpdate($business);else{foreach($publisher->publicationBlockers($business) as $blocker)throw new RuntimeException($blocker);}}
  finally{$this->pdo->exec('ROLLBACK TO SAVEPOINT editorial_validation');$this->pdo->exec('RELEASE SAVEPOINT editorial_validation');}
 }
 public function publish(int $id,string $actor,string $hash):void{
  if(trim($actor)==='')throw new RuntimeException('La publicación requiere un administrador.');$this->publishApproved($id,'admin:'.$actor,$hash);
 }
 private function publishApproved(int $id,string $actor,string $hash):void{
  $initial=$this->version($id);$ownTransaction=!$this->pdo->inTransaction();if($ownTransaction)$this->pdo->beginTransaction();try{$business=(int)$initial['business_id'];$this->lock($business);$v=$this->version($id);if((int)$this->latest($business)['id']!==$id||$v['status']!=='owner_approved'||!hash_equals($v['payload_hash'],$hash))throw new RuntimeException('Publica únicamente la última versión aprobada por el comerciante.');
   $review=$this->pdo->prepare('SELECT 1 FROM business_editorial_events WHERE version_id=? AND action="quality_checked" AND actor LIKE "admin:%"');$review->execute([$id]);if(!$review->fetchColumn())throw new RuntimeException('Falta la revisión editorial del portal para esta versión.');
   if(!hash_equals($v['base_hash'],self::hash($this->live($business))))throw new RuntimeException('La ficha cambió desde que se preparó esta versión. Revisa antes de publicar.');
   if($this->options($business)['code']!==$v['plan_code'])throw new RuntimeException('La suscripción cambió; prepara otra versión.');
   $q=$this->pdo->prepare('SELECT publication_status FROM businesses WHERE id=?');$q->execute([$business]);$wasPublished=$q->fetchColumn()==='published';
   $this->authorize((int)$v['account_id'],$business);
   $payload=$this->payload($v,$business);if($v['plan_code']==='page_premium'){BusinessShowcase::validate($payload['showcase']??[]);BusinessShowcase::clean($this->pdo,$business,array_merge($payload['showcase']??[],['category_codes'=>[$payload['category_code'],...($payload['category_codes']??[])]]),$payload['showcase']??[],true);}$this->applyPayload($business,$payload);
   $publisher=new BusinessSubscriptionAdminService($this->pdo);
   if($wasPublished){$q=$this->pdo->prepare('SELECT status,ends_at FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$business]);$sub=$q->fetch(PDO::FETCH_ASSOC);if($sub['status']!=='active'||($sub['ends_at']!==null&&$sub['ends_at']<=gmdate('Y-m-d H:i:s')))throw new RuntimeException('La suscripción debe estar vigente.');$publisher->validateEditorialUpdate($business);$this->pdo->prepare('UPDATE businesses SET publication_status="published" WHERE id=?')->execute([$business]);}
   else $publisher->publish($business,null,null,$id);
   if($v['plan_code']==='page_premium')PremiumBusinessProfile::save($this->pdo,(int)$v['account_id'],$business,$payload['service_sections']??[],(string)($payload['page_alias']??PremiumBusinessProfile::currentAlias($this->pdo,$business)));
   $this->pdo->prepare('UPDATE business_editorial_versions SET status="published",published_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);$this->event($id,$actor,'published');if($ownTransaction)$this->pdo->commit();
  }catch(Throwable $e){if($ownTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}
