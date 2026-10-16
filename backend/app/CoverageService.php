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
        $targets=[];
        foreach($this->plan['departments'] as $d)foreach($d['places'] as $p)if(($p['in_work_scope']??false)===true)$targets[]=[$d,$p];
        $scopeKnown=isset($this->plan['scope_version']);$targetPublished=0;$prepared=0.0;$depth=0.0;$publishable=0;
        foreach($targets as [$d,$p]){
            if(($live[self::normalized($d['name']).'|'.$p['slug']]??null)==='published')$targetPublished++;
            $prepared+=(float)($p['prepared_percent']??0);$depth+=(float)($p['depth_percent']??0);$publishable+=(int)($p['publishable']??false);
        }
        $n=count($targets);$ratio=static fn($v)=>$scopeKnown&&$n>0?round(100*$v/$n,2):null;
        $target=['status'=>$scopeKnown?'configured':'not_configured','scope_version'=>$this->plan['scope_version']??null,'denominator'=>$scopeKnown?$n:null,'published_count'=>$available&&$scopeKnown?$targetPublished:null,'published_percent'=>$available?$ratio($targetPublished):null,'prepared_percent'=>$scopeKnown&&$n?round($prepared/$n,2):null,'depth_percent'=>$scopeKnown&&$n?round($depth/$n,2):null,'publishable_percent'=>$ratio($publishable),'label'=>'Objetivo editorial P1–P2; no cobertura nacional completa','method'=>'Un lugar = una unidad; publicación según base y preparación/profundidad según evidencia del plan'];
        $registry=$this->plan['measurement_inventory']??[];
        $reference=['status'=>$registry?'historical_cartographic_reference':'not_configured','municipality_count'=>$registry?count($registry):null,'retained_category4_count'=>$registry?count(array_filter($registry,static fn($g)=>(int)($g['original_priority']??0)===4)):null,'label'=>'Inventario geográfico histórico de referencia; conserva categorías fuera de trabajos. No es censo vigente ni total de destinos turísticos.'];
        return ['schema_version'=>2,'editorial_target'=>$target,'measurement_reference'=>$reference,'estimated_destination_total'=>null,'national_scope_status'=>'defined','national_scope'=>'all_municipalities_and_distinct_tourist_places','audience'=>['status'=>'not_configured','source'=>null,'updated_at'=>null,'metrics'=>['anonymous_sessions'=>null,'estimated_visitors'=>null,'registered_travelers'=>null,'active_travelers'=>null,'business_views'=>null,'contact_clicks'=>null],'countries'=>null,'trend'=>null],'universe_version'=>$this->plan['universe_version'],'catalog_updated_at'=>$this->plan['catalog_updated_at'],'checked_at'=>gmdate('c'),'publication_source_available'=>$available,'country'=>['name'=>'Colombia','catalog_count'=>array_sum(array_map(static fn($d)=>$d['catalog_count'],$departments)),'counts'=>$countryCounts,'coverage_percent'=>null],'departments'=>$departments];
    }
}
