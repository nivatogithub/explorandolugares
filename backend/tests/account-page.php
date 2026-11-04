<?php
declare(strict_types=1);
require_once __DIR__.'/../app/BusinessClaimPresentation.php';
PortalPresentation::setPublicRoot(__DIR__.'/../public');
$context=['locale'=>'en','legacy_admin'=>false,'csrf'=>'test'];
$data=['places'=>[],'placeId'=>0,'categories'=>[],'category'=>'','name'=>'','results'=>[],
 'account'=>null,'error'=>'','success'=>false,'csrf'=>'test','phoneNumber'=>'',
 'registerUrl'=>'/negocios/registro/','loginUrl'=>'/negocios/acceso/'];
ob_start();BusinessClaimPresentation::render($context,$data,__DIR__.'/../public');$html=ob_get_clean();
foreach(['data-portal-shell="1"','data-portal-footer="1"','business-claim-filters','Local businesses','noindex,nofollow'] as $expected)
 if(!str_contains($html,$expected))throw new RuntimeException('Missing claim presentation: '.$expected);
if(substr_count($html,'data-portal-shell="1"')!==1)throw new RuntimeException('Duplicate header');
echo "OK: claim presentation uses prepared data and shared shell.\n";
