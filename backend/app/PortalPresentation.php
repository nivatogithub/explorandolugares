<?php
declare(strict_types=1);
/** Shared HTML components for generated pages and request-time PHP pages. */
final class PortalPresentation
{
    private static ?string $publicRoot=null;
    public static function setPublicRoot(string $root):void {self::$publicRoot=$root;}
    private static function config(): array
    {
        static $config;
        return $config ??= json_decode(file_get_contents(__DIR__.'/presentation/shell.json'),true,32,JSON_THROW_ON_ERROR);
    }
    public static function brandLogo():string {return self::config()['logo'];}
    private static function resourceVersions():array
    {
        $path=(self::$publicRoot??dirname(__DIR__).'/public').'/asset-manifest.json';
        return is_file($path)?json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR):[];
    }
    /** The public home and account-dialog background share this definitive component. */
    public static function homeMain(string $locale):string
    {
        $locale=strtolower($locale);if(!in_array($locale,['es','en','pt-br'],true))$locale='es';
        $copy=json_decode(file_get_contents(__DIR__.'/presentation/home.json'),true,32,JSON_THROW_ON_ERROR)[$locale];
        $prefix=$locale==='es'?'':'/'.$locale;$versions=self::resourceVersions();$images=[];
        foreach(['local_life'=>'vida-local-concepto-v1.webp','city'=>'mundo-explorando-ciudad-v2.webp','adventure'=>'naturaleza-aventura-concepto-v2.webp','mountains'=>'mundo-explorando-ilustracion-v1.webp'] as $key=>$name){
            $path='/assets/'.$name;$images[$key]=$path.(isset($versions[$path])?'?v='.$versions[$path]:'');
        }
        $e=[self::class,'escape'];ob_start();require __DIR__.'/views/home-main.php';return ob_get_clean();
    }
    public static function escape(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function context(array $context=[]): array
    {
        $locale=(string)($context['locale']??'es');
        if(strtolower($locale)==='pt-br')$locale='pt-BR';
        if(!in_array($locale,['es','en','pt-BR'],true))$locale='es';
        $path=(string)($context['path']??parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH));
        $path=preg_replace('~^/(?:en|pt-br)(?=/)~','',$path);
        $path=preg_replace('~/index\.html$~','/',$path);
        if($path==='')$path='/';
        $path=preg_replace('~^/destinos(?=/|$)~','/lugares',$path);
        $prefix=$locale==='es'?'':($locale==='en'?'/en':'/pt-br');
        $private=str_ends_with($path,'.php') || preg_match('~^/(?:admin|business|negocios/(?:acceso|registro|portal|recuperar-clave|cambiar-clave))\b~',$path)===1;
        $languages=$context['languages']??[];
        if(!$languages&&str_starts_with($path,'/admin/'))$languages=['es'=>$path];
        if(!$languages)foreach(['es','en','pt-BR'] as $language){
            $languagePrefix=$language==='es'?'':($language==='en'?'/en':'/pt-br');
            $languages[$language]=$private?$path.($language==='es'?'':'?lang='.strtolower($language)):$languagePrefix.$path;
        }
        $navigation=[];
        $portalLabels=['es'=>'ES · Inicio','en'=>'EN · Home','pt-BR'=>'PT-BR · Início'];
        $portalDescriptions=['es'=>'Inicio del portal en español; esta página no tiene traducción a ese idioma','en'=>'English portal home; this page is not available in English','pt-BR'=>'Início do portal em português; esta página não está disponível em português'];
        foreach(['es'=>'/','en'=>'/en/','pt-BR'=>'/pt-br/'] as $language=>$home){
            $equivalent=isset($languages[$language]);
            $navigation[$language]=['href'=>$equivalent?$languages[$language]:$home,'equivalent'=>$equivalent,'label'=>$equivalent?null:$portalLabels[$language],'description'=>$equivalent?null:$portalDescriptions[$language]];
        }
        return ['locale'=>$locale,'path'=>$path,'prefix'=>$prefix,'languages'=>$languages,'language_navigation'=>$navigation,'copy'=>self::config()['copy'][$locale],'legacy_admin'=>!empty($context['legacy_admin']),'hide_account_language'=>!empty($context['hide_account_language']),'disable_contacts'=>!empty($context['disable_contacts']),'csrf'=>(string)($context['csrf']??'')];
    }
    public static function alternates(array $context=[]): array
    {
        $result=[];foreach(self::context($context)['languages'] as $locale=>$path)$result[$locale]=self::config()['origin'].'/'.ltrim($path,'/');
        return $result;
    }
    /** Emit resources in place; callers may request CSS or JS without parsing HTML. */
    public static function assets(bool $account=true,string $context='public',string $type='all'): string
    {
        if(!in_array($type,['all','css','js'],true))throw new InvalidArgumentException('Unknown resource type.');
        $versions=self::resourceVersions();
        $modules=json_decode(file_get_contents(__DIR__.'/presentation/resources.json'),true,32,JSON_THROW_ON_ERROR);
        $resources=$context==='admin'?array_merge($modules['admin']['css'],$modules['admin']['js']):array_merge($modules['common']['css'],$modules['common']['js']);
        if($context==='preview')$resources=$modules['common']['css'];
        if($account&&!in_array($context,['admin','preview'],true))$resources=array_merge($resources,$modules['account']['css'],$modules['account']['js']);
        $html='';
        foreach($resources as $name){
            if($type!=='all'&&!str_ends_with($name,'.'.$type))continue;
            $path='/assets/'.$name;$url=$path.(isset($versions[$path])?'?v='.$versions[$path]:'');
            $html.=str_ends_with($name,'.css')?'<link rel="stylesheet" href="'.self::escape($url).'">':'<script defer src="'.self::escape($url).'"'.($name==='cobertura/analytics.js'?' data-el-measurement="1"':'').'></script>';
        }
        return $html;
    }
    public static function header(array $context=[]): string
    {
        $c=self::context($context);$t=$c['copy'];$e=[self::class,'escape'];
        ob_start();
        require __DIR__.'/presentation/header.php';
        return ob_get_clean();
    }
    public static function footer(array $context=[]): string
    {
        $c=self::context($context);$t=$c['copy'];$e=[self::class,'escape'];
        ob_start();require __DIR__.'/presentation/footer.php';return ob_get_clean();
    }
}
