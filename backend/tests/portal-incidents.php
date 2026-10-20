<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PortalIncidents.php';
$dir=sys_get_temp_dir().'/el-incidents-'.bin2hex(random_bytes(8));mkdir($dir,0700);
function incident_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 PortalIncidents::configure($dir);$_SERVER['REQUEST_URI']='/negocios/registro/?password=SECRET&email=private@example.invalid';$_POST=['password'=>'SECRET'];
 $ref=PortalIncidents::capture(new PDOException('SQL leaked password=SECRET and private@example.invalid'),'registration');
 $rows=PortalIncidents::recent();incident_check(count($rows)===1&&$rows[0]['reference']===$ref,'Missing reference');
 $raw=file_get_contents($dir.'/current.jsonl');incident_check(!str_contains($raw,'SECRET')&&!str_contains($raw,'private@')&&!str_contains($raw,'SQL leaked'),'Sensitive data leaked');
 incident_check($rows[0]['route']==='/negocios/registro/','Query leaked');
 incident_check(PortalIncidents::technical(new RuntimeException('Expected validation'),'validation')===null,'Validation should not create incidents');
 incident_check(count(PortalIncidents::recent())===1,'Validation polluted diagnostics');
 file_put_contents($dir.'/current.jsonl',str_repeat('x',1048576));PortalIncidents::capture(new Error('private message'),'rotation');incident_check(is_file($dir.'/previous.jsonl')&&filesize($dir.'/current.jsonl')<16384,'Rotation failed');
 incident_check((fileperms($dir.'/current.jsonl')&0777)===0600,'Private permissions missing');
 unlink($dir.'/current.jsonl');symlink($dir.'/target',$dir.'/current.jsonl');PortalIncidents::capture(new Error('sensitive'),'symlink');incident_check(!file_exists($dir.'/target'),'Followed symlink');unlink($dir.'/current.jsonl');
 echo "OK: references, no sensitive messages/payloads/query, expected validation excluded, bounded rotation, permissions and symlink protection.\n";
}finally{foreach(glob($dir.'/*') as $p)if(is_file($p)||is_link($p))unlink($p);rmdir($dir);}
