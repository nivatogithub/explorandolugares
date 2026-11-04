<?php
declare(strict_types=1);
require_once __DIR__.'/PortalSettings.php';
require_once __DIR__.'/PortalPresentation.php';

/** Presents configured privacy information without interpreting page markup. */
final class PrivacyPresentation
{
    public static function render(array $privacy, string $locale): void
    {
        $labels=match($locale){
            'en'=>['Data controller: ','Privacy contact: ','Retention of submitted personal data: '],
            'pt-BR'=>['Responsável: ','Contato de privacidade: ','Conservação dos dados pessoais enviados: '],
            default=>['Responsable: ','Contacto de privacidad: ','Conservación de los datos personales enviados: '],
        };
        $lines=[];
        foreach(['operator','contact','retention'] as $index=>$key){
            $value=$key==='retention'?PortalSettings::retention($privacy,strtolower($locale)):($privacy[$key]??'');
            if(is_scalar($value)&&trim((string)$value)!=='')$lines[]=$labels[$index].$value;
        }
        require __DIR__.'/views/privacy-information.php';
    }
}
