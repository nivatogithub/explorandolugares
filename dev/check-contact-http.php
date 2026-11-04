<?php
declare(strict_types=1);
// Reuse the guarded dev environment and synthetic session helpers.
ob_start();require '/srv/state/check-account-auth-http.php';$previous=ob_get_clean();
$checks=[];
foreach(['guest','valid'] as $mode){
    layout_http_session($pdo,$account,$mode,function(string $session,string $csrf)use(&$checks,$mode):void{
        foreach(['es','en','pt-br'] as $locale){
            $route='/contact.php?lang='.$locale;
            foreach(['get','honeypot','csrf'] as $case){
                $post=match($case){'get'=>null,'honeypot'=>['csrf'=>$csrf,'website'=>'synthetic.invalid'],'csrf'=>['csrf'=>'invalid'],};
                [$status,$html,$headers]=auth_http_request($session,$route,$post);
                $expected=match($case){'get'=>200,'honeypot'=>400,'csrf'=>303};
                if($status!==$expected)throw new RuntimeException('Unexpected contact status: '.$mode.' '.$locale.' '.$case.' '.$status);
                if($case==='csrf'){
                    if(($headers['location']??'')!=='/negocios/acceso/?sesion=expirada')throw new RuntimeException('CSRF redirect changed.');
                    $checks[]=['mode'=>$mode,'locale'=>$locale,'case'=>$case,'status'=>$status];continue;
                }
                if(!str_contains($headers['cache-control']??'','no-store'))throw new RuntimeException('Missing private cache control.');
                $doc=new DOMDocument();$previousErrors=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.str_replace($csrf,'synthetic-csrf',$html),LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previousErrors);$xpath=new DOMXPath($doc);
                foreach(['//html[@lang="'.$locale.'"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main','//main//form[@method="post"]//input[@name="csrf" and @value="synthetic-csrf"]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Contact structure changed: '.$expression);
                if($case==='honeypot'&&$xpath->query('//p[@role="alert"]')->length!==1)throw new RuntimeException('Honeypot not rejected.');
                foreach(iterator_to_array($xpath->query('//@src | //@href | //@action')) as $attr)$attr->ownerElement->setAttribute($attr->name,auth_http_path($attr->value,$route));
                foreach(iterator_to_array($xpath->query('//text()')) as $node){$value=trim($node->nodeValue);if($value==='')$node->parentNode->removeChild($node);else $node->nodeValue=$value;}
                $checks[]=['mode'=>$mode,'locale'=>$locale,'case'=>$case,'status'=>$status,'canonical_dom_sha256'=>hash('sha256',$doc->saveHTML())];
            }
        }
    });
}
$report=['driver'=>'MariaDB','test_sessions_removed'=>true,'commercial_or_contact_rows_written'=>false,'production_accessed'=>false,'checks'=>$checks];
file_put_contents('/srv/state/contact-view-http-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo $previous.'OK HTTP/MariaDB: '.count($checks)." contact cases (reads and rejected requests); synthetic sessions removed.\n";
