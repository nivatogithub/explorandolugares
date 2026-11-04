<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/PortalPageData.php';
require_once __DIR__.'/PrivacyPresentation.php';
require_once __DIR__.'/SubscriptionPricingPresentation.php';
require_once __DIR__.'/HomeAttractionsPresentation.php';
final class PortalPageRenderer
{
    public static function render(string $template,string $locale,string $type,string $publicRoot):void
    {
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
        if(is_string($path)&&str_ends_with($path,'/index.php')){
            $query=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY);header('Location: '.substr($path,0,-9).($query!==null?'?'.$query:''),true,301);return;
        }
        header('Cache-Control: no-cache, max-age=0, must-revalidate');$configFile=dirname(__DIR__).'/private/config.php';
        if(PHP_SAPI==='cli-server'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))$configFile=getenv('EL_LOCAL_CONFIG_PATH')?:$configFile;
        if($type==='privacy'){
            $privacy=PortalPageData::privacy($configFile);
            $language=match($locale){'en'=>'en','pt-BR'=>'pt-br',default=>'es'};
            readfile(__DIR__.'/presentation/privacy-shell/'.$language.'-before.html');
            PrivacyPresentation::render($privacy,$locale);
            readfile(__DIR__.'/presentation/privacy-shell/'.$language.'-after.html');
            return;
        }
        if($type==='subscriptions'||$type==='business-process'){
            $data=PortalPageData::pricing($configFile,$locale,$type==='subscriptions');
            $table=$data['table'];$operator=$data['operator'];
            $language=match($locale){'en'=>'en','pt-BR'=>'pt-br',default=>'es'};
            $base=__DIR__.'/presentation/commercial-shell/'.$language.'-'.$type.'-';
            readfile($base.'0.html');
            if($type==='subscriptions'){
                SubscriptionPricingPresentation::table($table);
                readfile($base.'1.html');
            }
            SubscriptionPricingPresentation::operator($operator);
            readfile($base.($type==='subscriptions'?'2':'1').'.html');
            return;
        }
        if($type==='home'){
            $content=PortalPageData::attractions($configFile,$locale,$publicRoot);
            $language=match($locale){'en'=>'en','pt-BR'=>'pt-br',default=>'es'};
            $base=__DIR__.'/presentation/home-shell/'.$language.'-';
            readfile($base.'before.html');
            HomeAttractionsPresentation::render($content,$base.'fallback.html');
            readfile($base.'after.html');
            return;
        }
        readfile($template);
    }
}
