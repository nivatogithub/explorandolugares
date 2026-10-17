<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DestinationPackage.php';
function package_check(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$root=dirname(__DIR__,2);
$example=DestinationPackage::readZip($root.'/backend/public/plantillas/paquete-destino-v2.zip');
package_check($example['manifest']['schema_version']===2,'The downloadable example uses the current schema.');
package_check(count($example['manifest']['places'])===1&&count($example['images'])===2,'Manifest references and bundled images agree.');
foreach(['es','en','pt-BR'] as $locale) package_check(isset($example['manifest']['destination']['translations'][$locale]),"Destination translation {$locale} exists.");
$sonson=DestinationPackage::readZip($root.'/content/paquetes/sonson/paquete-destino-sonson.zip');
package_check($sonson['manifest']['destination']['slug']==='sonson','The approved Sonsón package has the expected destination slug.');
package_check(count($sonson['manifest']['places'])===4&&count($sonson['images'])===5,'The approved Sonsón package contains its four places and five referenced images.');
package_check($sonson['manifest']['destination']['translations']['es']['display_name']==='Sonsón','The approved Sonsón package keeps its Spanish destination name.');
$experience=$example['manifest'];$experience['places'][0]['type']='experience';
DestinationPackage::validateManifest($experience); // Editorial experience without an operator link is valid.
foreach(['es'=>'Consultar com o operador','en'=>'Check with operator','pt-BR'=>'Consultar o operador'] as $locale=>$label){$experience['places'][0]['translations'][$locale]['external_url']='https://example.org/experience';$experience['places'][0]['translations'][$locale]['external_label']=$label;}
DestinationPackage::validateManifest($experience);
$experience['places'][0]['translations']['es']['external_url']='http://example.org/experience';
$rejected=false;try{DestinationPackage::validateManifest($experience);}catch(RuntimeException){$rejected=true;}
package_check($rejected,'Experience operator links require HTTPS.');

$tmp=tempnam(sys_get_temp_dir(),'el-invalid-package-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
$bad=$example['manifest'];$bad['hero_image']['file']='images/../../outside.png';
$zip->addFromString('paquete-destino.json',json_encode($bad,JSON_UNESCAPED_UNICODE));$zip->addFromString('images/example.png',$example['images']['images/ejemplo-portada.png']['bytes']);$zip->close();
$rejected=false;try{DestinationPackage::readZip($tmp);}catch(RuntimeException){$rejected=true;}@unlink($tmp);
package_check($rejected,'Archive traversal paths are rejected.');

$tmp=tempnam(sys_get_temp_dir(),'el-missing-package-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
$bad=$example['manifest'];$bad['hero_image']['file']='images/missing.png';
$zip->addFromString('paquete-destino.json',json_encode($bad,JSON_UNESCAPED_UNICODE));
foreach($example['images'] as $name=>$image)$zip->addFromString($name,$image['bytes']);
$zip->close();$rejected=false;try{DestinationPackage::readZip($tmp);}catch(RuntimeException){$rejected=true;}@unlink($tmp);
package_check($rejected,'Unreferenced images and absent referenced files are rejected.');

$tmp=tempnam(sys_get_temp_dir(),'el-v1-package-');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
$old=$example['manifest'];$old['schema_version']=1;$zip->addFromString('paquete-destino.json',json_encode($old));foreach($example['images'] as $name=>$image)$zip->addFromString($name,$image['bytes']);$zip->close();
$rejected=false;try{DestinationPackage::readZip($tmp);}catch(RuntimeException){$rejected=true;}@unlink($tmp);
package_check($rejected,'ZIP import rejects legacy manifests; legacy JSON stays a separate supported path.');
echo "OK: ZIP example and Sonsón package, translations, image references, file validation and path traversal rejection.\n";
