<?php
declare(strict_types=1);
require_once __DIR__.'/AccountEmailVerificationService.php';
require_once __DIR__.'/AccountMailConfiguration.php';
/** Transactional transport; real local delivery requires the private test recipient. */
final class AccountVerificationDelivery
{
    public static function send(PDO $pdo,array $config,int $id,bool $allowActiveUnverified=false):void
    {
        $eligible=$allowActiveUnverified?'(status="pending_activation" OR (status="active" AND email_verified_at IS NULL))':'status="pending_activation"';
        $q=$pdo->prepare('SELECT email FROM accounts WHERE id=? AND '.$eligible);$q->execute([$id]);$email=$q->fetchColumn();if(!$email)return;
        $mail=self::availableMail($config,(string)$email);
        $service=new AccountEmailVerificationService($pdo);
        $token=$service->issue($id,$allowActiveUnverified,(string)$email);
        if($token!==null)self::deliver($service,$mail,(string)$email,$token);
    }

    public static function correctPendingEmail(PDO $pdo,array $config,int $id,string $email,string $password):void
    {
        $email=strtolower(trim($email));
        if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Escribe un correo válido.');
        $mail=self::availableMail($config,$email);
        $service=new AccountEmailVerificationService($pdo);
        $token=$service->correctPendingEmail($id,$email,$password);
        self::deliver($service,$mail,$email,$token);
    }

    private static function availableMail(array $config,string $email):array
    {
        $mail=AccountMailConfiguration::resolve($config,$email);
        if(!$mail['mailbox']&&!$mail['delivery']->ready()){
            if(class_exists('PortalIncidents',false))PortalIncidents::capture(new RuntimeException(),'verification_configuration');
            throw new RuntimeException('No pudimos preparar el correo de activación. Inténtalo más tarde.');
        }
        return $mail;
    }

    private static function deliver(AccountEmailVerificationService $service,array $mail,string $email,string $token):void
    {
        $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
        $local=$mail['mailbox'];$delivery=$mail['delivery'];
        try{
            if($local){
                $path=dirname(__DIR__).'/private/mailbox.json';$f=fopen($path,'c+');if(!$f)throw new RuntimeException('Buzón local no disponible.');
                try{if(!flock($f,LOCK_EX))throw new RuntimeException('Buzón ocupado.');$messages=json_decode(stream_get_contents($f)?:'[]',true,32,JSON_THROW_ON_ERROR);$messages[]=['to'=>$email,'created_at'=>gmdate('c'),'kind'=>'verification','url'=>'http://'.$host.'/cuenta/verificar-correo/?token='.$token];rewind($f);ftruncate($f,0);fwrite($f,json_encode(array_slice($messages,-50),JSON_THROW_ON_ERROR));fflush($f);flock($f,LOCK_UN);}finally{fclose($f);chmod($path,0600);}
            }else{$delivery->send($email,$token,true);}
        }catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::capture($e,'verification_delivery');$service->revoke($token);throw new RuntimeException('Tu cuenta está guardada. No pudimos confirmar el envío; solicita otro enlace más tarde.',0,$e);}
    }
}
