<?php
declare(strict_types=1);
require_once __DIR__.'/_admin-layout.php';
/** Private shell for shared handlers that also have a public owner mode. */
function admin_page():void
{
    $assets=PortalPresentation::assets(false,'admin');
    ob_start();require __DIR__.'/_navigation.php';$navigation=ob_get_clean();
    $navigation=preg_replace('~href="(?!/|#|[a-z]+:)([^"]+)"~i','href="/admin/$1"',$navigation);
    ob_start(static function(string $html)use($assets,$navigation):string{
        $html=str_replace('</head>',$assets.'</head>',$html);
        $html=preg_replace('~<body(?:\s[^>]*)?>~','<body class="portal-admin">',$html,1);
        return preg_replace_callback('~<main\b([^>]*)>~',static function(array $m)use($navigation):string{
            $attributes=$m[1];
            if(preg_match('~class="([^"]*)"~',$attributes))$attributes=preg_replace('~class="([^"]*)"~','class="$1 admin-page"',$attributes,1);
            else $attributes.=' class="admin-page"';
            return '<main'.$attributes.'>'.$navigation;
        },$html,1);
    });
}
