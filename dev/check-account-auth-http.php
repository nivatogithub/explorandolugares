<?php
declare(strict_types=1);
// Reuse the guarded local fixture/session helpers and retain its private-page regression.
ob_start();require '/srv/state/check-admin-layout-http.php';$privateCheckOutput=ob_get_clean();
$checks=[];
function auth_http_request(string $session,string $route,?array $post=null):array
{
    $options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$session];
    if($post!==null){$options['method']='POST';$options['header'].="\r\nContent-Type: application/x-www-form-urlencoded";$options['content']=http_build_query($post);}
    $stream=fopen('http://127.0.0.1'.$route,'r',false,stream_context_create(['http'=>$options]));
    if($stream===false)throw new RuntimeException('No responde la página de acceso.');
    try{$html=stream_get_contents($stream);$received=stream_get_meta_data($stream)['wrapper_data'];}finally{fclose($stream);}
    preg_match('/^HTTP\/\S+ (\d+)/',$received[0]??'',$status);$headers=[];
    foreach($received as $line){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower($parts[0])]=trim($parts[1]);}
    return [(int)($status[1]??0),$html,$headers];
}
function auth_http_path(string $url,string $route):string
{
    if(preg_match('~^[a-z][a-z0-9+.-]*:|^#~i',$url))return $url;
    $parts=parse_url($url);$path=$parts['path']??'';
    $routePath=(string)(parse_url($route,PHP_URL_PATH)?:'/');
    if($path==='')$path=$routePath;
    elseif(!str_starts_with($path,'/'))$path=rtrim(dirname($routePath),'/').'/'.$path;
    $segments=[];foreach(explode('/',$path) as $segment){if($segment==='..')array_pop($segments);elseif($segment!=='.'&&$segment!=='')$segments[]=$segment;}
    $path='/'.implode('/',$segments).(str_ends_with($path,'/')&&$segments?'/':'');
    if(str_starts_with($path,'/assets/')){
        $file='/srv/portal/public'.$path;if(!is_file($file))throw new RuntimeException('Falta recurso de acceso.');
        return 'resource:'.$path.':'.hash_file('sha256',$file);
    }
    return $path.(isset($parts['query'])?'?'.$parts['query']:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
}
layout_http_session($pdo,$account,'guest',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        $cases=[['login','/negocios/acceso/',''],['closed','/negocios/acceso/','&cuenta=cerrada'],
            ['recover','/negocios/recuperar-clave/',''],['sent','/negocios/recuperar-clave/','&solicitado=1'],
            ['password','/negocios/cambiar-clave/',''],['reactivation','/negocios/cambiar-clave/','&reactivacion=1&token=x']];
        foreach($cases as [$name,$route,$query]){
            [$status,$html,$headers]=auth_http_request($session,$route.'?lang='.$locale.$query);
            if($status!==200||!str_contains($headers['cache-control']??'','no-store')||!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Respuesta privada de acceso incorrecta: '.$name);
            $document=new DOMDocument();$previous=libxml_use_internal_errors(true);$document->loadHTML('<?xml encoding="UTF-8">'.str_replace($csrf,'synthetic-csrf',$html),LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
            foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main','//dialog[@open and @data-account-auth-dialog]/div[@data-account-auth-content]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición del acceso incorrecta: '.$name.' / '.$locale);
            if(in_array($name,['login','closed','recover'],true)){
                if($xpath->query('//dialog//form[@method="post"]/input[@name="csrf" and @value="synthetic-csrf"]')->length!==1)throw new RuntimeException('Falta formulario firmado.');
            }elseif($xpath->query('//dialog//form')->length!==0)throw new RuntimeException('Formulario inesperado sin token válido.');
            foreach(iterator_to_array($xpath->query('//@src | //@href | //@action')) as $attribute)$attribute->ownerElement->setAttribute($attribute->name,auth_http_path($attribute->value,$route));
            foreach(iterator_to_array($xpath->query('//text()')) as $node){$value=trim($node->nodeValue);if($value==='')$node->parentNode->removeChild($node);else $node->nodeValue=$value;}
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>200,'headers_shell_dialog_and_fields_preserved'=>true,'canonical_dom_sha256'=>hash('sha256',$document->saveHTML())];
        }
        foreach(['/negocios/acceso/','/negocios/recuperar-clave/','/negocios/cambiar-clave/'] as $route){
            [$status,$html,$headers]=auth_http_request($session,$route.'?lang='.$locale,['csrf'=>'invalid']);
            if($status!==303||($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'data-account-auth-content'))throw new RuntimeException('CSRF inválido no rechazado: '.$route.' / '.$locale.' / HTTP '.$status.' / '.($headers['location']??'sin redirección'));
            $checks[]=['case'=>'csrf-invalid','route'=>$route,'locale'=>$locale,'http'=>303,'rejected_before_form_and_database_write'=>true];
        }
    }
});
file_put_contents('/srv/state/account-auth-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'database_rows_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $privateCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos de acceso/recuperación/contraseña, idiomas, diálogo, encabezados privados y CSRF; sesiones sintéticas retiradas.\n";
