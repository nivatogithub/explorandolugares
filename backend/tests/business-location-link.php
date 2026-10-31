<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessLocationLink.php';
function locationCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$business=['latitude'=>'5.7910000','longitude'=>'-75.7860000','display_name'=>'Hotel & Café','location_map_url'=>'https://www.openstreetmap.org/?mlat=1&mlon=2'];
$desktop='https://www.google.com/maps/search/?api=1&query=5.791%2C-75.786';
foreach(['Windows NT 10.0','X11; Linux x86_64','Macintosh; Intel Mac OS X',''] as $agent)locationCheck(BusinessLocationLink::forBusiness($business,$agent)===$desktop,'Desktop web link must use stored coordinates, not the capture URL');
foreach(['Linux; Android 14; Mobile','Linux; Android 13; Tablet'] as $agent){
 $url=BusinessLocationLink::forBusiness($business,$agent);locationCheck(str_starts_with($url,'geo:5.791,-75.786?q='),'Android must delegate the point to the device');
 locationCheck(str_contains(rawurldecode($url),'Hotel & Café')&&!str_contains($url,'openstreetmap'),'Android label and provider independence');
}
foreach(['iPhone; CPU iPhone OS','iPad; CPU OS','Macintosh; Intel Mac OS X Mobile/15E148'] as $agent)locationCheck(str_starts_with(BusinessLocationLink::forBusiness($business,$agent),'https://maps.apple.com/?ll=5.791%2C-75.786&'),'Apple mobile link must use the same point');
locationCheck(str_starts_with(BusinessLocationLink::forBusiness(['latitude'=>0,'longitude'=>0],'Android'),'geo:0,0'),'Zero coordinates are valid');
foreach([['latitude'=>91,'longitude'=>0],['latitude'=>'','longitude'=>0],['latitude'=>0],['latitude'=>NAN,'longitude'=>0],['latitude'=>0,'longitude'=>INF]] as $invalid)locationCheck(BusinessLocationLink::forBusiness($invalid,'Android')==='','Invalid or partial coordinates must not create a false point');
locationCheck(BusinessLocationLink::forBusiness(['location_map_url'=>'javascript:alert(1)'],'Android')==='','Unsafe legacy URL must not become a link');
require __DIR__.'/../app/views/directory-helpers.php';
$_SERVER['HTTP_USER_AGENT']='Android';$business['plan_code']='page';
$html=directoryPlanContacts($business,[],'es');locationCheck(str_contains($html,'href="geo:5.791,-75.786'),'Directory uses the same native link');
$business['preview_only']=true;locationCheck(!str_contains(directoryPlanContacts($business,[],'es'),'href='),'Private previews must remain non-navigable');
echo "OK: Android phones/tablets, Apple mobile/desktop-mode iPad, desktop web fallback, exact coordinates, invalid values and private previews\n";
