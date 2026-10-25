<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/HomeAttractions.php';
require_once __DIR__.'/PortalSettings.php';
require_once __DIR__.'/DatabaseClock.php';
final class PortalPageRenderer
{
    public static function render(string $template,string $locale,string $type,string $publicRoot):void
    {
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
        if(is_string($path)&&str_ends_with($path,'/index.php')){
            $query=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY);header('Location: '.substr($path,0,-9).($query!==null?'?'.$query:''),true,301);return;
        }
        $html=file_get_contents($template);header('Cache-Control: no-cache, max-age=0, must-revalidate');$configFile=dirname(__DIR__).'/private/config.php';
        if(PHP_SAPI==='cli-server'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))$configFile=getenv('EL_LOCAL_CONFIG_PATH')?:$configFile;
        if(!is_file($configFile)){echo $html;return;}
        try{
            $config=require $configFile;
            $overrides=PortalSettings::load(dirname($configFile));if(isset($overrides['privacy']))$config['privacy']=array_replace($config['privacy']??[],$overrides['privacy']);
            if($type==='home'){
                $pdo=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
                DatabaseClock::configure($pdo);
                $items=HomeAttractions::items($pdo,$locale,$publicRoot);
                if($items)$html=preg_replace_callback('~(<div\b[^>]*data-home-attractions[^>]*>).*?</div>~s',static fn($match)=>$match[1].HomeAttractions::render($items,$locale).'</div>',$html,1);
            }elseif($type==='privacy'){
                $privacy=$config['privacy']??[];$fields=['operator','contact','retention'];$content='';
                $labels=match($locale){'en'=>['Data controller: ','Privacy contact: ','Retention of submitted personal data: '],'pt-BR'=>['Responsável: ','Contato de privacidade: ','Conservação dos dados pessoais enviados: '],default=>['Responsable: ','Contacto de privacidad: ','Conservación de los datos personales enviados: ']};
                foreach($fields as $i=>$key){$value=$privacy[$key]??'';if($key==='retention')$value=PortalSettings::retention($privacy,strtolower($locale));if(is_scalar($value)&&trim((string)$value)!=='')$content.='<p>'.PortalPresentation::escape($labels[$i].$value).'</p>';}
                $html=str_replace('<div data-privacy-info></div>','<div data-privacy-info>'.$content.'</div>',$html);
            }
        }catch(Throwable $error){error_log('portal-public-fragment: unavailable');}
        echo $html;
    }
}
