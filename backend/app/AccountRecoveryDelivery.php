<?php
declare(strict_types=1);
require_once __DIR__.'/AccountMailTemplate.php';
/** Transactional recovery only: never subscribes a person to campaigns. */
final class AccountRecoveryDelivery
{
    public function __construct(private array $settings,private ?Closure $transport=null,private bool $allowLocalOrigin=false,private ?string $receiptPath=null){}
    public function ready():bool
    {
        return ($this->settings['enabled']??false)===true&&!empty($this->settings['api_key'])&&filter_var($this->settings['sender_email']??'',FILTER_VALIDATE_EMAIL)&&!empty($this->settings['sender_name'])&&($this->allowLocalOrigin?($this->settings['public_origin']??'')==='http://127.0.0.1:8088':preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD',(string)($this->settings['public_origin']??'')))&&($this->transport||function_exists('curl_init'));
    }
    public function send(string $email,string $token,bool $verification=false,bool $example=false,bool $reactivation=false):void
    {
        if(!$this->ready()||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('La recuperación por correo no está configurada.');
        $url=$this->settings['public_origin'].($verification?'/cuenta/verificar-correo/?token=':'/negocios/cambiar-clave/?token=').$token;
        if($reactivation)$url=$this->settings['public_origin'].'/negocios/cambiar-clave/?reactivacion=1&token='.$token;
        if($example){if(!$this->allowLocalOrigin)throw new RuntimeException('Los ejemplos solo se envían desde preproducción.');$url=$this->settings['public_origin'].'/correo-ejemplo.php?tipo='.($verification?'verificacion':'recuperacion');}
        $payload=array_merge(['sender'=>['email'=>$this->settings['sender_email'],'name'=>$this->settings['sender_name']],'to'=>[['email'=>$email]]],AccountMailTemplate::build($email,$url,$verification,$example,$reactivation));
        if($this->transport)$response=($this->transport)($payload);
        else{
            $curl=curl_init('https://api.brevo.com/v3/smtp/email');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['api-key: '.$this->settings['api_key'],'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
            $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);$response=['status'=>$status,'body'=>is_string($body)?json_decode($body,true):null];
        }
        if($this->receiptPath!==null){
            $receipt=['at'=>gmdate('c'),'kind'=>($reactivation?'reactivation':($verification?'verification':'recovery')).($example?'_example':''),'recipient_sha256'=>hash('sha256',strtolower($email)),'http_status'=>(int)($response['status']??0),'accepted'=>($response['status']??0)===201&&!empty($response['body']['messageId']),'provider_code'=>preg_replace('/[^a-zA-Z0-9_-]/','',substr((string)($response['body']['code']??''),0,80))];
            if(!is_link($this->receiptPath)){if(@file_put_contents($this->receiptPath,json_encode($receipt,JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX)!==false)@chmod($this->receiptPath,0600);else error_log('No se pudo guardar el comprobante local de correo.');}
        }
        if(($response['status']??0)!==201||empty($response['body']['messageId']))throw new RuntimeException('El proveedor no confirmó la solicitud de correo.');
    }
}
