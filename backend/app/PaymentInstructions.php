<?php
declare(strict_types=1);
/** Manual payment instructions, private per environment. Test data never renders in production. */
final class PaymentInstructions
{
 public static function load(PDO $pdo,string $directory,bool $localTest):array{
  $data=$pdo->query('SELECT enabled,test_mode,holder,bank,account_number,account_type,breb_key,notes,qr_file,updated_at,updated_by FROM payment_instructions WHERE id=1')->fetch(PDO::FETCH_ASSOC);
  if(!$data||(!empty($data['test_mode'])&&!$localTest))return [];
  $data['enabled']=(bool)$data['enabled'];$data['test_mode']=(bool)$data['test_mode'];return $data;
 }
 public static function save(PDO $pdo,string $directory,array $input,array $upload,bool $localTest,int $actor):void{
  $old=self::load($pdo,$directory,$localTest);$data=['enabled'=>($input['enabled']??'')==='1','test_mode'=>$localTest,'updated_at'=>gmdate('c'),'updated_by'=>$actor];
  foreach(['holder'=>180,'bank'=>120,'account_number'=>80,'breb_key'=>120,'notes'=>1000] as $field=>$limit){$v=$input[$field]??'';if(!is_string($v)||strlen($v)>$limit||preg_match('/[\x00-\x1f\x7f]/',$v))throw new RuntimeException('Revisa los datos de pago.');$data[$field]=trim($v);}
  $data['account_type']=(string)($input['account_type']??'');if(!in_array($data['account_type'],['','savings','checking'],true))throw new RuntimeException('Selecciona Ahorros o Corriente.');
  if($data['enabled']&&$data['holder']==='')throw new RuntimeException('Indica el titular de los medios de pago.');
  if($data['account_number']!==''&&($data['bank']===''||$data['account_type']===''))throw new RuntimeException('Completa el banco y el tipo de cuenta.');
  $data['qr_file']=$old['qr_file']??'';$newFile=null;
  if(is_link($directory))throw new RuntimeException('La carpeta de pagos no es válida.');if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('No se pudo preparar el almacenamiento de pagos.');
  try{
   if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
    if(($upload['error']??0)!==UPLOAD_ERR_OK||!is_uploaded_file($upload['tmp_name']??'')||(int)($upload['size']??0)>2*1024*1024)throw new RuntimeException('Carga una imagen QR de máximo 2 MB.');
    $info=@getimagesize($upload['tmp_name']);if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)||$info[0]<64||$info[1]<64||$info[0]>2048||$info[1]>2048)throw new RuntimeException('El QR debe ser PNG, JPG o WebP, de 64 a 2048 píxeles por lado.');
    if(!function_exists('imagecreatefromstring'))throw new RuntimeException('Falta la extensión de imágenes GD en el servidor.');
    $image=@imagecreatefromstring(file_get_contents($upload['tmp_name']));if(!$image)throw new RuntimeException('No se pudo leer la imagen QR.');
    $name=bin2hex(random_bytes(24)).'.png';$newFile=$directory.'/'.$name;
    try{if(!imagepng($image,$newFile))throw new RuntimeException('No se pudo guardar el QR.');chmod($newFile,0600);}finally{imagedestroy($image);}$data['qr_file']=$name;
   }
   if($data['enabled']&&$data['account_number']===''&&$data['breb_key']===''&&$data['qr_file']==='')throw new RuntimeException('Configura al menos un medio de pago.');
   $fields=['enabled','test_mode','holder','bank','account_number','account_type','breb_key','notes','qr_file','updated_at','updated_by'];
   $data['updated_at']=gmdate('Y-m-d H:i:s');
   $q=$pdo->prepare('REPLACE INTO payment_instructions (id,'.implode(',',$fields).') VALUES (1,'.implode(',',array_fill(0,count($fields),'?')).')');
   $q->execute(array_map(static fn($field)=>is_bool($data[$field])?(int)$data[$field]:$data[$field],$fields));
  }catch(Throwable $e){if($newFile&&is_file($newFile))unlink($newFile);throw $e;}
 }
}
