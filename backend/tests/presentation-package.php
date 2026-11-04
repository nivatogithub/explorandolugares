<?php
declare(strict_types=1);
$package=$argv[1]??'';if(!is_file($package.'/build-manifest.json'))throw new RuntimeException('Expected a built package');
require $package.'/app/BusinessClaimPresentation.php';PortalPresentation::setPublicRoot($package.'/public');
$context=['locale'=>'en','legacy_admin'=>false,'csrf'=>'unchanged-test-token'];
$data=['places'=>[],'placeId'=>0,'categories'=>[],'category'=>'','name'=>'','results'=>[],
 'account'=>null,'error'=>'','success'=>false,'csrf'=>'unchanged-test-token','phoneNumber'=>'',
 'registerUrl'=>'/negocios/registro/','loginUrl'=>'/negocios/acceso/'];
ob_start();BusinessClaimPresentation::render($context,$data,$package.'/public');$html=ob_get_clean();
foreach(['data-portal-shell="1"','data-portal-footer="1"','noindex,nofollow','Local businesses','/assets/portal-shell.css?v='] as $expected)
 if(!str_contains($html,$expected))throw new RuntimeException('Missing packaged claim presentation: '.$expected);
if(substr_count($html,'data-portal-shell="1"')!==1)throw new RuntimeException('Duplicate header');
echo "OK: packaged claim page has one shared shell and explicit account presentation.\n";
