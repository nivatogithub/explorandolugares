<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessPhone.php';
/** Shared form preparation; receives values explicitly and never reads a request. */
final class BusinessPhonePresentation
{
    public static function context(mixed $phoneValues,mixed $input=null):array
    {

        $rawPhone=(string)($phoneValues['phone']??$phoneValues['phone_fixed']??'');
        $digits=preg_replace('/\D/','',$rawPhone);
        if(strlen($digits)===12&&str_starts_with($digits,'57'))$digits=substr($digits,2);
        $fixed=str_starts_with($digits,'60');
        $wa=(string)($phoneValues['whatsapp']??'');
        $same=$rawPhone!==''&&preg_replace('/\D/','',$wa)===preg_replace('/\D/','',$rawPhone);
        $phoneForm=$input??[
          'type'=>$fixed?'fixed':'mobile',
          'area'=>$fixed?substr($digits,0,3):'604',
          'number'=>$fixed?substr($digits,3):$digits,
          'whatsapp'=>$wa,
          'same_whatsapp'=>$same?'1':'0',
        ];
        $area=(string)($phoneForm['area']??'604');
        if(!isset(BusinessPhone::AREAS[$area]))$area='604';
        return ['form'=>$phoneForm,'area'=>$area];
    }
    public static function render(array $phone):void
    {
        $phoneForm=$phone['form'];$area=$phone['area'];
        require __DIR__.'/views/account/business-phone.php';
    }
}
