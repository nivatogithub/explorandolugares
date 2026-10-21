<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AdminEmailWorkspace.php';
function emailCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function emailReject(Closure $fn):void{try{$fn();}catch(RuntimeException){return;}throw new RuntimeException('Expected rejection.');}
$dir=sys_get_temp_dir().'/el-email-'.bin2hex(random_bytes(8));$w=new AdminEmailWorkspace($dir);
try{
 $id=$w->saveTemplate(['name'=>'Prueba','subject'=>'Hola {{business_name}}','html'=>'<p onclick="evil()">Hola {{recipient_name}}</p><script>evil()</script><a href="javascript:evil()">Nada</a><a href="https://example.invalid/">Enlace</a>'],'admin');
 $t=$w->get('template',$id);emailCheck(!str_contains($t['html'],'script')&&!str_contains($t['html'],'onclick')&&!str_contains($t['html'],'javascript:'),'Unsafe HTML retained.');
 $sender=['sender_email'=>'sender@example.invalid','sender_name'=>'Test'];$settings=[...$sender,'enabled'=>true,'api_key'=>'fake'];
 $m=$w->prepare($id,'recipient@example.invalid',['business_name'=>'<Business>','recipient_name'=>'<img src=x onerror=evil()>'],$sender,'admin',8);$snapshot=$w->get('message',$m);
 emailCheck(str_contains($snapshot['content']['subject'],'<Business>')&&str_contains($snapshot['content']['html'],'&lt;img'),'Values must remain escaped.');
 $w->saveTemplate(['template_id'=>$id,'name'=>'Editada','subject'=>'Otra','html'=>'<p>Otra versión</p>'],'admin');emailCheck($w->get('message',$m)['content']===$snapshot['content'],'Editing a template changed a prepared message.');
 $custom=$w->prepare($id,'recipient@example.invalid',[],$sender,'admin',8,['subject'=>'Ajuste individual','html'=>'<p>Contenido individual</p>']);emailCheck($w->get('message',$custom)['content']['subject']==='Ajuste individual'&&$w->get('template',$id)['subject']==='Otra','Individual override modified the template.');
 $calls=0;$transport=function($payload)use(&$calls){$calls++;emailCheck($payload['to'][0]['email']==='recipient@example.invalid','Wrong recipient.');return ['status'=>201,'body'=>['messageId'=>'synthetic-id']];};
 emailReject(fn()=>$w->send($m,[...$settings,'sender_name'=>'Changed'],'admin',$transport));emailCheck($calls===0,'Sender change sent a message.');
 $result=$w->send($m,$settings,'admin',$transport);emailCheck($result['status']==='accepted','Acceptance missing.');emailReject(fn()=>$w->send($m,$settings,'admin',$transport));emailCheck($calls===1,'Duplicate send occurred.');
 $uncertain=$w->prepare($id,'recipient@example.invalid',[],$sender,'admin',8);$result=$w->send($uncertain,$settings,'admin',static function(){throw new RuntimeException('timeout');});emailCheck($result['status']==='uncertain','Timeout not recorded as uncertain.');emailReject(fn()=>$w->send($uncertain,$settings,'admin',$transport));
 emailReject(fn()=>$w->get('message','../settings'));emailReject(fn()=>AdminEmailWorkspace::render('Test','<p>{{unknown}}</p>',[]));emailReject(fn()=>AdminEmailWorkspace::render('{{business_name}}','<p>Test</p>',['business_name'=>"Header\r\nBcc: victim@example.invalid"]));
 echo "OK: safe templates, escaped real values, immutable preview snapshots, private history, sender checks, no duplicate or uncertain retries; simulated transport only.\n";
}finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);}
