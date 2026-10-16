<?php
declare(strict_types=1);

final class BrevoSettings
{
    public static function load(string $directory,string $appKey): array
    {
        if(is_link($directory))throw new RuntimeException('La carpeta privada no es válida.');
        $path=$directory.'/settings.json';
        if (!is_file($path)) return ['enabled'=>false,'api_key'=>''];
        if (is_link($path)) throw new RuntimeException('La configuración privada de Brevo no es válida.');
        $data=json_decode((string)file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Revisa la configuración privada de Brevo.');
        $secret=base64_decode((string)($data['secret']??''),true);
        $data['api_key']='';
        if (is_string($secret) && strlen($secret)>28) {
            $key=openssl_decrypt(substr($secret,28),'aes-256-gcm',hash('sha256',$appKey,true),OPENSSL_RAW_DATA,substr($secret,0,12),substr($secret,12,16),'explorando-brevo-v1');
            if (!is_string($key)) throw new RuntimeException('No se pudo abrir la conexión guardada. Vuelve a configurar la llave de Brevo.');
            $data['api_key']=$key;
        }
        unset($data['secret']);return $data;
    }

    public static function save(string $directory,string $appKey,array $input): void
    {
        if (strlen($appKey)<32) throw new RuntimeException('Falta la llave privada de la aplicación.');
        $current=self::load($directory,$appKey);$data=[];
        foreach (['sender_email'=>254,'sender_name'=>120,'reply_to'=>254,'sender_address'=>500] as $field=>$limit) {
            $value=is_string($input[$field]??null)?trim($input[$field]):'';
            if (strlen($value)>$limit || preg_match('/[\x00-\x1f\x7f]/',$value)) throw new RuntimeException('Revisa los datos del remitente.');
            $data[$field]=$value;
        }
        foreach (['sender_email','reply_to'] as $field) if ($data[$field]!==''&&!filter_var($data[$field],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Revisa los correos del remitente y de respuesta.');
        $data['enabled']=($input['enabled']??'')==='1';
        $key=is_string($input['api_key']??null)?trim($input['api_key']):'';
        if ($key==='') $key=(string)($current['api_key']??'');
        if (strlen($key)>1000 || preg_match('/[\x00-\x20\x7f]/',$key)) throw new RuntimeException('Revisa la llave de API.');
        if ($data['enabled']&&($key===''||$data['sender_email']===''||$data['sender_name']===''||$data['sender_address']==='')) throw new RuntimeException('Para habilitar Brevo completa la llave, el remitente y su dirección.');
        $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($key,'aes-256-gcm',hash('sha256',$appKey,true),OPENSSL_RAW_DATA,$nonce,$tag,'explorando-brevo-v1');
        if (!is_string($cipher)) throw new RuntimeException('No se pudo proteger la llave de Brevo.');
        $data['secret']=base64_encode($nonce.$tag.$cipher);
        if (is_link($directory)) throw new RuntimeException('La carpeta privada no es válida.');
        if (!is_dir($directory)&&!mkdir($directory,0700,true)) throw new RuntimeException('No se pudo preparar la configuración privada.');
        $temporary=tempnam($directory,'.brevo-');
        if (!is_string($temporary)) throw new RuntimeException('No se pudo guardar la conexión.');
        try {chmod($temporary,0600);$json=json_encode($data,JSON_THROW_ON_ERROR);if(file_put_contents($temporary,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('No se pudo guardar la conexión.');if(!rename($temporary,$directory.'/settings.json'))throw new RuntimeException('No se pudo activar la conexión.');}
        finally {if(is_file($temporary))unlink($temporary);}
    }
}
