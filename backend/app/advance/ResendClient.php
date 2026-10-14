<?php
declare(strict_types=1);
final class ResendClient {
    public function __construct(private array $config, private ?Closure $transport=null) {}
    public function assertReady(): void {
        if (($this->config['enabled']??false)!==true || !filter_var($this->config['from']??'',FILTER_VALIDATE_EMAIL)
            || empty($this->config['api_key'])) throw new RuntimeException('Resend no está habilitado o configurado.');
    }
    public function send(array $message, string $key): string {
        $this->assertReady();
        $payload=['from'=>$this->config['from'],'to'=>[$message['email']], 'subject'=>$message['subject'],'html'=>$message['html']];
        if ($this->transport) $result=($this->transport)($payload,$key);
        else {
            $curl=curl_init('https://api.resend.com/emails');
            curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->config['api_key'],'Content-Type: application/json','Idempotency-Key: '.$key],
                CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
            $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            $result=['status'=>$status,'body'=>is_string($body)?json_decode($body,true):null];
        }
        if (($result['status']??0)<200 || $result['status']>=300 || !is_string($result['body']['id']??null)) {
            // Nunca propagar cuerpos del proveedor ni credenciales al panel o logs.
            throw new RuntimeException('Resultado de envío incierto. Reconciliar en Resend antes de repetir.');
        }
        return substr($result['body']['id'],0,120);
    }
}
