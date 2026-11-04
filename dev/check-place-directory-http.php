<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-place-labels-http.php';$previousOutput=ob_get_clean();$directoryChecks=[];
function directory_http(string $route,int $expected=200):array
{
    [$status,$html,$headers]=auth_http_request('',$route);
    if($status!==$expected)throw new RuntimeException('Wrong directory HTTP status: '.$route.' / '.$status);
    $document=new DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($document);
    return [$xpath,$headers,$html];
}
foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $locale=>$prefix){
    $base=$prefix.'/lugares/';
    foreach([['pagina=2',9],['pagina=3',2],['q=Jerico',1],['heritage=1',3],['department=Cesar',1],['region=Caribe',6],['department=Cesar&heritage=1',0],['q=does-not-exist',0],['q=%25',0],['q%5B%5D=x&pagina%5B%5D=x',9]] as [$query,$count]){
        [$xpath,$headers,$html]=directory_http($base.'?'.$query);
        if($xpath->query('//article[@data-place-slug]')->length!==$count||$xpath->query('//form[@method="get"]')->length!==1||$xpath->query('//select[@name="region"]')->length!==1||$xpath->query('//select[@name="department"]')->length!==1||$xpath->query('//input[@name="heritage"]')->length!==1)throw new RuntimeException('Missing filter controls or wrong page size: '.$query);
        if($xpath->query('//html')->item(0)->getAttribute('lang')!==$locale)throw new RuntimeException('Wrong directory language.');
        if(str_contains($html,'places.js'))throw new RuntimeException('Directory depends on retired browser pagination.');
        $directoryChecks[]=['locale'=>$locale,'query'=>$query,'http'=>200,'cards'=>$count];
    }
    [$xpath]=directory_http($base.'?q=a');$next=$xpath->query('//nav[contains(@class,"place-directory-pagination")]/a[@rel="next"]')->item(0)?->getAttribute('href');
    if(!$next)throw new RuntimeException('Missing filtered next page.');parse_str(parse_url($next,PHP_URL_QUERY),$query);if($query!==['q'=>'a','pagina'=>'2'])throw new RuntimeException('Pagination lost filters.');
    [$nextXpath]=directory_http($next);if($nextXpath->query('//article[@data-place-slug]')->length<1)throw new RuntimeException('Filtered next page empty.');
    foreach(['es','en','pt-BR'] as $language){$url=$xpath->query('//link[@rel="alternate" and @hreflang="'.$language.'"]')->item(0)?->getAttribute('href');if(!$url||parse_url($url,PHP_URL_QUERY)!=='q=a')throw new RuntimeException('Language switch lost filters.');}
    $directoryChecks[]=['locale'=>$locale,'filtered_next_and_languages'=>true];
    [$xpath]=directory_http($base.'?region=Caribe');
    $departments=[];foreach($xpath->query('//select[@name="department"]/option[@value!=""]') as $option)$departments[]=$option->getAttribute('value');
    if($departments!==['Atlántico','Bolívar','Cesar','Córdoba','La Guajira','Magdalena'])throw new RuntimeException('Region must limit departments.');
    [$xpath]=directory_http($base.'?department=Cesar');
    if($xpath->query('//select[@name="region"]/option[@value="Oriente"]')->length!==1)throw new RuntimeException('Department incorrectly limits regions.');
    if($xpath->query('//details')->length||$xpath->query('//*[@data-remove-filter]')->length||$xpath->query('//a[@data-clear-filters]')->length!==1)throw new RuntimeException('Filters must be visible and clearable without chips.');
    $directoryChecks[]=['locale'=>$locale,'visible_filters_region_limits_departments'=>true];
    [$xpath,$headers]=directory_http($base.'?pagina=999999',302);if(($headers['location']??'')!==$base.'?pagina=3')throw new RuntimeException('Missing page-bound redirect.');
    [$xpath,$headers]=directory_http($base.'?heritage=1&pagina=2',302);if(($headers['location']??'')!==$base.'?heritage=1')throw new RuntimeException('Filtered page-bound redirect lost filter.');
    $directoryChecks[]=['locale'=>$locale,'invalid_page_redirects'=>true];
}
file_put_contents('/srv/state/place-directory-http-report.json',json_encode(['checks'=>$directoryChecks,'driver'=>'MariaDB','commercial_rows_written'=>false,'production_accessed'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo $previousOutput.'OK: '.count($directoryChecks)." native directory filter/pagination cases.\n";
