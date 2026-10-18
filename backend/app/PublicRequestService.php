<?php
declare(strict_types=1);
final class PublicRequestService
{
 public const TYPES=['suggestion'=>'Sugerencia','petition'=>'Petición','complaint'=>'Queja','claim'=>'Reclamo'];
 public function __construct(private PDO $pdo){}
 public function submit(array $data):string{
  $type=(string)($data['request_type']??'');$name=trim((string)($data['name']??''));$email=strtolower(trim((string)($data['email']??'')));$message=trim((string)($data['message']??''));
  if(!isset(self::TYPES[$type]))throw new RuntimeException('Selecciona el tipo de solicitud.');
  if(strlen($name)<2||strlen($name)>180)throw new RuntimeException('Escribe tu nombre, máximo 180 caracteres.');
  if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Escribe un correo válido para responderte.');
  if(strlen($message)<10||strlen($message)>10000)throw new RuntimeException('Describe tu solicitud entre 10 y 10.000 caracteres.');
  if(($data['consent']??'')!=='1')throw new RuntimeException('Autoriza el tratamiento de los datos para atender tu solicitud.');
  $reference=strtoupper(bin2hex(random_bytes(12)));$q=$this->pdo->prepare('INSERT INTO public_requests(reference,request_type,name,email,message,consent_version) VALUES(?,?,?,?,?,?)');$q->execute([$reference,$type,$name,$email,$message,'public-request-v1']);return $reference;
 }
 public function review(int $id,string $status,string $note):void{
  if(!in_array($status,['pending','in_review','resolved'],true)||strlen($note)>10000)throw new RuntimeException('Revisa el estado y la nota.');
  $this->pdo->prepare('UPDATE public_requests SET status=?,response_note=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$note,$id]);
 }
}
