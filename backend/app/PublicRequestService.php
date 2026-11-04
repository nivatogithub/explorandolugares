<?php
declare(strict_types=1);
require_once __DIR__.'/PublicRequestException.php';
require_once __DIR__.'/PublicRequestWriter.php';
final class PublicRequestService
{
 public const TYPES=['suggestion'=>'Sugerencia','petition'=>'Petición','complaint'=>'Queja','claim'=>'Reclamo'];
 private PublicRequestWriter $writer;
 public function __construct(PDO $pdo){$this->writer=new PublicRequestWriter($pdo);}
 public function submit(array $data):string{
  $type=(string)($data['request_type']??'');$name=trim((string)($data['name']??''));$email=strtolower(trim((string)($data['email']??'')));$message=trim((string)($data['message']??''));
  if(!isset(self::TYPES[$type]))throw new PublicRequestException('invalid_type','Selecciona el tipo de solicitud.');
  if(strlen($name)<2||strlen($name)>180)throw new PublicRequestException('invalid_name','Escribe tu nombre, máximo 180 caracteres.');
  if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new PublicRequestException('invalid_email','Escribe un correo válido para responderte.');
  if(strlen($message)<10||strlen($message)>10000)throw new PublicRequestException('invalid_message','Describe tu solicitud entre 10 y 10.000 caracteres.');
  if(($data['consent']??'')!=='1')throw new PublicRequestException('consent_required','Autoriza el tratamiento de los datos para atender tu solicitud.');
  $reference=strtoupper(bin2hex(random_bytes(12)));$this->writer->insert($reference,$type,$name,$email,$message);return $reference;
 }
 public function review(int $id,string $status,string $note):void{
  if(!in_array($status,['pending','in_review','resolved'],true)||strlen($note)>10000)throw new RuntimeException('Revisa el estado y la nota.');
  $this->writer->review($id,$status,$note);
 }
}
