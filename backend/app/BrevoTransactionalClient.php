<?php
declare(strict_types=1);
/** Transactional email only. Does not create contacts, lists or campaigns. */
final class BrevoTransactionalClient
{
    public function __construct(private array $settings,private ?Closure $transport=null){}
    public function ready():bool
    {
        return ($this->settings['enabled']??false)===true&&!empty($this->settings['api_key'])&&filter_var($this->settings['sender_email']??'',FILTER_VALIDATE_EMAIL)&&trim((string)($this->settings['sender_name']??''))!==''&&($this->transport||function_exists('curl_init'));
    }
    public function send(array $payload):array
    {
        if(!$this->ready())throw new RuntimeException('La conexión transaccional no está habilitada.');
        if($this->transport)return ($this->transport)($payload);
        $curl=curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['api-key: '.$this->settings['api_key'],'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        return ['status'=>$status,'body'=>is_string($body)?json_decode($body,true):null];
    }
}
