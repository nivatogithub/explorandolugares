<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/backend/app/DestinationPackage.php';
require $root.'/backend/app/DestinationAdminService.php';
require $root.'/backend/app/BusinessPackage.php';
$dir=sys_get_temp_dir().'/el-destination-package-santafe-'.bin2hex(random_bytes(4));mkdir($dir);
try{
 $r=DestinationPackage::readZip(__DIR__.'/paquete-destino-santa-fe-de-antioquia.zip',$dir);$m=$r['manifest'];$in=$m['destination'];
 // Validation only: no constructor, database connection, save or publication action.
 $in['id']=1;$in['publication_status']='published';$in['display_order']=1;$in['places']=$m['places'];
 foreach(['es','en','pt-BR'] as $l){$in['history'][$l]=implode("\n",$in['history'][$l]);$in['reasons'][$l]=implode("\n",$in['reasons'][$l]);$in['sources'][$l]=implode("\n",array_map(static fn($s)=>$s['section_key'].'|'.$s['label'].'|'.$s['url'],$in['sources'][$l]));}
 foreach($in['places'] as &$p)$p['publication_status']='published';unset($p);
 $class=new ReflectionClass(DestinationAdminService::class);$instance=$class->newInstanceWithoutConstructor();$method=$class->getMethod('validate');$clean=$method->invoke($instance,$in);
 BusinessPackage::validateManifest(json_decode(file_get_contents(__DIR__.'/directorio-preprod-30.json'),true));
 echo json_encode(['package'=>'pass','admin_validation_only'=>'pass','business_manifest'=>'pass','places'=>count($m['places']),'images'=>count($r['images']),'database_used'=>false,'publication_performed'=>false],JSON_PRETTY_PRINT),"\n";
}finally{foreach(new FilesystemIterator($dir) as $f)unlink($f->getPathname());rmdir($dir);}
