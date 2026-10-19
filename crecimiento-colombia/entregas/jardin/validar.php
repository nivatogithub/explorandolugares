<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/backend/app/DestinationPackage.php';
require $root.'/backend/app/BusinessPackage.php';
require $root.'/backend/app/DestinationAdminService.php';
$read=fn(string $n)=>json_decode(file_get_contents(__DIR__.'/'.$n),true,64,JSON_THROW_ON_ERROR);
$m=$read('paquete-destino.json');$b=$read('directorio-30.json');
DestinationPackage::validateManifest($m);BusinessPackage::validateManifest($b);
$cats=json_decode(file_get_contents($root.'/backend/app/contact-options.json'),true);$allowed=[];
foreach($cats['groups'] as $g) foreach($g['categories']??[] as $c)$allowed[]=$c['value'];
foreach($b['businesses'] as $x)if(!in_array($x['category_code'],$allowed,true))throw new RuntimeException('Categoría desconocida');
$names=array_map(fn($x)=>mb_strtolower(trim($x['name'])),$b['businesses']);
if(count($names)!==30||count(array_unique($names))!==30)throw new RuntimeException('Directorio no contiene30 nombres únicos');
$missing=[];foreach([$m['hero_image'],...array_column($m['places'],'image')] as $x)if(!is_file(__DIR__.'/'.$x['file']))$missing[]=$x['file'];
$staging=sys_get_temp_dir().'/el-business-package-'.bin2hex(random_bytes(6));mkdir($staging,0700);
try{$zip=BusinessPackage::readZip(__DIR__.'/paquete-negocios-jardin.zip',$staging);$zipCount=count($zip['manifest']['businesses']);}finally{BusinessPackage::removeDirectory($staging);}
$dest=DestinationPackage::readZip(__DIR__.'/paquete-destino-jardin.zip');
$hashes=[];foreach($dest['images'] as $img)$hashes[]=hash('sha256',$img['bytes']);
if(count($hashes)!==count($m['places'])+1||count(array_unique($hashes))!==count($m['places'])+1)throw new RuntimeException('Faltan imágenes o hay duplicados');
// Native form validation only, with isolated memory PDO; no writes or publication.
$form=$m['destination'];$form['id']=1;$form['publication_status']='published';$form['display_order']=1;$form['places']=$m['places'];
foreach(['es','en','pt-BR'] as $loc){
 $form['history'][$loc]=implode("\n",$form['history'][$loc]);$form['reasons'][$loc]=implode("\n",$form['reasons'][$loc]);
 $form['sources'][$loc]=implode("\n",array_map(fn($x)=>$x['section'].' | '.$x['label'].' | '.$x['url'],$form['sources'][$loc]));
}
foreach($form['places'] as &$poi)$poi['publication_status']='published';unset($poi);
$validator=new ReflectionMethod(DestinationAdminService::class,'validate');
$validated=$validator->invoke(new DestinationAdminService(new PDO('sqlite::memory:')),$form);
$criteria=$read('criterios-editoriales.json');$bySlug=[];foreach($criteria['items'] as $item)$bySlug[$item['slug']]=$item;
if(count(array_filter($m['places'],fn($x)=>$x['type']==='place'))>8)throw new RuntimeException('Más de8 atractivos; no hay cuota mínima');
foreach($m['places'] as $poi){$c=$bySlug[$poi['slug']]??null;if(!$c||$c['decision']!=='include')throw new RuntimeException('Falta decisión editorial por item');if($poi['type']==='experience'){if(empty($c['offer_verified'])||empty($c['operator']['name'])||empty($c['operator']['offer_url'])||empty($c['operator']['source_url']))throw new RuntimeException('Experiencia sin oferta trazada');foreach(['es','en','pt-BR'] as $l)if(trim((string)($poi['translations'][$l]['external_url']??''))!==''||trim((string)($poi['translations'][$l]['external_label']??''))!=='')throw new RuntimeException('La oferta editorial neutral no enlaza gratuitamente al oferente');}}
$r=['editorial_criteria'=>'pass','checked_at'  =>'2026-10-19','destination_manifest_schema'=>'pass','destination_zip_read'=>'pass','destination_zip_images'=>count($dest['images']),'all_image_hashes_unique'=>true,'admin_form_validation'=>'pass','destination_zip_bytes'=>filesize(__DIR__.'/paquete-destino-jardin.zip'),'destination_zip_sha256'=>hash_file('sha256',__DIR__.'/paquete-destino-jardin.zip'),'business_manifest_schema'=>'pass','business_zip_read'=>'pass','businesses'=>$zipCount,'attractions'=>count(array_filter($m['places'],fn($x)=>$x['type']==='place')),'experiences'=>count(array_filter($m['places'],fn($x)=>$x['type']==='experience')),'missing_images'=>$missing,'destination_import_ready'=>count($missing)===0,'database_import_executed'=>false,'site_qa'=>'not_executed','approval'=>'pending'];
file_put_contents(__DIR__.'/validacion.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
