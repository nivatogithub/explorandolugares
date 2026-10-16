<?php
declare(strict_types=1);
require_once __DIR__.'/CoverageCatalogService.php';
/** First-party, opt-in measurements. No raw IP, URL query, email or contact values are persisted. */
final class AnalyticsService {
    public function __construct(private PDO $pdo,private string $key) {}
    public function ready():bool {try{return (string)$this->pdo->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='enabled'")->fetchColumn()==='1';}catch(Throwable){return false;}}
    public function install(array $plan,array $geography):void {
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $ddl=[
          'el_measurement_settings'=>"setting_key VARCHAR(64) PRIMARY KEY,setting_value TEXT NOT NULL",
          'el_measurement_sessions'=>"session_hash VARCHAR(64) PRIMARY KEY,visitor_hash VARCHAR(64) NOT NULL,started_at VARCHAR(19) NOT NULL,last_seen_at VARCHAR(19) NOT NULL,country_code VARCHAR(2) NOT NULL,source_channel VARCHAR(24) NOT NULL,account_kind VARCHAR(16) NOT NULL,account_hash VARCHAR(64) NULL",
          'el_measurement_events'=>"id $id,event_key VARCHAR(64) NOT NULL UNIQUE,session_hash VARCHAR(64) NOT NULL,occurred_at VARCHAR(19) NOT NULL,event_day VARCHAR(10) NOT NULL,event_name VARCHAR(32) NOT NULL,path VARCHAR(400) NOT NULL,department_code VARCHAR(2) NULL,municipality_code VARCHAR(5) NULL,destination_slug VARCHAR(160) NULL,business_public_id VARCHAR(64) NULL,channel VARCHAR(24) NULL",
          'el_measurement_limits'=>"bucket_key VARCHAR(64) PRIMARY KEY,hits INTEGER NOT NULL,expires_at VARCHAR(19) NOT NULL",
          'el_coverage_departments'=>"code VARCHAR(2) PRIMARY KEY,name VARCHAR(180) NOT NULL,priority INTEGER NOT NULL,weight INTEGER NOT NULL",
          'el_coverage_places'=>"id VARCHAR(200) PRIMARY KEY,department_code VARCHAR(2) NOT NULL,name VARCHAR(180) NOT NULL,slug VARCHAR(160) NOT NULL,municipality_code VARCHAR(5) NOT NULL,parent_place_id VARCHAR(200) NULL,place_type VARCHAR(32) NOT NULL,priority INTEGER NOT NULL,weight INTEGER NOT NULL,preparing INTEGER NOT NULL DEFAULT 0",
          'el_coverage_units'=>"code VARCHAR(5) PRIMARY KEY,department_code VARCHAR(2) NOT NULL,name VARCHAR(180) NOT NULL"
        ];
        foreach($ddl as $table=>$columns)$this->pdo->exec("CREATE TABLE IF NOT EXISTS $table ($columns)$suffix");
        // Indexes are created only when missing; all statements are additive.
        foreach(['el_measurement_events'=>['el_events_time'=>'occurred_at','el_events_session'=>'session_hash','el_events_scope'=>'department_code,municipality_code,occurred_at'],'el_measurement_sessions'=>['el_sessions_time'=>'started_at']] as $table=>$indexes){foreach($indexes as $name=>$columns){
            try{if($mysql){$q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$table,$name]);if((int)$q->fetchColumn())continue;}$this->pdo->exec('CREATE INDEX '.($mysql?'':'IF NOT EXISTS ')."$name ON $table ($columns)");}catch(Throwable $e){throw new RuntimeException('No se pudo preparar el índice de medición.');}
        }}
        $insert=$mysql?'INSERT IGNORE':'INSERT OR IGNORE';
        $q=$this->pdo->prepare("$insert INTO el_coverage_departments(code,name,priority,weight) VALUES(?,?,?,?)");
        foreach($plan['departments'] as $d)$q->execute([$d['code'],$d['name'],4-$d['weight'],$d['weight']]);
        $q=$this->pdo->prepare("$insert INTO el_coverage_places(id,department_code,name,slug,municipality_code,parent_place_id,place_type,priority,weight,preparing) VALUES(?,?,?,?,?,?,?,?,?,?)");
        foreach($plan['departments'] as $d)foreach($d['places'] as $p)$q->execute([$p['id'],$d['code'],$p['name'],$p['slug'],$p['municipality_code'],$p['parent_place_id'],$p['type'],$p['priority'],$p['weight'],(int)$p['preparing']]);
        $q=$this->pdo->prepare("$insert INTO el_coverage_units(code,department_code,name) VALUES(?,?,?)");foreach($geography as $g)$q->execute([$g['code'],$g['department_code'],$g['name']]);
        $q=$this->pdo->prepare("$insert INTO el_measurement_settings(setting_key,setting_value) VALUES(?,?)");foreach(['enabled'=>'1','started_at'=>gmdate('Y-m-d H:i:s'),'retention_days'=>'180','minimum_public_sessions'=>'5','catalog_version'=>$plan['universe_version']] as $k=>$v)$q->execute([$k,$v]);
        (new CoverageCatalogService($this->pdo))->synchronize($plan);
    }
    public function planFromDatabase():?array {
        if(!$this->ready())return null;$departments=$this->pdo->query('SELECT code,name,weight FROM el_coverage_departments ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->pdo->query('SELECT id,department_code,name,slug,municipality_code,parent_place_id,place_type AS type,priority,weight,preparing FROM el_coverage_places ORDER BY priority,name');$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($departments as &$d){$d['weight']=(int)$d['weight'];$d['places']=[];foreach($rows as $p){if($p['department_code']!==$d['code'])continue;unset($p['department_code']);$p['preparing']=(bool)$p['preparing'];$p['priority']=(int)$p['priority'];$p['weight']=(int)$p['weight'];$d['places'][]=$p;}}unset($d);
        return (new CoverageCatalogService($this->pdo))->enrich(['schema_version'=>2,'universe_version'=>'database-v1','catalog_updated_at'=>gmdate('Y-m-d'),'departments'=>$departments]);
    }
    public function geographyFromDatabase():array{return $this->pdo->query('SELECT code,department_code,name FROM el_coverage_units ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);}
    public function hash(string $value):string{return hash_hmac('sha256',$value,$this->key);}
    public function sessionMatches(string $session,array $context):bool {
        $q=$this->pdo->prepare('SELECT account_kind,account_hash FROM el_measurement_sessions WHERE session_hash=?');$q->execute([$this->hash('session|'.$session)]);$row=$q->fetch(PDO::FETCH_ASSOC);
        return !$row||($row['account_kind']===($context['account_kind']??'anonymous')&&$row['account_hash']===($context['account_hash']??null));
    }
    public function country(string $ip):string {
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return 'ZZ';
        $file=__DIR__.'/geoip/country.mmdb';$autoload=__DIR__.'/vendor/maxmind/autoload.php';
        if(!is_file($file)||!is_file($autoload))return 'ZZ';
        try{require_once $autoload;$reader=new \MaxMind\Db\Reader($file);$result=$reader->get($ip);$reader->close();$code=$result['country']['iso_code']??'ZZ';return preg_match('/^[A-Z]{2}$/D',$code)?$code:'ZZ';}catch(Throwable){return 'ZZ';}
    }
    public function maintain():void {
        $last=$this->pdo->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='last_retention_run'")->fetchColumn();if($last && $last>=gmdate('Y-m-d'))return;
        $this->purge();$mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$sql=$mysql?"INSERT INTO el_measurement_settings(setting_key,setting_value) VALUES('last_retention_run',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)":"INSERT INTO el_measurement_settings(setting_key,setting_value) VALUES('last_retention_run',?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value";$this->pdo->prepare($sql)->execute([gmdate('Y-m-d')]);
    }
    public function accept(array $event,array $context,string $now):bool {
        if(!$this->ready())return false;
        if(($event['consent']??null)!==true||!in_array($event['event']??'', ['page_view','business_view','contact_click','map_select','invitation_open'],true))throw new InvalidArgumentException('Evento no permitido');
        if(!preg_match('/^[a-f0-9-]{32,64}$/D',(string)($event['event_id']??'')))throw new InvalidArgumentException('Identificador inválido');
        $path=(string)($event['path']??'');if(strlen($path)>400||str_contains($path,'?')||str_contains($path,'#')||!preg_match('~^/[a-zA-Z0-9/_-]*(?:\.html)?$~D',$path))throw new InvalidArgumentException('Ruta inválida');
        if(preg_match('~/(admin|api|business|negocios/(portal|registro|acceso|salir|cambiar-clave))(?:/|$)~',$path)||($context['excluded']??false))return false;
        $known=$this->pdo->query('SELECT department_code,municipality_code,slug FROM el_coverage_places')->fetchAll(PDO::FETCH_ASSOC);
        $scope=['department_code'=>null,'municipality_code'=>null,'destination_slug'=>null];
        if(preg_match('~/(?:destinos)/([a-z0-9-]+)/?~',$path,$m))foreach($known as $p)if($p['slug']===$m[1]){$scope=['department_code'=>$p['department_code'],'municipality_code'=>$p['municipality_code'],'destination_slug'=>$p['slug']];break;}
        $businessId=null;$businessPublic=null;
        $requested=$event['business_id']??null;
        if(preg_match('~/(?:negocios)/([a-fA-F0-9-]{16,64})/?$~',$path,$m))$requested=$m[1];
        if(is_string($requested)&&preg_match('/^[a-fA-F0-9-]{16,64}$/D',$requested)){
            $q=$this->pdo->prepare("SELECT b.id,b.public_id,d.slug,d.department FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.public_id=? AND b.publication_status='published' AND d.publication_status='published' LIMIT 1");$q->execute([$requested]);$b=$q->fetch(PDO::FETCH_ASSOC);
            if($b){$businessId=(int)$b['id'];$businessPublic=$b['public_id'];foreach($known as $p)if($p['slug']===$b['slug']){$scope=['department_code'=>$p['department_code'],'municipality_code'=>$p['municipality_code'],'destination_slug'=>$p['slug']];break;}}
        }
        if(in_array($event['event'],['business_view','contact_click'],true)&&!$businessPublic)return false;
        if($event['event']==='map_select'){
            $code=(string)($event['department']??'');$q=$this->pdo->prepare('SELECT COUNT(*) FROM el_coverage_departments WHERE code=?');$q->execute([$code]);if(!(int)$q->fetchColumn())return false;$scope['department_code']=$code;
            $mun=(string)($event['municipality']??'');if($mun!==''){$q=$this->pdo->prepare('SELECT COUNT(*) FROM el_coverage_units WHERE code=? AND department_code=?');$q->execute([$mun,$code]);if(!(int)$q->fetchColumn())return false;$scope['municipality_code']=$mun;}
        }
        $channel=in_array($event['channel']??'', ['whatsapp','phone','email','website','directions','people','business'],true)?$event['channel']:null;
        if($event['event']==='contact_click'&&$channel===null)return false;
        $sid=$this->hash('session|'.$context['session']);$vid=$this->hash('visitor|'.$context['visitor']);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM el_measurement_events WHERE event_key=?');$eventKey=$this->hash($event['event_id']);$q->execute([$eventKey]);if((int)$q->fetchColumn())return false;
        $bucket=$this->hash('limit|'.$context['ip'].'|'.intdiv(strtotime($now.' UTC'),600));$mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $sql=$mysql?'INSERT INTO el_measurement_limits(bucket_key,hits,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1':'INSERT INTO el_measurement_limits(bucket_key,hits,expires_at) VALUES(?,1,?) ON CONFLICT(bucket_key) DO UPDATE SET hits=hits+1';$this->pdo->prepare($sql)->execute([$bucket,gmdate('Y-m-d H:i:s',strtotime($now.' UTC')+1200)]);
        $q=$this->pdo->prepare('SELECT hits FROM el_measurement_limits WHERE bucket_key=?');$q->execute([$bucket]);if((int)$q->fetchColumn()>100)return false;
        $kind=$context['account_kind']??'anonymous';$account=$context['account_hash']??null;if(!in_array($kind,['anonymous','traveler'],true))return false;
        $insert=$mysql?'INSERT IGNORE':'INSERT OR IGNORE';$this->pdo->prepare("$insert INTO el_measurement_sessions(session_hash,visitor_hash,started_at,last_seen_at,country_code,source_channel,account_kind,account_hash) VALUES(?,?,?,?,?,?,?,?)")->execute([$sid,$vid,$now,$now,$context['country']??'ZZ',$context['source']??'direct_unknown',$kind,$account]);
        $this->pdo->prepare('UPDATE el_measurement_sessions SET last_seen_at=? WHERE session_hash=?')->execute([$now,$sid]);
        $day=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
        // Repeated clicks within 30 seconds do not multiply contact intentions.
        if($event['event']==='contact_click'){$q=$this->pdo->prepare("SELECT COUNT(*) FROM el_measurement_events WHERE session_hash=? AND business_public_id=? AND channel=? AND event_name='contact_click' AND occurred_at>=?");$q->execute([$sid,$businessPublic,$channel,gmdate('Y-m-d H:i:s',strtotime($now.' UTC')-30)]);if((int)$q->fetchColumn())return false;}
        $this->pdo->prepare("$insert INTO el_measurement_events(event_key,session_hash,occurred_at,event_day,event_name,path,department_code,municipality_code,destination_slug,business_public_id,channel) VALUES(?,?,?,?,?,?,?,?,?,?,?)")->execute([$eventKey,$sid,$now,$day,$event['event'],$path,$scope['department_code'],$scope['municipality_code'],$scope['destination_slug'],$businessPublic,$channel]);return true;
    }
    public function report(int $days=30,?string $department=null,?string $municipality=null,?string $now=null):array {
        $days=in_array($days,[30,90],true)?$days:30;
        $empty=['status'=>'not_configured','metrics'=>array_fill_keys(['anonymous_sessions','estimated_visitors','registered_travelers','active_travelers','business_views','contact_clicks'],null),'countries'=>null,'trend'=>null];if(!$this->ready())return $empty;
        $now??=gmdate('Y-m-d H:i:s');$end=strtotime($now.' UTC');$start=gmdate('Y-m-d H:i:s',$end-$days*86400);$prior=gmdate('Y-m-d H:i:s',$end-2*$days*86400);
        $where='e.occurred_at>=? AND e.occurred_at<?';$args=[$prior,$now];if($department!==null){$where.=' AND e.department_code=?';$args[]=$department;}if($municipality!==null){$where.=' AND e.municipality_code=?';$args[]=$municipality;}
        $q=$this->pdo->prepare("SELECT e.event_name,e.occurred_at,e.event_day,e.session_hash,s.visitor_hash,s.country_code,s.source_channel,s.account_kind,s.account_hash FROM el_measurement_events e JOIN el_measurement_sessions s ON s.session_hash=e.session_hash WHERE $where ORDER BY e.occurred_at");$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $sessions=[];$visitors=[];$anonymous=[];$active=[];$business=0;$contacts=0;$views=0;$previous=0;$countries=[];$countryVisitors=[];$daily=[];$sources=[];
        foreach($rows as $r){if($r['occurred_at']<$start){if($r['event_name']==='page_view')$previous++;continue;}$sessions[$r['session_hash']]=true;$visitors[$r['visitor_hash']]=true;if($r['account_kind']==='anonymous')$anonymous[$r['session_hash']]=true;
            if($r['account_kind']==='traveler'&&$r['account_hash']&&in_array($r['event_name'],['page_view','business_view','contact_click'],true))$active[$r['account_hash']]=true;
            if($r['event_name']==='business_view')$business++;if($r['event_name']==='contact_click')$contacts++;
            if($r['event_name']==='page_view'){$countryVisitors[$r['country_code']][$r['visitor_hash']]=true;$views++;$daily[$r['event_day']]=($daily[$r['event_day']]??0)+1;$countries[$r['country_code']]=($countries[$r['country_code']]??0)+1;$sources[$r['source_channel']]=($sources[$r['source_channel']]??0)+1;}
        }
        $registered=null;try{$registered=(int)$this->pdo->query("SELECT COUNT(*) FROM accounts WHERE account_type='traveler' AND status='active' AND email_verified_at IS NOT NULL")->fetchColumn();}catch(Throwable){}
        $started=(string)$this->pdo->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='started_at'")->fetchColumn();$enoughHistory=$started!==''&&$started<=$prior;
        $minimum=5;$suppressed=count($visitors)>0&&count($visitors)<$minimum;
        $publicCountries=[];$other=0;arsort($countries);foreach($countries as $code=>$count){if(count($countryVisitors[$code]??[])<$minimum)$other+=$count;else $publicCountries[]=['code'=>$code,'count'=>$count];}if($other)$publicCountries[]=['code'=>'OTHER','count'=>$other];
        $series=[];$date=(new DateTimeImmutable(max($start,$started?:$start),new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->setTime(0,0);$last=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'));while($date<=$last){$day=$date->format('Y-m-d');$series[]=['day'=>$day,'views'=>$daily[$day]??0];$date=$date->modify('+1 day');}
        return ['status'=>$suppressed?'collecting_baseline':'enabled','source'=>'Medición propia de Explorando Lugares','updated_at'=>gmdate('c',$end),'period_days'=>$days,'period_start'=>$start,'period_end'=>$now,'started_at'=>$started,'privacy_minimum_sessions'=>$minimum,'metrics'=>['anonymous_sessions'=>$suppressed?null:count($anonymous),'estimated_visitors'=>$suppressed?null:count($visitors),'registered_travelers'=>$registered,'active_travelers'=>$suppressed?null:count($active),'business_views'=>$suppressed?null:$business,'contact_clicks'=>$suppressed?null:$contacts],'page_views'=>$suppressed?null:$views,'countries'=>$suppressed?null:$publicCountries,'sources'=>$suppressed?null:$sources,'trend'=>$suppressed?null:$series,'previous_views'=>$enoughHistory&&!$suppressed?$previous:null,'growth_percent'=>$enoughHistory&&!$suppressed&&$previous>0?round(100*($views-$previous)/$previous,1):null,'comparison_available'=>$enoughHistory&&!$suppressed,'scope'=>['department'=>$department,'municipality'=>$municipality],'registered_scope'=>'portal_global','consent_only'=>true,'country_source'=>is_file(__DIR__.'/geoip/country.mmdb')?'DB-IP Country Lite (consulta local)':'País no disponible'];
    }
    public function purge():void {
        $cutoff=gmdate('Y-m-d H:i:s',time()-180*86400);$this->pdo->prepare('DELETE FROM el_measurement_events WHERE occurred_at<?')->execute([$cutoff]);$this->pdo->prepare('DELETE FROM el_measurement_sessions WHERE last_seen_at<?')->execute([$cutoff]);$this->pdo->prepare('DELETE FROM el_measurement_limits WHERE expires_at<?')->execute([gmdate('Y-m-d H:i:s')]);
    }
}
