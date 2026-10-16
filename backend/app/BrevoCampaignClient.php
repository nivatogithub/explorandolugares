<?php
declare(strict_types=1);

/** Marketing campaigns only. Never substitutes transactional email for marketing. */
final class BrevoCampaignClient
{
    public function __construct(private array $settings, private ?Closure $transport=null) {}

    public function assertReady(): void
    {
        if (($this->settings['enabled']??false)!==true || empty($this->settings['api_key']) || !filter_var($this->settings['sender_email']??'', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Completa y habilita la conexión con Brevo antes de continuar.');
        }
        if (!$this->transport && !function_exists('curl_init')) throw new RuntimeException('El servidor necesita habilitar cURL para conectar con Brevo.');
    }

    public function request(string $method,string $path,?array $payload=null,bool $allowMissing=false): array
    {
        $this->assertReady();
        if (!preg_match('~^/(?:contacts|emailCampaigns)(?:/[A-Za-z0-9_.%@+\-]+)*(?:\?limit=500)?$~D',$path)) throw new InvalidArgumentException('Operación de Brevo no válida.');
        if ($this->transport) $response=($this->transport)($method,$path,$payload);
        else {
            $curl=curl_init('https://api.brevo.com/v3'.$path);
            $options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,
                CURLOPT_HTTPHEADER=>['api-key: '.$this->settings['api_key'],'Content-Type: application/json','Accept: application/json'],
                CURLOPT_FOLLOWLOCATION=>false];
            if ($payload!==null) $options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR);
            curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
            $response=['status'=>$status,'body'=>is_string($body)?json_decode($body,true):null];
        }
        $status=(int)($response['status']??0);
        if ($allowMissing && $status===404) return ['missing'=>true];
        if ($status<200 || $status>=300) throw new RuntimeException('Brevo no confirmó la operación. Revisa la cuenta del proveedor antes de repetir una preparación o un envío.');
        return is_array($response['body']??null)?$response['body']:[];
    }

    public function syncContact(string $email,int $listId): void
    {
        $path='/contacts/'.rawurlencode($email);
        $contact=$this->request('GET',$path,null,true);
        if (!empty($contact['emailBlacklisted'])) throw new RuntimeException('Brevo tiene un destinatario excluido. Revisa la audiencia; no se reactivó su correo.');
        if (!empty($contact['missing'])) $this->request('POST','/contacts',['email'=>$email,'listIds'=>[$listId]]);
        else $this->request('PUT',$path,['listIds'=>[$listId]]);
        // Never send emailBlacklisted=false or rewrite existing attributes/consent.
    }
}
