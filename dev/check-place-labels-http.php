<?php
declare(strict_types=1);
// Reuse the reserved native HTTP/session harness, including its 255 existing checks.
ob_start();require '/srv/state/check-business-payment-page-http.php';$regressionOutput=ob_get_clean();
$catalog=json_decode(file_get_contents('/srv/portal/app/content/release-places-businesses.json'),true,512,JSON_THROW_ON_ERROR);$labelChecks=[];
foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $locale=>$prefix){
    $seen=[];
    for($page=1;$page<=max(1,(int)ceil(count($catalog['public_places'])/9));$page++){
    $route=$prefix.'/lugares/'.($page>1?'?pagina='.$page:'');
    [$status,$html]=layout_http_request('',$route);if($status!==200)throw new RuntimeException('Place index failed.');
    $document=new DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($document);
    $cards=$xpath->query('//article[contains(@class,"destination-card")]');if($cards->length!==min(9,count($catalog['public_places'])-9*($page-1)))throw new RuntimeException('Missing or duplicate place cards.');
    foreach($cards as $card){
        $href=$xpath->query('.//h2/a',$card)->item(0)?->getAttribute('href');$slug=basename(trim((string)$href,'/'));$place=$catalog['editorial'][$slug]??null;
        if(!$place||$href!==$prefix.'/lugares/'.$slug.'/')throw new RuntimeException('Changed place identity or link.');
        if(in_array($slug,$seen,true))throw new RuntimeException('Repeated place across pages.');$seen[]=$slug;
        $region=$xpath->query('.//*[@data-place-label="region"]',$card);$heritage=$xpath->query('.//*[@data-place-label="heritage_town"]',$card);
        if($region->length!==1||$region->item(0)->textContent!==($place['subregion']?:$place['department']))throw new RuntimeException('Wrong or missing region: '.$slug);
        if($heritage->length!==(in_array('heritage_town',$place['labels']??[],true)?1:0))throw new RuntimeException('Incorrect heritage card label: '.$slug);
        if($heritage->length&&$heritage->item(0)->textContent!==['es'=>'Pueblo Patrimonio','en'=>'Heritage Town','pt-BR'=>'Povoado Patrimônio'][$locale])throw new RuntimeException('Missing label translation.');
    }
    $labelChecks[]=['route'=>$route,'http'=>200,'cards'=>$cards->length,'regions_and_heritage_labels'=>true];
    }
    $expected=$catalog['public_places'];sort($seen);sort($expected);if($seen!==$expected)throw new RuntimeException('Paginated directory lost a place.');
    foreach($catalog['public_places'] as $slug){
        [$status,$html]=layout_http_request('',$prefix.'/lugares/'.$slug.'/');if($status!==200)throw new RuntimeException('Place detail failed.');
        $document=new DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($document);
        $region=$xpath->query('//section[contains(@class,"destination-hero")]//*[@data-place-label="region"]');
        $heritage=$xpath->query('//section[contains(@class,"destination-hero")]//*[@data-place-label="heritage_town"]');
        $place=$catalog['editorial'][$slug];if($region->length!==1||$heritage->length!==(in_array('heritage_town',$place['labels']??[],true)?1:0))throw new RuntimeException('Detail labels differ: '.$slug);
        $labelChecks[]=['route'=>$prefix.'/lugares/'.$slug.'/','http'=>200,'shared_detail_labels'=>true];
    }
}
$configuration=require '/srv/state/private/config.php';$labelDb=new PDO($configuration['dsn'],$configuration['db_user'],$configuration['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
layout_http_session($labelDb,$account,'valid',function(string $session,string $csrf)use($labelDb,&$labelChecks):void{
    foreach(['jardin','jerico','santa-fe-de-antioquia','barranquilla'] as $slug){
        $q=$labelDb->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$slug]);$id=(int)$q->fetchColumn();
        [$status,$html]=layout_http_request($session,'/admin/places.php?id='.$id);if($status!==200)throw new RuntimeException('Label administration failed.');
        $document=new DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($document);
        $input=$xpath->query('//input[@name="heritage_town"]');
        if($input->length!==1||$input->item(0)->hasAttribute('checked')!==($slug!=='barranquilla')||$xpath->query('//input[@name="labels_submitted"]')->length!==1)throw new RuntimeException('Wrong administrative label state.');
        $labelChecks[]=['case'=>'admin-'.$slug,'http'=>200,'explicit_checkbox'=>true];
    }
});
file_put_contents('/srv/state/place-labels-http-report.json',json_encode(['checks'=>$labelChecks,'commercial_rows_written'=>false,'test_sessions_removed'=>true,'production_accessed'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo $regressionOutput.'OK: '.count($labelChecks)." native label checks after 255 account/admin regressions.\n";
