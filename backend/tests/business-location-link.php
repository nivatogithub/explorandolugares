<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessLocationLink.php';
function locationCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$business=['latitude'=>'5.7910000','longitude'=>'-75.7860000','display_name'=>'Negocio de prueba'];
foreach(['Windows NT','Android','iPhone'] as $agent){
    locationCheck(BusinessLocationLink::forBusiness($business,$agent)!=='', 'Stored coordinates must remain navigable.');
    $business['location_map_url']='https://maps.app.goo.gl/ExampleFixture';
    locationCheck(BusinessLocationLink::forBusiness(['location_map_url'=>$business['location_map_url']],$agent)===$business['location_map_url'], 'A reviewed map link without coordinates must be preserved.');
    unset($business['location_map_url']);
}
foreach(['javascript:alert(1)','https://www.google.com/maps/search/?api=1&query=5.791,-75.786','https://example.invalid/'] as $url){
    $business['location_map_url']=$url;
    locationCheck(BusinessLocationLink::forBusiness(['location_map_url'=>$url])==='', 'Unreviewed or unsafe fallback links must remain hidden.');
}
require __DIR__.'/../app/views/directory-helpers.php';
$business['plan_code']='page';$business['location_map_url']='https://maps.app.goo.gl/ExampleFixture';
locationCheck(str_contains(directoryPlanContacts($business,[],'es'),'maps/search/'), 'The business page must expose the stored coordinates.');
locationCheck(str_contains(directoryActions($business,[],'es'),'maps/search/'), 'The directory card must expose the stored coordinates.');
locationCheck(str_starts_with(BusinessLocationLink::forBusiness($business,'Android'),'geo:5.791,-75.786?q='),'Android must retain the device map handler.');
foreach(['iPhone','iPad','Macintosh Mobile'] as $agent)locationCheck(str_starts_with(BusinessLocationLink::forBusiness($business,$agent),'https://maps.apple.com/?ll='),'Apple devices must retain their map link.');
foreach([[null,null],['bad',1],[91,1],[1,-181],[INF,1],[1,NAN],[1,null]] as [$lat,$lon])locationCheck(BusinessLocationLink::forBusiness(['latitude'=>$lat,'longitude'=>$lon])==='','Invalid and incomplete coordinates must not generate a link.');
$registered=$business;$registered['location_map_url']='https://www.google.com/maps/search/?api=1&query=5.791%2C-75.786';
locationCheck(BusinessLocationLink::forBusiness($registered,'Windows NT')===$registered['location_map_url'],'Registration URL and stored point must agree.');
$business['preview_only']=true;
locationCheck(!str_contains(directoryPlanContacts($business,[],'es'),'href='), 'Private previews must remain non-navigable.');
echo "OK: registration points, desktop/Android/Apple links, both public consumers and private previews\n";
