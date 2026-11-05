<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessLocationLink.php';
function locationCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$business=['latitude'=>'5.7910000','longitude'=>'-75.7860000','display_name'=>'Negocio de prueba'];
foreach(['Windows NT','Android','iPhone'] as $agent){
    locationCheck(BusinessLocationLink::forBusiness($business,$agent)==='', 'Coordinates alone must not create a public map link.');
    $business['location_map_url']='https://maps.app.goo.gl/ExampleFixture';
    locationCheck(BusinessLocationLink::forBusiness($business,$agent)===$business['location_map_url'], 'A reviewed map link must be preserved.');
    unset($business['location_map_url']);
}
foreach(['javascript:alert(1)','https://www.google.com/maps/search/?api=1&query=5.791,-75.786','https://example.invalid/'] as $url){
    $business['location_map_url']=$url;
    locationCheck(BusinessLocationLink::forBusiness($business)==='', 'Unreviewed or unsafe links must remain hidden.');
}
require __DIR__.'/../app/views/directory-helpers.php';
$business['plan_code']='page';$business['location_map_url']='https://maps.app.goo.gl/ExampleFixture';
locationCheck(str_contains(directoryPlanContacts($business,[],'es'),'https://maps.app.goo.gl/ExampleFixture'), 'Directory must use the reviewed link.');
$business['preview_only']=true;
locationCheck(!str_contains(directoryPlanContacts($business,[],'es'),'href='), 'Private previews must remain non-navigable.');
echo "OK: reviewed business links, hidden unreviewed coordinates and private previews\n";
