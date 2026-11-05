<?php
declare(strict_types=1);
require_once __DIR__.'/PlaceDirectorySelector.php';

/** Directory filters and page bounds; independent of request globals and HTML. */
final class PlaceDirectoryService
{
    public const PAGE_SIZE=9;
    public function __construct(private PlaceDirectorySelector $places) {}

    public function search(string $locale,array $input):array
    {
        if(!in_array($locale,['es','en','pt-BR'],true))$locale='es';
        $text=static fn(string $key):string=>is_string($input[$key]??null)?mb_substr(trim($input[$key]),0,120,'UTF-8'):'';
        $filters=['q'=>$text('q'),'department'=>$text('department'),'region'=>$text('region'),'heritage'=>($input['heritage']??'')==='1'];
        $sectors=$this->places->sectors();
        $filters['sector']=array_key_exists($text('sector'),$sectors)?$text('sector'):'';
        $members=$sectors[$filters['sector']]??[];
        $requested=$input['pagina']??'1';
        $requested=is_string($requested)&&ctype_digit($requested)&&strlen($requested)<9?max(1,min(1000000,(int)$requested)):1;
        $total=$this->places->count($locale,$filters);$pages=max(1,(int)ceil($total/self::PAGE_SIZE));$page=min($requested,$pages);
        $rows=$this->places->page($locale,$filters,self::PAGE_SIZE,($page-1)*self::PAGE_SIZE);
        $departments=[];$regions=[];
        foreach($this->places->options($locale) as $row){
            if((!$members || in_array($row['department'],$members,true)) && $row['department']!==''&&($filters['region']===''||$row['region']===$filters['region']))$departments[$row['department']]=true;
            if($row['region']!=='')$regions[$row['region']]=true;
        }
        $departments=array_keys($departments);$regions=array_keys($regions);sort($departments,SORT_NATURAL|SORT_FLAG_CASE);sort($regions,SORT_NATURAL|SORT_FLAG_CASE);
        return ['locale'=>$locale,'filters'=>$filters,'rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'requestedPage'=>$requested,'pageSize'=>self::PAGE_SIZE,
            'first'=>$total?($page-1)*self::PAGE_SIZE+1:0,'last'=>min($page*self::PAGE_SIZE,$total),'sectors'=>array_keys($sectors),'departments'=>$departments,'regions'=>$regions];
    }

    public static function url(string $locale,array $filters,int $page=1):string
    {
        $path=match($locale){'en'=>'/en/lugares/','pt-BR'=>'/pt-br/lugares/',default=>'/lugares/'};
        $query=[];foreach(['q','department','region','sector'] as $key)if(($filters[$key]??'')!=='')$query[$key]=$filters[$key];
        if(!empty($filters['heritage']))$query['heritage']='1';if($page>1)$query['pagina']=$page;
        return $path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
    }
}
