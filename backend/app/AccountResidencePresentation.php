<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
/** Residence form data and rendering; catalog loading and request access stay with callers. */
final class AccountResidencePresentation
{
    public static function context(array $catalog,array $input,array $stored=[]):array
    {
        $values=[];
        foreach(['country_code','region_code','region_name','city_code','city_name'] as $key){
            $values[$key]=(string)($input['residence_'.$key]??$stored[$key]??($key==='country_code'?'CO':''));
        }
        $sortKey = static fn(string $name): string => strtr(mb_strtolower($name, 'UTF-8'), [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n~',
        ]);
        uasort($catalog['regions']['CO'], static fn(string $a, string $b): int =>
            strcmp($sortKey($a), $sortKey($b)));
        usort($catalog['municipalities'], static fn(array $a, array $b): int =>
            strcmp($sortKey($a['name']), $sortKey($b['name'])));
        return ['catalog'=>$catalog,'values'=>$values,'copy'=>['heading'=>'¿Dónde vives?','help'=>'Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.','country'=>'País']];
    }
    public static function render(array $residence, string $publicRoot):void
    {
        $file=$publicRoot.'/assets/account-residence.js';
        if(!is_file($file))throw new RuntimeException('Missing residence resource.');
        $residenceScriptUrl='/assets/account-residence.js?v='.substr(hash_file('sha256',$file),0,12);
        $e=static fn($value):string=>PortalPresentation::escape((string)$value);
        require __DIR__.'/views/account/residence-fields.php';
    }
}
