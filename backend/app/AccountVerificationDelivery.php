<?php
declare(strict_types=1);
require_once __DIR__.'/AccountEmailVerificationService.php';
require_once __DIR__.'/AccountMailConfiguration.php';
/** Transactional transport; real local delivery requires the private test recipient. */
final class AccountVerificationDelivery
{
    public static function send(PDO $pdo,array $config,int $id):void
    {
        $q=$pdo->prepare('SELECT email FROM accounts WHERE id=? AND status="pending_activation"');$q->execute([$id]);$email=$q->fetchColumn();if(!$email)return;
        $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
        $local=getenv('EL_PREPROD_LOCAL')==='1'&&!empty($config['local_test'])&&preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host);
        $mail=AccountMailConfiguration::resolve($config,(string)$email);$local=$mail['mailbox'];$delivery=$mail['delivery'];
        if(!$local&&!$delivery->ready()){if(class_exists('PortalIncidents',false))PortalIncidents::capture(new RuntimeException(),'verification_configuration');throw new RuntimeException('Tu cuenta está guardada, pero no pudimos enviar el correo de activación. Solicita un nuevo enlace más tarde.');}
        $service=new AccountEmailVerificationService($pdo);$token=$service->issue($id);
        try{
            if($local){
                $path=dirname(__DIR__).'/private/mailbox.json';$f=fopen($path,'c+');if(!$f)throw new RuntimeException('Buzón local no disponible.');
                try{if(!flock($f,LOCK_EX))throw new RuntimeException('Buzón ocupado.');$messages=json_decode(stream_get_contents($f)?:'[]',true,32,JSON_THROW_ON_ERROR);$messages[]=['to'=>$email,'created_at'=>gmdate('c'),'kind'=>'verification','url'=>'http://'.$host.'/cuenta/verificar-correo/?token='.$token];rewind($f);ftruncate($f,0);fwrite($f,json_encode(array_slice($messages,-50),JSON_THROW_ON_ERROR));fflush($f);flock($f,LOCK_UN);}finally{fclose($f);chmod($path,0600);}
            }else{$delivery->send($email,$token,true);}
        }catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::capture($e,'verification_delivery');$service->revoke($token);throw new RuntimeException('Tu cuenta está guardada. No pudimos confirmar el envío; solicita otro enlace más tarde.',0,$e);}
    }
}
