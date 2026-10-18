<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PublicRequestService.php';
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec(file_get_contents(dirname(__DIR__).'/database/migrations/036-public-requests.sqlite.sql'));$s=new PublicRequestService($p);
foreach(PublicRequestService::TYPES as $type=>$label){$ref=$s->submit(['request_type'=>$type,'name'=>'Persona Ficticia','email'=>'prueba@pruebas.invalid','message'=>'Solicitud ficticia para prueba aislada.','consent'=>'1']);if(!preg_match('/^[A-F0-9]{24}$/',$ref))throw new Exception('Referencia inválida');}
if((int)$p->query('SELECT COUNT(*) FROM public_requests')->fetchColumn()!==4)throw new Exception('Falta persistencia');
try{$s->submit(['request_type'=>'otro']);throw new LogicException('Tipo inválido aceptado');}catch(RuntimeException $e){}
$s->review(1,'resolved','Respuesta enviada fuera de este sistema');if($p->query('SELECT status FROM public_requests WHERE id=1')->fetchColumn()!=='resolved')throw new Exception('No guardó atención');
echo "OK: cuatro tipos, referencias, validación y revisión persistente en BD aislada.\n";
