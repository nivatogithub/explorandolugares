<?php
declare(strict_types=1);
require __DIR__.'/../app/PortalSettings.php';
$directory=sys_get_temp_dir().'/portal-settings-'.bin2hex(random_bytes(8));mkdir($directory,0700);
try{
 if(PortalSettings::load($directory)!==[])throw new RuntimeException('Missing override changed defaults');
 $values=['operator'=>'Responsable de prueba','contact'=>'privacidad@example.invalid','retention'=>'Conservar según el criterio de prueba aprobado.','retention_en'=>'Test retention policy.','retention_pt'=>'Critério de conservação de teste.','enabled'=>true,'app_key'=>'never-save'];
 PortalSettings::save($directory,$values,12);$saved=PortalSettings::load($directory);
 if($saved['privacy']['contact']!==$values['contact']||count($saved['privacy'])!==5||isset($saved['app_key']))throw new RuntimeException('Unexpected override');
 $path=$directory.'/portal-settings.json';if((fileperms($path)&0777)!==0600)throw new RuntimeException('Private file permissions');$before=file_get_contents($path);
 try{PortalSettings::save($directory,array_replace($values,['contact'=>'bad-email']),12);throw new LogicException('Invalid email accepted');}catch(RuntimeException $e){}
 if(file_get_contents($path)!==$before)throw new RuntimeException('Invalid save replaced current policy');
 if(PortalSettings::retention($saved['privacy'],'pt-BR')!==$values['retention_pt']||PortalSettings::retention($saved['privacy'],'en')!==$values['retention_en']||PortalSettings::retention($saved['privacy'],'es')!==$values['retention'])throw new RuntimeException('Incorrect locale policy');
 $legacy=$saved['privacy'];unset($legacy['retention_pt']);if(PortalSettings::retention($legacy,'pt')!=='')throw new RuntimeException('Portuguese silently fell back to Spanish');
 echo "PASS: privacy fields only, persistence, private permissions and invalid-save preservation\n";
}finally{if(is_file($directory.'/portal-settings.json'))unlink($directory.'/portal-settings.json');rmdir($directory);}
