<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/AccountResidencePresentation.php';

/** Profile presentation receives prepared data; account actions stay in the controller. */
final class AccountProfilePresentation
{
    private const TEXTS=[
        'name'=>'Nombre','email'=>'Correo electrónico','residence_heading'=>'¿Dónde vives?',
        'residence_help'=>'Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.',
        'country'=>'País',
    ];

    public static function render(array $context,array $data,string $publicRoot):void
    {
        $locale=(string)$context['locale'];$column=$locale==='en'?0:1;
        $catalog=json_decode(file_get_contents(__DIR__.'/presentation/accounts.json'),true,64,JSON_THROW_ON_ERROR);$copy=[];
        foreach(self::TEXTS as $key=>$source)$copy[$key]=$locale==='es'?$source:($catalog[$source][$column]??$source);
        $data['residence']['copy']=['heading'=>$copy['residence_heading'],'help'=>$copy['residence_help'],'country'=>$copy['country']];
        $resourceUrls=[];
        // Preserve the existing final identity stylesheet and cascade during extraction.
        foreach(['portal'=>'account-portal.css','registration'=>'account-registration.css','identity'=>'portal-identity.css'] as $key=>$name){
            $file=$publicRoot.'/assets/'.$name;
            if(!is_file($file))throw new RuntimeException('Missing profile resource.');
            $resourceUrls[$key]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,12);
        }
        $e=[PortalPresentation::class,'escape'];
        require __DIR__.'/views/account/profile.php';
    }

    public static function renderLogout(string $csrf):void
    {
        $e=[PortalPresentation::class,'escape'];
        require __DIR__.'/views/account/profile-logout.php';
    }
}
