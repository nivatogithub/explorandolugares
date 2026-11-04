<?php
declare(strict_types=1);
function reader_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}

/** Independent implementation: no inheritance from the SQL selector or connection. */
final class SnapshotPlaceReader implements PlaceReader
{
    public array $calls=[];
    public function __construct(private array $responses){}
    private function result(string $method,array $arguments):mixed
    {
        $this->calls[]=[$method,$arguments];$result=$this->responses[$method];
        if($result instanceof Throwable)throw $result;
        return $result;
    }
    public function findPublished(string $slug,string $locale,bool $preview=false):?array{return $this->result(__FUNCTION__,[$slug,$locale,$preview]);}
    public function availableLocales(int $placeId):array{return $this->result(__FUNCTION__,[$placeId]);}
    public function history(int $placeId,string $locale):array{return $this->result(__FUNCTION__,[$placeId,$locale]);}
    public function reasons(int $placeId,string $locale):array{return $this->result(__FUNCTION__,[$placeId,$locale]);}
    public function media(int $placeId,string $locale,string $role):?array{return $this->result(__FUNCTION__,[$placeId,$locale,$role])[$role];}
    public function sources(int $placeId,string $locale):array{return $this->result(__FUNCTION__,[$placeId,$locale]);}
    public function attractions(int $placeId,string $locale,bool $preview=false):array{return $this->result(__FUNCTION__,[$placeId,$locale,$preview]);}
    public function listPublished(string $locale):array{return $this->result(__FUNCTION__,[$locale]);}
}

function checkPlaceReaderContract(PDO $pdo):void
{
$pdo->beginTransaction();
try{
    $selector=new PlaceSelector($pdo);$baseline=PlaceRepository::fromDatabase($pdo);$cases=0;
    foreach(['es','en','pt-BR'] as $locale)foreach([false,true] as $preview){
        $record=$selector->findPublished('jerico',$locale,$preview);reader_check($record!==null,'Fixture requires Jericó.');$id=(int)$record['id'];
        $responses=['findPublished'=>$record,'availableLocales'=>$selector->availableLocales($id),'history'=>$selector->history($id,$locale),
            'reasons'=>$selector->reasons($id,$locale),'media'=>['hero'=>$selector->media($id,$locale,'hero'),'history'=>$selector->media($id,$locale,'history')],
            'sources'=>$selector->sources($id,$locale),'attractions'=>$selector->attractions($id,$locale,$preview),'listPublished'=>$selector->listPublished($locale)];
        $reader=new SnapshotPlaceReader($responses);
        $repository=new PlaceRepository($reader,new BusinessDirectorySelector($pdo),new ExperienceOperatorSelector($pdo));
        reader_check($repository->findPublished('jerico',$locale,$preview)===$baseline->findPublished('jerico',$locale,$preview),'Swapping the reader changed detail data.');
        reader_check($reader->calls===[['findPublished',['jerico',$locale,$preview]],['availableLocales',[$id]],['history',[$id,$locale]],
            ['reasons',[$id,$locale]],['media',[$id,$locale,'hero']],['media',[$id,$locale,'history']],['sources',[$id,$locale]],['attractions',[$id,$locale,$preview]]],
            'Reader arguments, locale, preview or operation order changed.');
        reader_check($repository->listPublished($locale)===$baseline->listPublished($locale),'Reader swap changed the directory.');
        $fallback=$repository->findPublished('jerico','unsupported',$preview);
        reader_check($reader->calls[count($reader->calls)-8]===['findPublished',['jerico','es',$preview]],'Locale fallback was not applied before calling the reader.');
        $duplicates=$responses;$duplicates['listPublished']=array_merge($responses['listPublished'],$responses['listPublished']);
        $duplicateRepository=new PlaceRepository(new SnapshotPlaceReader($duplicates),new BusinessDirectorySelector($pdo),new ExperienceOperatorSelector($pdo));
        reader_check($duplicateRepository->listPublished($locale)===$baseline->listPublished($locale),'Directory duplicates escaped canonical grouping.');
        $cases++;
    }
    // Missing data and failures must short-circuit before any dependent queries.
    $empty=new PDO('sqlite::memory:');$missing=new SnapshotPlaceReader(['findPublished'=>null]);
    $repository=new PlaceRepository($missing,new BusinessDirectorySelector($empty),new ExperienceOperatorSelector($empty));
    reader_check($repository->findPublished('missing','es')===null&&count($missing->calls)===1,'Missing place queried another component.');
    $failure=new RuntimeException('Synthetic reader unavailable');$reader=new SnapshotPlaceReader(['findPublished'=>$failure]);
    $repository=new PlaceRepository($reader,new BusinessDirectorySelector($empty),new ExperienceOperatorSelector($empty));
    $caught=null;try{$repository->findPublished('jerico','es');}catch(RuntimeException $error){$caught=$error;}
    reader_check($caught===$failure,'Infrastructure error was hidden as missing data.');
    echo "OK: interchangeable reader preserves six locale/scope models, directories, ordering, preview forwarding, locale fallback, deduplication, missing results and errors.\n";
}finally{$pdo->rollBack();}
}
