<?php
declare(strict_types=1);
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/PortalPageRenderer.php';
PortalPresentation::setPublicRoot(__DIR__);
function portal_render_page(string $template,string $locale,string $type):void
{
    PortalPageRenderer::render($template,$locale,$type,__DIR__);
}
