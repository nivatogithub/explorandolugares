<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';

/** Explicit contact presentation; request handling remains in the controller. */
final class ContactPresentation
{
    public static function render(array $context,array $data,string $publicRoot):void
    {
        PortalPresentation::setPublicRoot($publicRoot);
        $identity='/assets/portal-identity.css';
        $identity.='?v='.substr(hash_file('sha256',$publicRoot.$identity),0,12);
        $locale=(string)$context['locale'];
        $prefix=match($locale){'en'=>'/en','pt-br'=>'/pt-br',default=>''};
        $catalog=json_decode(file_get_contents(__DIR__.'/presentation/accounts.json'),true,64,JSON_THROW_ON_ERROR);
        $translate=static function(string $value)use($locale,$catalog):string{
            $key=trim($value);
            if($locale==='es'||!isset($catalog[$key]))return $value;
            return str_replace($key,$catalog[$key][$locale==='en'?0:1],$value);
        };
        $text=static fn(mixed $value):string=>is_array($value)?'Array':(string)$value;
        $e=static fn(mixed $value):string=>PortalPresentation::escape($text($value));
        $t=static fn(mixed $value):string=>$e($translate($text($value)));
        require __DIR__.'/views/account/contact.php';
    }
}
