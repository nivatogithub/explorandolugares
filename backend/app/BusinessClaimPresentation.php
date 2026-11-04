<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/ContactPhoneFieldPresentation.php';
require_once __DIR__.'/BusinessClaimException.php';

/** Render a claim page from controller-supplied data. */
final class BusinessClaimPresentation
{
    public static function errorMessage(Throwable $error,string $locale):string
    {
        $fallback=$error instanceof PDOException?'No se pudo guardar la solicitud. Vuelve a intentarlo más tarde.':$error->getMessage();
        if($locale==='es')return $fallback;
        $key=$error instanceof PDOException?'error_storage':($error instanceof BusinessClaimException?'error_'.$error->reason:null);
        if($key===null)return $fallback;
        $copies=json_decode(file_get_contents(__DIR__.'/presentation/business-claim.json'),true,64,JSON_THROW_ON_ERROR);
        return $copies[$locale][$key]??$fallback;
    }
    public static function render(array $context,array $data,string $publicRoot):void
    {
        $locale=(string)$context['locale'];
        $copies=json_decode(file_get_contents(__DIR__.'/presentation/business-claim.json'),true,64,JSON_THROW_ON_ERROR);
        $copy=$copies[$locale]??$copies['es'];
        $e=static fn(mixed $value):string=>PortalPresentation::escape((string)$value);
        $text=static fn(string $key):string=>$e($copy[$key]);
        $asset=static fn(string $name):string=>'../assets/'.$name.'?v='.substr(hash_file('sha256',$publicRoot.'/assets/'.$name),0,12);
        $identity='/assets/portal-identity.css?v='.substr(hash_file('sha256',$publicRoot.'/assets/portal-identity.css'),0,12);
        $lower=static fn(string $value):string=>function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
        $prefix=$locale==='en'?'/en':($locale==='pt-br'?'/pt-br':'');
        $interestUrl=$prefix.'/negocios-locales/interes/';$privacyUrl=$prefix.'/privacidad/';
        $registerUrl=$data['registerUrl'];$loginUrl=$data['loginUrl'];
        $destinations=$data['places'];$destinationId=$data['placeId'];$categories=$data['categories'];
        $category=$data['category'];$name=$data['name'];$results=$data['results'];$account=$data['account'];
        $error=$data['error'];$success=$data['success'];$csrf=$data['csrf'];$phoneNumber=$data['phoneNumber'];
        require __DIR__.'/views/account/business-claim.php';
    }
}
