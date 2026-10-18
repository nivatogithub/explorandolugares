<?php
declare(strict_types=1);
/** Explicit read-only provider check. Never creates contacts, campaigns or emails. */
final class BrevoHealth
{
    public static function fingerprint(array $settings): string
    {
        return hash('sha256',json_encode(array_intersect_key($settings,array_flip(['api_key','enabled','sender_email','sender_name'])),JSON_THROW_ON_ERROR));
    }
    public static function check(array $settings,?Closure $transport=null): array
    {
        $result=['checked_at'=>gmdate('c'),'fingerprint'=>self::fingerprint($settings),'status'=>'error','checks'=>[]];
        $checks=&$result['checks'];
        if(($settings['enabled']??false)!==true||empty($settings['api_key'])||!filter_var($settings['sender_email']??'',FILTER_VALIDATE_EMAIL)){
            $checks['configuration']=['ok'=>false,'label'=>'Completa y habilita la conexión guardada.'];return $result;
        }
        $get=static function(string $path)use($settings,$transport):array{
            if($transport)return $transport($path);
            if(!function_exists('curl_init'))return ['status'=>0];
            $curl=curl_init('https://api.brevo.com/v3'.$path);
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['api-key: '.$settings['api_key'],'Accept: application/json']]);
            $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
            return ['status'=>$status,'body'=>is_string($raw)?json_decode($raw,true):null];
        };
        try{
            $account=$get('/account');$code=(int)($account['status']??0);
            $checks['api']=['ok'=>$code===200,'label'=>$code===200?'Credenciales aceptadas por Brevo':match($code){401,403=>'Brevo rechazó la clave o sus permisos',429=>'Brevo limitó las consultas; vuelve a comprobar más tarde',default=>'No se pudo comprobar la conexión con Brevo'}];
            if($code!==200)return $result;
            $relay=$account['body']['relay']['enabled']??null;
            $checks['relay']=['ok'=>$relay===true,'label'=>$relay===true?'Correo transaccional habilitado':($relay===false?'Brevo indica correo transaccional deshabilitado':'Brevo no informó si el correo transaccional está habilitado')];
            $senders=$get('/senders');$active=false;
            foreach(($senders['body']['senders']??[]) as $sender)if(strcasecmp((string)($sender['email']??''),$settings['sender_email'])===0&&($sender['active']??false)===true)$active=true;
            $checks['sender']=['ok'=>($senders['status']??0)===200&&$active,'label'=>($senders['status']??0)!==200?'No se pudo consultar el remitente':($active?'Remitente guardado activo en Brevo':'El remitente guardado no aparece activo en Brevo')];
            $domain=substr($settings['sender_email'],strrpos($settings['sender_email'],'@')+1);
            $domainInfo=$get('/senders/domains/'.rawurlencode($domain));$verified=($domainInfo['body']['verified']??false)===true&&($domainInfo['body']['authenticated']??false)===true;
            $checks['domain']=['ok'=>($domainInfo['status']??0)===200&&$verified,'label'=>($domainInfo['status']??0)!==200?'No se pudo comprobar la autenticación del dominio':($verified?'Dominio verificado y autenticado':'Dominio pendiente de verificar o autenticar')];
            $result['status']=count(array_filter($checks,static fn($c)=>!$c['ok']))===0?'ok':'warning';
        }catch(Throwable){$checks['connection']=['ok'=>false,'label'=>'No se pudo completar la comprobación del proveedor.'];}
        return $result;
    }
    public static function save(string $directory,array $result): void
    {
        if(is_link($directory)||is_link($directory.'/health.json'))throw new RuntimeException('Ruta de diagnóstico no válida.');
        if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('No se pudo guardar la comprobación.');
        $tmp=tempnam($directory,'.health-');if($tmp===false)throw new RuntimeException('No se pudo guardar la comprobación.');
        try{chmod($tmp,0600);$text=json_encode($result,JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$text,LOCK_EX)!==strlen($text)||!rename($tmp,$directory.'/health.json'))throw new RuntimeException('No se pudo guardar la comprobación.');}finally{if(is_file($tmp))unlink($tmp);}
    }
    public static function load(string $directory,array $settings): array
    {
        $none=['status'=>'unknown','checks'=>[],'checked_at'=>null];$path=$directory.'/health.json';
        if(is_link($directory)||!is_file($path)||is_link($path))return $none;
        try{$v=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);if(!is_array($v)||!isset($v['fingerprint'],$v['checked_at'],$v['checks'])||!is_array($v['checks'])||!in_array($v['status']??'', ['ok','warning','error'],true))return $none;
            $at=strtotime($v['checked_at']);if($at===false||$at>time()+60)return $none;
            if(!hash_equals(self::fingerprint($settings),(string)$v['fingerprint'])||time()-$at>900)$v['status']='stale';
            return $v;
        }catch(Throwable){return $none;}
    }
}
