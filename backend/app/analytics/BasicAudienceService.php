<?php
declare(strict_types=1);
/** Aggregate page counters only: no visitor/session IDs, IPs, countries or raw paths. */
final class BasicAudienceService {
    public function __construct(private PDO $pdo, private string $publicRoot) {}
    public function ready():bool {
        try {
            if($this->pdo->query("SELECT setting_value FROM el_basic_settings WHERE setting_key='enabled'")->fetchColumn()!=='1')return false;
            $this->pdo->query('SELECT page_day,scope_code,page_views FROM el_basic_page_daily LIMIT 0');return true;
        }catch(Throwable){return false;}
    }
    public function install():void {
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS el_basic_settings(setting_key VARCHAR(64) PRIMARY KEY,setting_value VARCHAR(64) NOT NULL)$suffix");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS el_basic_page_daily(page_day VARCHAR(10) NOT NULL,scope_code VARCHAR(2) NOT NULL,page_views BIGINT NOT NULL DEFAULT 0,PRIMARY KEY(page_day,scope_code))$suffix");
        $insert=$mysql?'INSERT IGNORE':'INSERT OR IGNORE';
        $q=$this->pdo->prepare("$insert INTO el_basic_settings(setting_key,setting_value) VALUES(?,?)");
        foreach(['started_at'=>gmdate('Y-m-d H:i:s'),'enabled'=>'1','retention_days'=>'180'] as $k=>$v)$q->execute([$k,$v]);
    }
    private function department(string $slug):?string {
        try{$q=$this->pdo->prepare('SELECT department_code FROM el_coverage_places WHERE slug=? LIMIT 1');$q->execute([$slug]);$code=$q->fetchColumn();return is_string($code)&&preg_match('/^[0-9]{2}$/D',$code)?$code:null;}catch(Throwable){return null;}
    }
    private function scope(string $path):array|null {
        if(strlen($path)>400||!preg_match('~^/[a-zA-Z0-9/_-]*(?:\.html)?$~D',$path))return null;
        if(preg_match('~/(admin|api|business|negocios/(portal|registro|acceso|salir|cambiar-clave))(?:/|$)~',$path))return null;
        if(preg_match('~^/(?:en/|pt-br/)?destinos/([a-z0-9-]+)(?:/|$)~',$path,$match)) {
            $q=$this->pdo->prepare("SELECT slug FROM destinations WHERE slug=? AND publication_status='published' LIMIT 1");$q->execute([$match[1]]);
            if(!$q->fetchColumn())return null;
            // Only existing static subpages or the destination's public root.
            $root=preg_match('~^/(?:en/|pt-br/)?destinos/[a-z0-9-]+/(?:index\.html)?$~',$path);
            if(!$root&&!$this->staticPage($path))return null;
            return array_filter(['CO',$this->department($match[1])]);
        }
        if(preg_match('~^/(?:en/|pt-br/)?negocios/([a-fA-F0-9-]{16,64})/?$~D',$path,$match)) {
            $q=$this->pdo->prepare("SELECT d.slug FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.public_id=? AND b.publication_status='published' AND d.publication_status='published' LIMIT 1");$q->execute([$match[1]]);$slug=$q->fetchColumn();
            return $slug?array_filter(['CO',$this->department((string)$slug)]):null;
        }
        return $this->staticPage($path)?['CO']:null;
    }
    private function staticPage(string $path):bool {
        $relative=ltrim($path,'/');$file=rtrim($this->publicRoot,'/').'/'.$relative;
        if(is_dir($file))$file=rtrim($file,'/').'/index.html';
        return str_ends_with($file,'.html')&&is_file($file);
    }
    public function countPage(string $path,?string $now=null):bool {
        if(!$this->ready())return false;
        $scopes=$this->scope($path);if(!$scopes)return false;
        $now??=gmdate('Y-m-d H:i:s');
        $this->maintain($now);
        $day=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $sql=$mysql?'INSERT INTO el_basic_page_daily(page_day,scope_code,page_views) VALUES(?,?,1) ON DUPLICATE KEY UPDATE page_views=page_views+1':'INSERT INTO el_basic_page_daily(page_day,scope_code,page_views) VALUES(?,?,1) ON CONFLICT(page_day,scope_code) DO UPDATE SET page_views=page_views+1';
        $this->pdo->beginTransaction();
        try{$q=$this->pdo->prepare($sql);foreach(array_unique($scopes) as $scope)$q->execute([$day,$scope]);$this->pdo->commit();}catch(Throwable $e){$this->pdo->rollBack();throw $e;}
        return true;
    }
    public function report(int $days=30,?string $department=null,?string $now=null):array {
        $empty=['status'=>'not_configured','page_views'=>null,'trend'=>null,'previous_views'=>null,'growth_percent'=>null,'comparison_available'=>false];
        if(!$this->ready())return $empty;
        if($department!==null&&!preg_match('/^[0-9]{2}$/D',$department))throw new InvalidArgumentException('Departamento inválido');
        $days=in_array($days,[30,90],true)?$days:30;$now??=gmdate('Y-m-d H:i:s');
        $today=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->setTime(0,0);
        $start=$today->modify('-'.($days-1).' days');$completeStart=$today->modify("-$days days");$prior=$today->modify('-'.(2*$days).' days');
        $started=(string)$this->pdo->query("SELECT setting_value FROM el_basic_settings WHERE setting_key='started_at'")->fetchColumn();
        $startedLocal=(new DateTimeImmutable($started,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'));
        $q=$this->pdo->prepare('SELECT page_day,page_views FROM el_basic_page_daily WHERE scope_code=? AND page_day>=? AND page_day<=? ORDER BY page_day');$q->execute([$department??'CO',$prior->format('Y-m-d'),$today->format('Y-m-d')]);
        $daily=[];$views=0;$previous=0;$completeViews=0;foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){if($row['page_day']<$completeStart->format('Y-m-d'))$previous+=(int)$row['page_views'];elseif($row['page_day']<$today->format('Y-m-d'))$completeViews+=(int)$row['page_views'];if($row['page_day']>=$start->format('Y-m-d')){$views+=(int)$row['page_views'];$daily[$row['page_day']]=(int)$row['page_views'];}}
        $series=[];$first=max($start->format('Y-m-d'),$startedLocal->format('Y-m-d'));for($day=new DateTimeImmutable($first,$today->getTimezone());$day<=$today;$day=$day->modify('+1 day')){$key=$day->format('Y-m-d');$series[]=['day'=>$key,'views'=>$daily[$key]??0];}
        // Require an entire preceding window, not a partial first day.
        $history=$startedLocal<=$prior;
        return ['status'=>'enabled','page_views'=>$views,'started_at'=>$started,'period_start'=>$start->format('Y-m-d'),'period_end'=>$today->format('Y-m-d'),'scope'=>$department??'CO','trend'=>$series,'previous_views'=>$history?$previous:null,'comparison_current_views'=>$history?$completeViews:null,'comparison_period_start'=>$completeStart->format('Y-m-d'),'comparison_period_end'=>$today->modify('-1 day')->format('Y-m-d'),'current_day_partial'=>true,'growth_percent'=>$history&&$previous>0?round(100*($completeViews-$previous)/$previous,1):null,'comparison_available'=>$history,'cookies'=>false,'identifiers'=>false,'unit'=>'page_views','source'=>'Conteo básico agregado propio'];
    }
    private function maintain(string $now):void {
        $day=substr($now,0,10);
        if($this->pdo->query("SELECT setting_value FROM el_basic_settings WHERE setting_key='last_retention_run'")->fetchColumn()===$day)return;
        $cutoff=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->modify('-180 days')->format('Y-m-d');
        $this->pdo->prepare('DELETE FROM el_basic_page_daily WHERE page_day<?')->execute([$cutoff]);
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $sql=$mysql?"INSERT INTO el_basic_settings(setting_key,setting_value) VALUES('last_retention_run',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)":"INSERT INTO el_basic_settings(setting_key,setting_value) VALUES('last_retention_run',?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value";
        $this->pdo->prepare($sql)->execute([$day]);
    }
    public function purge():void {
        if(!$this->ready())return;
        $day=(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->modify('-180 days')->format('Y-m-d');
        $this->pdo->prepare('DELETE FROM el_basic_page_daily WHERE page_day<?')->execute([$day]);
    }
}
