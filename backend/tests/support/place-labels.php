<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/PlaceSelector.php';
require_once __DIR__.'/../../app/PlaceWriter.php';
require_once __DIR__.'/../../app/PlaceLabelPresentation.php';

/** Same behavior checks on SQLite and ordinary MariaDB tables. Fixture only. */
function check_place_labels(PDO $pdo,string $app,callable $migrate): array
{
    $checks=0;
    $check=static function(bool $ok,string $message)use(&$checks):void {if(!$ok)throw new RuntimeException($message);$checks++;};
    $places=[['barranquilla','Atlántico',''],['riohacha','La Guajira',' '],['valledupar','Cesar',''],['san-bernardo-del-viento','Córdoba',''],['jardin','Antioquia','Suroeste'],['jerico','Antioquia','Suroeste'],['santa-fe-de-antioquia','Antioquia','Occidente'],['cauca-viejo','Antioquia','Suroeste'],['future-place','Departamento <seguro>','']];
    $insert=$pdo->prepare('INSERT INTO destinations(slug,destination_type,country_code,department,subregion,publication_status,display_order,latitude,longitude) VALUES(?,\'municipality\',\'CO\',?,?,\'published\',1,5.79,-75.78)');
    $translation=$pdo->prepare('INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,\'\',\'\',\'Contenido sintético\',\'Descripción sintética\')');
    foreach($places as [$slug,$department,$region]){
        $insert->execute([$slug,$department,$region]);$id=(int)$pdo->lastInsertId();
        foreach(['es','en','pt-BR'] as $locale)$translation->execute([$id,$locale,$slug]);
    }
    $beforeIds=$pdo->query('SELECT id,slug FROM destinations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $migrate();
    $snapshot=static fn()=>[$pdo->query('SELECT * FROM destinations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),$pdo->query('SELECT * FROM place_labels ORDER BY place_id,code')->fetchAll(PDO::FETCH_ASSOC)];
    $once=$snapshot();$migrate();$check($once===$snapshot(),'Repeated label migration changed data.');
    $check($beforeIds===$pdo->query('SELECT id,slug FROM destinations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Place identities changed.');
    $reader=new PlaceSelector($pdo);$writer=new PlaceWriter($pdo);
    foreach(['es','en','pt-BR'] as $locale){
        $rows=$reader->listPublished($locale);$check(count($rows)===9,'Directory changed its cardinality.');
        foreach($rows as $row){
            $heritage=in_array($row['slug'],['jardin','jerico','santa-fe-de-antioquia'],true);
            $check((bool)$row['is_heritage_town']===$heritage,'Incorrect heritage designation: '.$row['slug']);
            if(in_array($row['slug'],['barranquilla','riohacha','valledupar','san-bernardo-del-viento'],true))$check($row['subregion']==='Caribe','Missing reviewed region.');
            $labels=PlaceLabelPresentation::labels($row,$locale);
            $check(count($labels)===($heritage?2:1),'Region/heritage labels must be independent.');
            if($heritage)$check($labels[1]['text']===['es'=>'Pueblo Patrimonio','en'=>'Heritage Town','pt-BR'=>'Povoado Patrimônio'][$locale],'Missing label translation.');
            if($row['slug']==='future-place'){
                $check($labels[0]['text']==='Departamento <seguro>','Missing department fallback.');
                $placeLabels=$labels;ob_start();require $app.'/views/place-labels.php';$html=ob_get_clean();
                $check(str_contains($html,'Departamento &lt;seguro&gt;')&&!str_contains($html,'Departamento <seguro>'),'Unescaped label markup.');
            }
        }
        $detail=$reader->findPublished('jerico',$locale);$check(!empty($detail['is_heritage_town']),'Detail lost stored label.');
    }
    $id=(int)$pdo->query("SELECT id FROM destinations WHERE slug='jerico'")->fetchColumn();
    $original=$pdo->query("SELECT * FROM place_labels WHERE place_id=$id")->fetch(PDO::FETCH_ASSOC);
    try{$writer->syncLabels($id,[]);throw new RuntimeException('Missing transaction accepted.');}catch(LogicException){$checks++;}
    $pdo->beginTransaction();$writer->syncLabels($id,[]);$pdo->commit();$migrate();
    $check(!$reader->findPublished('jerico','es')['is_heritage_town'],'Migration re-enabled a removed designation.');
    $pdo->beginTransaction();$writer->syncLabels($id,['heritage_town']);$pdo->rollBack();
    $check(!$reader->findPublished('jerico','es')['is_heritage_town'],'Label save did not roll back.');
    $pdo->beginTransaction();$writer->syncLabels($id,['heritage_town']);$pdo->commit();
    $check($pdo->query("SELECT * FROM place_labels WHERE place_id=$id")->fetch(PDO::FETCH_ASSOC)===$original,'Toggling lost provenance.');
    $pdo->exec("UPDATE destinations SET subregion='Edición posterior' WHERE slug='riohacha'");$migrate();
    $check($reader->findPublished('riohacha','es')['subregion']==='Edición posterior','Migration overwrote later regional content.');
    $check(count(PlaceLabelPresentation::labels(['slug'=>'jerico','subregion'=>'Suroeste'],'es'))===1,'Runtime inferred a designation from the slug.');
    $check(PlaceLabels::normalize(['heritage_town','heritage_town'])===['heritage_town'],'Duplicate label codes.');
    foreach(['heritage_town',['unknown'],[['heritage_town']]] as $bad){try{PlaceLabels::normalize($bad);throw new LogicException('Invalid label accepted.');}catch(RuntimeException){$checks++;}}
    return ['checks'=>$checks,'driver'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME),'migration_repeated'=>true,'place_ids_preserved'=>true,'disabled_labels_preserved'=>true,'later_region_edits_preserved'=>true,'languages'=>['es','en','pt-BR']];
}
