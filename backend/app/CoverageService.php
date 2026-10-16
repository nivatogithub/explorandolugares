<?php
declare(strict_types=1);
/** Public read model: explicit fields only, no contact details, documents or editorial notes. */
final class CoverageService
{
    public function __construct(private array $plan, private array $geography) {}
    public static function configurationPath(): string { return dirname(__DIR__).'/private/config.php'; }
    private static function normalized(string $value): string {
        $plain=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        return strtolower(preg_replace('/[^a-zA-Z0-9]/','',$plain!==false?$plain:$value));
    }
    public function snapshot(?PDO $pdo): array {
        $live=[];$rows=[];$available=false;
        if($pdo!==null){
            try{
                $rows=$pdo->query("SELECT slug,department,publication_status FROM destinations WHERE country_code='CO' AND publication_status IN ('published','draft')")->fetchAll(PDO::FETCH_ASSOC);
                foreach($rows as $row){$key=self::normalized((string)$row['department']).'|'.$row['slug'];$live[$key]=(string)$row['publication_status'];}
                $available=true;
            }catch(Throwable){ /* Fail closed: unavailable is not zero publication. */ }
        }
        $departments=[];$countryCounts=['published'=>0,'in_progress'=>0,'pending'=>0,'unknown'=>0];
        foreach($this->plan['departments'] as $department){
            $places=[];$counts=['published'=>0,'in_progress'=>0,'pending'=>0,'unknown'=>0];
            foreach($department['places'] as $place){
                $liveStatus=$live[self::normalized($department['name']).'|'.$place['slug']]??null;
                $status=$liveStatus==='published'?'published':(($liveStatus==='draft'||$place['preparing'])?'in_progress':($available?'pending':'unknown'));
                $counts[$status]++;
                $places[]=['id'=>$place['id'],'name'=>$place['name'],'municipality_code'=>$place['municipality_code'],'parent_place_id'=>$place['parent_place_id'],'type'=>$place['type'],'status'=>$status,'url'=>$status==='published'?'/destinos/'.rawurlencode($place['slug']).'/':null];
            }
            $municipalities=[];
            foreach($this->geography as $unit){
                if($unit['department_code']!==$department['code'])continue;
                $related=array_values(array_filter($places,static fn($p)=>$p['municipality_code']===$unit['code']));
                $public=count(array_filter($related,static fn($p)=>$p['status']==='published'));
                $progress=count(array_filter($related,static fn($p)=>$p['status']==='in_progress'));
                $municipalities[]=['code'=>$unit['code'],'name'=>$unit['name'],'tracked_count'=>count($related),'published_count'=>$public,'status'=>$public>0?'published':($progress>0?'in_progress':(!$related?'untracked':($available?'pending':'unknown')))];
            }
            $allPublished=count(array_filter($rows,static fn($r)=>$r['publication_status']==='published'&&self::normalized($r['department'])===self::normalized($department['name'])));
            $counts['published']=$allPublished;
            $coverage=null;
            $departments[]=['code'=>$department['code'],'name'=>$department['name'],'catalog_count'=>count($places),'counts'=>$counts,'coverage_percent'=>$coverage,'places'=>$places,'municipalities'=>$municipalities];
            foreach($counts as $state=>$count)$countryCounts[$state]+=$count;
        }
        $countryCounts['published']=count(array_filter($rows,static fn($r)=>$r['publication_status']==='published'));
        return ['schema_version'=>2,'estimated_destination_total'=>null,'national_scope_status'=>'defined','national_scope'=>'all_municipalities_and_distinct_tourist_places','audience'=>['status'=>'not_configured','source'=>null,'updated_at'=>null,'metrics'=>['anonymous_sessions'=>null,'estimated_visitors'=>null,'registered_travelers'=>null,'active_travelers'=>null,'business_views'=>null,'contact_clicks'=>null],'countries'=>null,'trend'=>null],'universe_version'=>$this->plan['universe_version'],'catalog_updated_at'=>$this->plan['catalog_updated_at'],'checked_at'=>gmdate('c'),'publication_source_available'=>$available,'country'=>['name'=>'Colombia','catalog_count'=>array_sum(array_map(static fn($d)=>$d['catalog_count'],$departments)),'counts'=>$countryCounts,'coverage_percent'=>null],'departments'=>$departments];
    }
}
