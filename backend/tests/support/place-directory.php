<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/PlaceDirectoryService.php';

function check_place_directory(PDO $pdo):array
{
    $checks=0;$check=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks++;};
    $insert=$pdo->prepare('INSERT INTO destinations(slug,country_code,department,subregion,publication_status) VALUES(?,\'CO\',?,?,?)');
    $translation=$pdo->prepare('INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,\'\',\'\',\'Contenido sintético\',\'Descripción sintética\')');
    $ids=[];
    for($i=1;$i<=25;$i++){
        $slug=match($i){22=>'jerico',23=>'jardin',default=>'fixture-'.sprintf('%02d',$i)};
        $department=$i<=12||in_array($i,[22,23],true)?'Antioquia':'Cesar';$region=$department==='Cesar'?'Caribe':($i<=6?'Oriente':'Suroeste');
        $insert->execute([$slug,$department,$region,$i===24?'draft':'published']);$id=(int)$pdo->lastInsertId();$ids[$i]=$id;
        foreach(['es','en','pt-BR'] as $locale)if($i!==25||$locale==='es')$translation->execute([$id,$locale,match($i){22=>'Jericó',23=>'Jardín',25=>'Solo español',default=>'Lugar '.sprintf('%02d',$i)}]);
        if(in_array($i,[1,22,23],true))$pdo->prepare('INSERT INTO place_labels(place_id,code,enabled) VALUES(?,\'heritage_town\',1)')->execute([$id]);
    }
    // Two hero bindings must not duplicate a place or consume two positions in a page.
    foreach([1,2] as $n){
        $pdo->prepare('INSERT INTO media_assets(asset_path,creator,source_url,license_name,license_url,width,height) VALUES(?,\'Synthetic\',\'https://example.invalid\',\'Synthetic\',\'https://example.invalid\',800,533)')->execute(['assets/synthetic-'.$n.'.webp']);$media=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO destination_media(destination_id,media_asset_id,role,display_order) VALUES(?,?,\'hero\',?)')->execute([$ids[22],$media,$n]);
        foreach(['es','en','pt-BR'] as $locale)$pdo->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,\'Synthetic\',\'Synthetic\')')->execute([$media,$locale]);
    }
    $pdo->prepare('INSERT INTO place_labels(place_id,code,enabled) VALUES(?,\'heritage_town\',0)')->execute([$ids[2]]);
    $directory=new PlaceDirectoryService(new PlaceDirectorySelector($pdo));
    foreach(['es','en','pt-BR'] as $locale){
        $expected=$locale==='es'?24:23;$all=[];
        for($page=1;$page<=3;$page++){
            $data=$directory->search($locale,['pagina'=>(string)$page]);$check($data['total']===$expected&&$data['pages']===3,'Wrong total or page count.');
            $check(count($data['rows'])===min(9,$expected-9*($page-1)),'Incorrect page size.');
            $slugs=array_column($data['rows'],'slug');$check(!array_intersect($all,$slugs),'Repeated place between pages.');$all=[...$all,...$slugs];
            $check(!in_array('fixture-24',$slugs,true),'Draft leaked.');
        }
        $check(count($all)===$expected&&count(array_unique($all))===$expected,'Pagination lost or duplicated places.');
        foreach(['Jerico','Jericó','JERICO'] as $query){$data=$directory->search($locale,['q'=>$query]);$check(array_column($data['rows'],'slug')===['jerico'],'Accent/case-independent search failed.');}
        foreach(['%','_','nothing-matches',"' OR 1=1 --"] as $query)$check($directory->search($locale,['q'=>$query])['total']===0,'Wildcard or SQL input broadened the search.');
        $data=$directory->search($locale,['heritage'=>'1']);$check($data['total']===3&&count($data['rows'])===3,'Heritage filter ignored disabled/missing labels.');
        $data=$directory->search($locale,['department'=>'Antioquia']);$check($data['total']===14,'Department filter failed.');
        $check($data['regions']===['Caribe','Oriente','Suroeste'],'Department selection must never restrict regions.');
        $check($directory->search($locale,['region'=>'Caribe'])['departments']===['Cesar'],'Region must restrict departments.');
        $check($directory->search($locale,['region'=>'Oriente'])['departments']===['Antioquia'],'Subregion must restrict departments.');
        $data=$directory->search($locale,['region'=>'Oriente']);$check($data['total']===6,'Region filter failed.');
        $data=$directory->search($locale,['region'=>'Caribe','department'=>'Antioquia']);$check($data['total']===0&&$data['page']===1,'Combined empty result failed.');
        $data=$directory->search($locale,['heritage'=>'1','q'=>'j','region'=>'Suroeste','department'=>'Antioquia']);$check($data['total']===2,'Combined filters failed.');
        $data=$directory->search($locale,['pagina'=>'999999']);$check($data['page']===3&&$data['requestedPage']===999999,'Out-of-range page not clamped.');
        $data=$directory->search($locale,['pagina'=>'2','heritage'=>'1']);$check($data['page']===1&&$data['pages']===1,'Filter did not adjust page bounds.');
        $data=$directory->search($locale,['q'=>[],'region'=>[],'department'=>[],'heritage'=>[],'pagina'=>[]]);$check($data['total']===$expected&&$data['page']===1,'Malformed query parameters failed.');
    }
    $data=$directory->search('unknown',[]);$check($data['locale']==='es','Locale fallback failed.');
    $url=PlaceDirectoryService::url('pt-BR',['q'=>'Jericó & Jardín','department'=>'Antioquia','region'=>'Suroeste','heritage'=>true],2);
    parse_str(parse_url($url,PHP_URL_QUERY),$query);
    $check(str_starts_with($url,'/pt-br/lugares/?')&&$query===['q'=>'Jericó & Jardín','department'=>'Antioquia','region'=>'Suroeste','heritage'=>'1','pagina'=>'2'],'Page URL lost filters.');
    $check(PlaceDirectoryService::url('es',[],1)==='/lugares/','First page must keep its canonical path.');
    return ['checks'=>$checks,'driver'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME),'sql_page_size'=>9,'duplicate_hero_tested'=>true,'filters_and_languages'=>true];
}
