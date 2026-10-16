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
        $live=[];$available=false;
        if($pdo!==null){
            try{
                $rows=$pdo->query("SELECT slug,department,publication_status FROM destinations WHERE country_code='CO' AND publication_status IN ('published','draft')")->fetchAll(PDO::FETCH_ASSOC);
                foreach($rows as $row){$key=self::normalized((string)$row['department']).'|'.$row['slug'];$live[$key]=(string)$row['publication_status'];}
                $available=true;
            }catch(Throwable){ /* Fail closed: unavailable is not zero publication. */ }
        }
        $departments=[];$countryWeights=0;$countryCovered=0;$countryCounts=['published'=>0,'in_progress'=>0,'pending'=>0,'unknown'=>0];
        foreach($this->plan['departments'] as $department){
            $places=[];$totalWeight=0;$publishedWeight=0;$counts=['published'=>0,'in_progress'=>0,'pending'=>0,'unknown'=>0];
            foreach($department['places'] as $place){
                $liveStatus=$live[self::normalized($department['name']).'|'.$place['slug']]??null;
                $status=$liveStatus==='published'?'published':(($liveStatus==='draft'||$place['preparing'])?'in_progress':($available?'pending':'unknown'));
                $counts[$status]++;$totalWeight+=(int)$place['weight'];if($status==='published')$publishedWeight+=(int)$place['weight'];
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
            $coverage=$available&&$totalWeight>0?round(100*$publishedWeight/$totalWeight,2):null;
            $departments[]=['code'=>$department['code'],'name'=>$department['name'],'catalog_count'=>count($places),'counts'=>$counts,'coverage_percent'=>$coverage,'places'=>$places,'municipalities'=>$municipalities];
            $countryWeights+=(int)$department['weight'];if($coverage!==null)$countryCovered+=$department['weight']*$coverage;
            foreach($counts as $state=>$count)$countryCounts[$state]+=$count;
        }
        return ['schema_version'=>1,'universe_version'=>$this->plan['universe_version'],'catalog_updated_at'=>$this->plan['catalog_updated_at'],'checked_at'=>gmdate('c'),'publication_source_available'=>$available,'country'=>['name'=>'Colombia','catalog_count'=>array_sum($countryCounts),'counts'=>$countryCounts,'coverage_percent'=>$available&&$countryWeights?round($countryCovered/$countryWeights,2):null],'departments'=>$departments];
    }
}
