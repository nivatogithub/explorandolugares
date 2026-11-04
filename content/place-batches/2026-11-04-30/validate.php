<?php
declare(strict_types=1);
$repo=dirname(__DIR__,3);require_once $repo.'/backend/app/PlaceLabels.php';require_once $repo.'/backend/app/PlacePackage.php';$count=0;
foreach(glob(__DIR__.'/places/*/manifest.draft.json') as $file){$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);PlacePackage::validateManifest($data);foreach([$data['hero_image'],...array_column($data['places'],'image')] as $image){$path=dirname($file).'/'.$image['file'];PlacePackage::inspectImage(file_get_contents($path),$image['file']);}$count++;}
if($count!==30)throw new RuntimeException('Expected 30 manifests');echo "Validated $count place contracts and their media\n";
