<?php
declare(strict_types=1);
$runtime=$argv[1]??'';
if(!is_file($runtime.'/app/PlacePackage.php'))throw new RuntimeException('Pass backend path with the current PlacePackage contract');
require_once $runtime.'/app/PlaceLabels.php';require_once $runtime.'/app/PlacePackage.php';
$count=0;$images=0;
foreach(glob(__DIR__.'/places/*/manifest.json') as $f){$v=json_decode(file_get_contents($f),true,512,JSON_THROW_ON_ERROR);PlacePackage::validateManifest($v);$count++;
 foreach([$v['hero_image'],...array_column($v['places'],'image')] as $img){PlacePackage::inspectImage(file_get_contents(dirname($f).'/'.$img['file']),$img['file']);$images++;}}
echo json_encode(['valid_contracts'=>$count,'valid_images'=>$images])."\n";
