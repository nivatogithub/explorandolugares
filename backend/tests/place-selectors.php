<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PlaceRepository.php';

final class PlaceCountingPDO extends PDO
{
    public array $statements=[];
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        $this->statements[]=$query;return parent::prepare($query,$options);
    }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false
    {
        $this->statements[]=$query;
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
    }
}
function place_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$file=realpath($argv[1]??'');$local=realpath(dirname(__DIR__,2).'/.local');
if(!$file||!$local||!str_starts_with($file,$local.'/'))throw new RuntimeException('Usar una fixture SQLite aislada bajo .local.');
$pdo=new PlaceCountingPDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->beginTransaction();
try{
    foreach(['es','en','pt-BR'] as $locale){
        $pdo->statements=[];$page=PlaceRepository::fromDatabase($pdo)->findPublished('jerico',$locale);
        place_check($page!==null&&$page['locale']===$locale,'Real translation missing.');
        foreach($pdo->statements as $sql){
            place_check(str_starts_with(ltrim($sql),'SELECT'),'Reading a place executed a write.');
            place_check(!str_contains($sql,'provenance_json')&&!str_contains($sql,'internal_research_json'),'Private fields were fetched.');
        }
        place_check(!array_key_exists('internal_research_json',$page),'Internal research exposed.');
        foreach($page['places'] as $attraction)foreach($attraction['media'] as $media)place_check(!array_key_exists('provenance_json',$media),'Private media provenance exposed.');
    }
    $service=PlaceRepository::fromDatabase($pdo);
    place_check($service->findPublished("jerico' OR 1=1 --",'es')===null,'Slug input changed the query scope.');
    place_check($service->findPublished('jerico','unknown')===$service->findPublished('jerico','es'),'Locale compatibility changed.');
    $place=$service->findPublished('jerico','es');$id=(int)$place['id'];
    $pdo->prepare('UPDATE destinations SET publication_status="draft" WHERE id=?')->execute([$id]);
    place_check($service->findPublished('jerico','es')===null,'A draft became public.');
    place_check($service->findPublished('jerico','es',true)!==null,'Private draft data disappeared.');
    $pdo->prepare('UPDATE destinations SET publication_status="published" WHERE id=?')->execute([$id]);

    $pdo->statements=[];
    place_check((new BusinessDirectorySelector($pdo))->publicContacts([])===[],'Empty contact selection changed.');
    place_check((new BusinessDirectorySelector($pdo))->planFeatures([])===[],'Empty plan selection changed.');
    place_check((new ExperienceOperatorSelector($pdo))->activeForExperiences([])===[],'Empty experience selection changed.');
    place_check(!$pdo->statements,'An empty batch queried the database.');

    $pdo->statements=[];PlaceRepository::fromDatabase($pdo)->findPublished('jerico','es');$before=count($pdo->statements);
    $insert=$pdo->prepare('INSERT INTO points_of_interest(destination_id,slug,poi_type,publication_status,display_order) VALUES(?,?,"experience","published",?)');
    $translation=$pdo->prepare('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,"es",?,?)');
    for($i=0;$i<24;$i++){
        $insert->execute([$id,'selector-fixture-'.$i,1000+$i]);$pointId=(int)$pdo->lastInsertId();
        $translation->execute([$pointId,'Experiencia sintética '.$i,'Solo dentro de esta transacción de prueba.']);
    }
    $pdo->statements=[];$expanded=PlaceRepository::fromDatabase($pdo)->findPublished('jerico','es');$after=count($pdo->statements);
    place_check(count($expanded['places'])===count($place['places'])+24,'The expanded fixture was not read.');
    place_check($before===$after,'Queries increased with the number of experiences.');
    place_check(count(array_filter($pdo->statements,static fn($sql)=>str_contains($sql,'FROM business_experience_subscriptions x')))===1,'Operator placements were not selected in one batch.');
    echo 'OK: lugares in three languages, read-only/private-data boundaries, locale/slug/draft scopes, empty batches and '.$before.' queries both before and after adding 24 experiences.' . "\n";
}finally{$pdo->rollBack();}
