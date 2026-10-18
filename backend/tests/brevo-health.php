<?php
declare(strict_types=1);
require __DIR__.'/../app/BrevoHealth.php';
function expectHealth(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$settings=['enabled'=>true,'api_key'=>'do-not-disclose-this-test-key','sender_email'=>'correo@example.invalid','sender_name'=>'Portal'];$paths=[];
$transport=static function($path)use(&$paths){$paths[]=$path;return ['status'=>200,'body'=>match($path){'/account'=>['relay'=>['enabled'=>true]],'/senders'=>['senders'=>[['email'=>'correo@example.invalid','active'=>true]]],default=>['verified'=>true,'authenticated'=>true]}];};
$result=BrevoHealth::check($settings,$transport);expectHealth($result['status']==='ok','Valid connection rejected');expectHealth($paths===['/account','/senders','/senders/domains/example.invalid'],'Unexpected provider operation');
expectHealth(!str_contains(json_encode($result),$settings['api_key']),'Secret leaked');
$denied=BrevoHealth::check($settings,static fn($p)=>['status'=>401,'body'=>['message'=>'must not be exposed']]);expectHealth($denied['status']==='error'&&!str_contains(json_encode($denied),'must not be exposed'),'Provider errors leaked');
$inactive=BrevoHealth::check($settings,static fn($p)=>['status'=>200,'body'=>match($p){'/account'=>['relay'=>['enabled'=>false]],'/senders'=>['senders'=>[['email'=>'correo@example.invalid','active'=>false]]],default=>['verified'=>true,'authenticated'=>false]}]);expectHealth($inactive['status']==='warning'&&!$inactive['checks']['relay']['ok']&&!$inactive['checks']['sender']['ok']&&!$inactive['checks']['domain']['ok'],'Unavailable mail presented as healthy');
$calls=0;BrevoHealth::check(array_replace($settings,['enabled'=>false]),static function($p)use(&$calls){$calls++;return [];});expectHealth($calls===0,'Disabled connection contacted provider');
$dir=sys_get_temp_dir().'/brevo-health-'.bin2hex(random_bytes(6));
try{
 BrevoHealth::save($dir,$result);expectHealth(BrevoHealth::load($dir,$settings)['status']==='ok','Result not persisted');expectHealth((fileperms($dir.'/health.json')&0777)===0600,'Result is not private');
 expectHealth(BrevoHealth::load($dir,array_replace($settings,['api_key'=>'rotated']))['status']==='stale','Rotated key retained a green status');
 $result['checked_at']=gmdate('c',time()-901);BrevoHealth::save($dir,$result);expectHealth(BrevoHealth::load($dir,$settings)['status']==='stale','Old result retained a green status');
 expectHealth(count(glob($dir.'/*'))===1,'Health snapshots accumulated');
}finally{if(is_file($dir.'/health.json'))unlink($dir.'/health.json');if(is_dir($dir))rmdir($dir);}
echo "PASS: read-only Brevo checks, private persistence, no secret disclosure, key rotation and expiry\n";
