<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessPreviewLinks.php';
$url='https://example.invalid/?a=1&b=2';
if(BusinessPreviewLinks::attributes($url,true,true)!=='aria-disabled="true" tabindex="-1"')throw new RuntimeException('Preview can navigate or open another window');
if(BusinessPreviewLinks::attributes($url,false,true)!=='href="https://example.invalid/?a=1&amp;b=2" target="_blank" rel="noopener noreferrer"')throw new RuntimeException('Live public contact behavior changed');
if(BusinessPreviewLinks::attributes('/negocios/example/',false)!=='href="/negocios/example/"')throw new RuntimeException('Live public page link changed');
require __DIR__.'/../app/PortalPresentation.php';
$assets=PortalPresentation::assets(false,'preview');
if(str_contains($assets,'<script')||!str_contains($assets,'portal-tokens.css'))throw new RuntimeException('Preview must share design styles without account/navigation/measurement scripts');
echo "OK: inert preview links, escaped live contact URLs, unchanged live navigation and styles without active portal scripts.\n";
