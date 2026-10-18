<?php
declare(strict_types=1);
/** Presentation only. Called after the route has processed redirects and tokens. */
function account_page(bool $floating=false):void
{
    $locale=(string)($_GET['lang']??$_SESSION['account_locale']??'es');if(!in_array($locale,['es','en','pt-br'],true))$locale='es';$_SESSION['account_locale']=$locale;
    $prefix=$locale==='es'?'':$locale.'/';$home=__DIR__.'/'.$prefix.'index.html';
    if(!is_file($home))return; // Source-only CLI fixtures do not contain the prepared portal.
    $portal=file_get_contents($home);
    $rootLinks=static fn(string $html):string=>preg_replace_callback('~\b(href|src|action)="([^"\s]+)"~',static function($m)use($prefix){
        if(preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i',$m[2]))return $m[0];
        return $m[1].'="/'.$prefix.$m[2].'"';
    },$html);
    $portal=$rootLinks($portal);
    ob_start(static function(string $html)use($portal,$floating,$locale):string{
        if(!preg_match('~<head\b[^>]*>(.*?)<[/]head>~s',$html,$head)||!preg_match('~<main\b[^>]*>.*?</main>~s',$html,$main))return $html;
        preg_match('~<header\b[^>]*>.*?</header>~s',$portal,$header);
        if($header)$header[0]=str_replace(' aria-current="page"','',$header[0]);
        preg_match('~<footer\b[^>]*>.*?</footer>~s',$portal,$footer);
        preg_match_all('~<link\b[^>]*rel="stylesheet"[^>]*>~',$portal,$styles);
        preg_match('~</main>(.*?)<[/]body>~s',$html,$tail);
        $content=preg_replace('~<a\b[^>]*class="[^"]*\bbrand\b[^"]*"[^>]*>.*?</a>~s','',$main[0]);
        if($floating){
            preg_match('~<main\b[^>]*>.*?</main>~s',$portal,$backdrop);
            $content=preg_replace('~^<main\b[^>]*>|</main>$~s','',$content);
            $content='<dialog open class="account-login-dialog account-auth-dialog" aria-label="Tu cuenta" data-account-auth-dialog><button class="account-login-close" type="button" aria-label="Cerrar">×</button><div data-account-auth-content>'.$content.'</div></dialog>';
            $content=($backdrop[0]??'').$content;
        }
        $css='/assets/account-page.css';
        return '<!doctype html><html lang="'.$locale.'"><head>'.$head[1].implode('',$styles[0]).'<link rel="stylesheet" href="/assets/account-header.css"><link rel="stylesheet" href="/assets/account-entry.css"><link rel="stylesheet" href="'.htmlspecialchars($css,ENT_QUOTES).'">'.'</'.'head><body class="portal-account-page">'.($header[0]??'').$content.($footer[0]??'').($tail[1]??'').'<script src="/assets/menu-principal.js" defer></script><script src="/assets/account-residence.js" defer></script><script src="/assets/select-flotante.js" defer></script><script src="/assets/account-entry.js" defer></script></'.'body></html>'; 
    });
}
