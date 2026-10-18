<?php
declare(strict_types=1);
$root=$argv[1]??throw new RuntimeException('Indica raíz PREPROD para validadores de lectura.');
require rtrim($root,'/').'/backend/app/DestinationPackage.php';
require rtrim($root,'/').'/backend/app/DestinationAdminService.php';
$read=DestinationPackage::readZip(__DIR__.'/paquete-preprod.zip');
$m=$read['manifest'];
if($m['destination']['slug']!=='bogota'||count($m['places'])>8)throw new RuntimeException('Identidad o límite incorrectos.');
foreach($m['places'] as $place)if($place['publication_status']!=='draft')throw new RuntimeException('Atractivo no draft.');
$reflection=new ReflectionClass(DestinationAdminService::class);
$service=$reflection->newInstanceWithoutConstructor();
$research=$reflection->getMethod('validateInternalResearch')->invoke($service,json_encode($m['local_research'],JSON_THROW_ON_ERROR));
echo json_encode(['zip'=>'validado','schema'=>2,'slug'=>'bogota','atractivos'=>count($m['places']),'imagenes'=>count($read['images']),'local_research'=>'validado_sin_BD','idiomas'=>array_keys($m['destination']['translations']),'publicacion'=>'no_autorizada'],JSON_UNESCAPED_UNICODE),PHP_EOL;
