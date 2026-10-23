<?php
declare(strict_types=1);
require_once __DIR__.'/_portal-shell.php';
/** Route handlers process redirects, session, tokens and CSRF before composition. */
function account_page(bool $floating=false):void
{
    $locale=(string)($_GET['lang']??$_SESSION['account_locale']??'es');
    if(!in_array($locale,['es','en','pt-br'],true))$locale='es';
    $_SESSION['account_locale']=$locale;
    $accountAdmin=!empty($_SESSION['admin'])
        && !empty($_SESSION['admin_account_id'])
        && (int)$_SESSION['admin_account_id']===(int)($_SESSION['business_account_id']??0);
    $context=['locale'=>$locale,'legacy_admin'=>$accountAdmin,'csrf'=>(string)($_SESSION['csrf']??'')];
    $prefix=$locale==='es'?'':$locale.'/';
    $backdrop='';
    // Only the background content is a generated home fragment; shell/assets are explicit components.
    if($floating&&is_file(__DIR__.'/'.$prefix.'index.html')){
        $home=file_get_contents(__DIR__.'/'.$prefix.'index.html');
        if(preg_match('~<main\b[^>]*>.*?</main>~s',$home,$match)){
            $backdrop=preg_replace_callback('~\b(href|src|action)="([^"\s]+)"~',static function($m)use($prefix){
                if(preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i',$m[2]))return $m[0];
                return $m[1].'="/'.$prefix.$m[2].'"';
            },$match[0]);
        }
    }
    $shellHeader=PortalPresentation::header($context);$shellFooter=PortalPresentation::footer($context);$assets=PortalPresentation::assets();
    ob_start(static function(string $html)use($context,$floating,$locale,$backdrop,$shellHeader,$shellFooter,$assets):string{
        $html=PortalPresentation::translateAccount($html,$locale);
        if(!preg_match('~<head\b[^>]*>(.*?)</head>~s',$html,$head)||!preg_match('~<main\b[^>]*>.*?</main>~s',$html,$main))return $html;
        preg_match('~</main>(.*?)</body>~s',$html,$tail);
        $content=preg_replace('~<a\b[^>]*class="[^"]*\bbrand\b[^"]*"[^>]*>.*?</a>~s','',$main[0]);
        if($floating){
            $content=preg_replace('~^<main\b[^>]*>|</main>$~s','',$content);
            $content=$backdrop.'<dialog open class="account-login-dialog account-auth-dialog" aria-label="Tu cuenta" data-account-auth-dialog><button class="account-login-close" type="button" aria-label="Cerrar">×</button><div data-account-auth-content>'.$content.'</div></dialog>';
        }
        $head[1]=preg_replace('~<link\b[^>]*href="[^"]*assets/(?:style|identidad-neutral|header-controls|portal-shell|account-header|account-entry|account-page|cobertura/medicion)\.css(?:\?[^"]*)?"[^>]*>~','',$head[1]);
        return '<!doctype html><html lang="'.htmlspecialchars($locale,ENT_QUOTES).'"><head>'.PortalPresentation::injectAssets($head[1],$assets).'</head><body class="portal-account-page">'.$shellHeader.$content.$shellFooter.($tail[1]??'').'</body></html>';
    });
}
