<?php
declare(strict_types=1);
/** Public read model: explicit fields only, no contact details, documents or editorial notes. */
final class CoverageService
{
    public function __construct(private array $plan, private array $geography, private array $scope = []) {}
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
                // Internal drafts and planning flags never authorize a public announcement.
                if($liveStatus!=='published' && empty($place['public_announcement']))continue;
                $status=$liveStatus==='published'?'published':($available?'in_progress':'unknown');
                $counts[$status]++;
                $places[]=['id'=>$place['id'],'name'=>$place['name'],'municipality_code'=>$place['municipality_code'],'parent_place_id'=>null,'type'=>$place['type'],'status'=>$status,'url'=>$status==='published'?'/lugares/'.rawurlencode($place['slug']).'/':null];
            }
            // Resolve every live publication against the full geography, not just the initial target.
            // Explicit plan mappings retain independent places such as Cauca Viejo in Jericó.
            $aliases=['mompox'=>'13468','buga'=>'76111','la-playa-de-belen'=>'54398','pueblo-viejo'=>'47570',
                'santa-rosa-de-lima'=>'13683','manaure'=>'20443'];
            foreach($rows as $row){
                if($row['publication_status']!=='published'||self::normalized($row['department'])!==self::normalized($department['name']))continue;
                $url='/lugares/'.rawurlencode($row['slug']).'/';
                if(count(array_filter($places,static fn($p)=>$p['url']===$url))>0)continue;
                $matches=array_values(array_filter($this->geography,static fn($unit)=>
                    $unit['department_code']===$department['code'] &&
                    (self::normalized($unit['name'])===self::normalized($row['slug']) ||
                     $unit['code']===($aliases[$row['slug']]??null))));
                if(count($matches)!==1)continue; // Ambiguous/unresolved geography must never be guessed.
                $unit=$matches[0];
                $places[]=['id'=>'live-'.$department['code'].'-'.$row['slug'],'name'=>$unit['name'],
                    'municipality_code'=>$unit['code'],'parent_place_id'=>null,'type'=>'municipality',
                    'status'=>'published','url'=>$url];
            }
            $selectedCodes=array_column($this->scope['municipalities']??[],'code');
            $scoped=!empty($selectedCodes);
            foreach($this->scope['municipalities']??[] as $selected){
                if($selected['department_code']!==$department['code'])continue;
                if(count(array_filter($places,static fn($p)=>$p['municipality_code']===$selected['code']))>0)continue;
                $publishedRow=null;
                foreach($rows as $candidate){
                    if($candidate['publication_status']==='published' && self::normalized($candidate['department'])===self::normalized($department['name']) && self::normalized($candidate['slug'])===self::normalized($selected['name'])){$publishedRow=$candidate;break;}
                }
                $status=$publishedRow?'published':($available?'pending':'unknown');$counts[$status]++;
                $places[]=['id'=>'selected-'.$selected['code'],'name'=>$selected['name'],'municipality_code'=>$selected['code'],'parent_place_id'=>null,'type'=>'municipality','status'=>$status,'url'=>$publishedRow?'/lugares/'.rawurlencode($publishedRow['slug']).'/':null];
            }
            $municipalities=[];
            foreach($this->geography as $unit){
                if($unit['department_code']!==$department['code'])continue;
                $inScope=!$scoped||in_array($unit['code'],$selectedCodes,true);
                $related=array_values(array_filter($places,static fn($p)=>$p['municipality_code']===$unit['code']));
                $public=count(array_filter($related,static fn($p)=>$p['status']==='published'));
                $progress=count(array_filter($related,static fn($p)=>$p['status']==='in_progress'));
                $municipalities[]=['code'=>$unit['code'],'name'=>$unit['name'],'in_scope'=>$inScope,'tracked_count'=>count($related),'published_count'=>$public,'status'=>$public>0?'published':(!$inScope?'outside_scope':($progress>0?'in_progress':(!$related?'untracked':($available?'pending':'unknown'))))];
            }
            $allPublished=count(array_filter($rows,static fn($r)=>$r['publication_status']==='published'&&self::normalized($r['department'])===self::normalized($department['name'])));
            $counts['published']=$allPublished;
            $targetCount=count(array_filter($municipalities,static fn($m)=>$m['in_scope']));
            $covered=count(array_filter($municipalities,static fn($m)=>$m['in_scope']&&$m['published_count']>0));
            $coverage=$available&&$targetCount>0?round(100*$covered/$targetCount,2):null;
            $departments[]=['code'=>$department['code'],'name'=>$department['name'],'catalog_count'=>count($places),'counts'=>$counts,'coverage_percent'=>$coverage,'covered_municipalities'=>$covered,'municipality_total'=>$targetCount,'geographic_municipality_total'=>count($municipalities),'places'=>$places,'municipalities'=>$municipalities];
            foreach($counts as $state=>$count)$countryCounts[$state]+=$count;
        }
        $coveredTotal=array_sum(array_column($departments,'covered_municipalities'));
        $municipalityTotal=array_sum(array_column($departments,'municipality_total'));
        $countryCounts['published']=count(array_filter($rows,static fn($r)=>$r['publication_status']==='published'));
        return ['schema_version'=>2,'estimated_destination_total'=>null,'national_scope_status'=>'defined','national_scope'=>empty($this->scope)?'all_municipalities_and_distinct_tourist_places':'selected_tourism_municipalities','scope_version'=>$this->scope['version']??$this->plan['universe_version'],'audience'=>['status'=>'not_configured','source'=>null,'updated_at'=>null,'metrics'=>['anonymous_sessions'=>null,'estimated_visitors'=>null,'registered_travelers'=>null,'active_travelers'=>null,'business_views'=>null,'contact_clicks'=>null],'countries'=>null,'trend'=>null],'universe_version'=>$this->plan['universe_version'],'catalog_updated_at'=>$this->plan['catalog_updated_at'],'checked_at'=>gmdate('c'),'publication_source_available'=>$available,'country'=>['name'=>'Colombia','catalog_count'=>array_sum(array_map(static fn($d)=>$d['catalog_count'],$departments)),'counts'=>$countryCounts,'coverage_percent'=>$available&&$municipalityTotal>0?round(100*$coveredTotal/$municipalityTotal,2):null,'covered_municipalities'=>$coveredTotal,'municipality_total'=>$municipalityTotal,'coverage_basis'=>'municipalities_with_published_guide'],'departments'=>$departments];
    }
}
