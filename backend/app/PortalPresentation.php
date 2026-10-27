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
        return ['locale'=>$locale,'path'=>$path,'prefix'=>$prefix,'languages'=>$languages,'language_navigation'=>$navigation,'copy'=>self::config()['copy'][$locale],'legacy_admin'=>!empty($context['legacy_admin']),'csrf'=>(string)($context['csrf']??'')];
    }
    public static function translateAccount(string $html,string $locale): string
    {
        if($locale==='es')return $html;
        $copy=json_decode(file_get_contents(__DIR__.'/presentation/accounts.json'),true,64,JSON_THROW_ON_ERROR);
        $document=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($previous);
        $xpath=new DOMXPath($document);$column=$locale==='en'?0:1;
        foreach($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') as $node){
            $key=trim($node->nodeValue);if(isset($copy[$key]))$node->nodeValue=str_replace($key,$copy[$key][$column],$node->nodeValue);
        }
        foreach(iterator_to_array($document->childNodes) as $node)if($node->nodeType===XML_PI_NODE)$node->parentNode->removeChild($node);
        return $document->saveHTML();
    }
    public static function alternates(array $context=[]): array
    {
        $result=[];foreach(self::context($context)['languages'] as $locale=>$path)$result[$locale]=self::config()['origin'].'/'.ltrim($path,'/');
        return $result;
    }
    public static function assets(bool $account=true,string $context='public'): string
    {
        $manifestPath=(self::$publicRoot??dirname(__DIR__).'/public').'/asset-manifest.json';
        $versions=is_file($manifestPath)?json_decode(file_get_contents($manifestPath),true,64,JSON_THROW_ON_ERROR):[];
        $modules=json_decode(file_get_contents(__DIR__.'/presentation/resources.json'),true,32,JSON_THROW_ON_ERROR);
        $resources=$context==='admin'?array_merge($modules['admin']['css'],$modules['admin']['js']):array_merge($modules['common']['css'],$modules['common']['js']);
        if($context==='preview')$resources=$modules['common']['css'];
        if($account&&!in_array($context,['admin','preview'],true))$resources=array_merge($resources,$modules['account']['css'],$modules['account']['js']);
        $html='';
        foreach($resources as $name){
            $path='/assets/'.$name;$url=$path.(isset($versions[$path])?'?v='.$versions[$path]:'');
            $html.=str_ends_with($name,'.css')?'<link rel="stylesheet" href="'.self::escape($url).'">':'<script defer src="'.self::escape($url).'"'.($name==='cobertura/medicion.js'?' data-el-measurement="1"':'').'></script>';
        }
        return $html;
    }
    public static function injectAssets(string $head,string $assets): string
    {
        preg_match_all('~<link\b[^>]*>~',$assets,$css);$scripts=preg_replace('~<link\b[^>]*>~','',$assets);
        if(preg_match('~<link\b[^>]*rel="stylesheet"~',$head,$match,PREG_OFFSET_CAPTURE))$position=$match[0][1];else $position=strlen($head);
        return substr($head,0,$position).implode('',$css[0]).substr($head,$position).$scripts;
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
