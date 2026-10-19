<?php
declare(strict_types=1);
/** Public aggregate-only dashboard. Never exposes accounts, draft places or CRM records. */
final class PublicDashboardService
{
    public function __construct(private PDO $db) {}
    private static function normalized(string $s):string{return strtolower(preg_replace('/[^a-z0-9]/i','',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s));}
    public function report(array $departments,?string $department=null,?string $destination=null,int|string $days=30):array {
        $out=['status'=>'not_configured','registered_travelers'=>null,'traveler_scope'=>'portal_global','businesses'=>null,'places'=>[],'place_interest'=>['status'=>'not_configured','rows'=>[]],'updated_at'=>gmdate('c')];
        $names=[];foreach($departments as $d)$names[$d['code']]=self::normalized($d['name']);
        $public=[];try{foreach($this->db->query("SELECT id,slug,department FROM destinations WHERE country_code='CO' AND publication_status='published'")->fetchAll(PDO::FETCH_ASSOC) as $d){if($department!==null&&(!isset($names[$department])||(self::normalized($d['department'])!==$names[$department]&&!($department==='25'&&self::normalized($d['department'])===($names['11']??'bogotadc')))))continue;if($destination!==null&&$d['slug']!==$destination)continue;$public[(int)$d['id']]=$d;}}catch(Throwable){return $out;}
        try{$out['registered_travelers']=(int)$this->db->query("SELECT COUNT(*) FROM accounts a WHERE a.account_type='traveler' AND a.status='active' AND a.email_verified_at IS NOT NULL AND a.email NOT LIKE '%.invalid' AND NOT EXISTS(SELECT 1 FROM account_roles r WHERE r.account_id=a.id AND r.role='administrator')")->fetchColumn();}catch(Throwable){}
        try{
            $rows=$this->db->query("SELECT b.id,b.destination_id,COALESCE((SELECT s.plan_code FROM business_subscriptions s WHERE s.business_id=b.id AND s.status='active' AND (s.starts_at IS NULL OR s.starts_at<=CURRENT_TIMESTAMP) AND (s.ends_at IS NULL OR s.ends_at>CURRENT_TIMESTAMP) ORDER BY s.id DESC LIMIT 1),'free') AS plan_code FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.publication_status='published' AND d.publication_status='published' AND d.country_code='CO' AND b.information_status<>'PENDIENTE_REVISION' AND b.operation_status<>'CERRADO_DEFINITIVO' AND b.public_id NOT LIKE 'f00d0000-%'")->fetchAll(PDO::FETCH_ASSOC);
            $totals=['free'=>0,'featured'=>0,'page'=>0,'page_premium'=>0,'other'=>0];$ids=[];
            foreach($rows as $b)if(isset($public[(int)$b['destination_id']])){$code=array_key_exists($b['plan_code'],$totals)?$b['plan_code']:'other';$totals[$code]++;$ids[]=(int)$b['id'];}
            $claimed=null;try{$claimed=0;foreach($this->db->query("SELECT DISTINCT business_id FROM business_account_memberships WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN) as $id)if(in_array((int)$id,$ids,true))$claimed++;}catch(Throwable){$claimed=null;}
            $out['businesses']=['total'=>array_sum($totals),'by_subscription'=>$totals,'with_registered_responsible'=>$claimed,'scope'=>'published_directory','paid_basis'=>'active_subscription_in_effect','includes_preloaded_free_listings'=>true];$out['status']='available';
        }catch(Throwable){}
        $out['places']=array_values(array_map(static fn($d)=>['slug'=>$d['slug']],$public));
        try{
            if($this->db->query("SELECT setting_value FROM el_basic_settings WHERE setting_key='enabled'")->fetchColumn()!=='1')return $out;
            $start=$days==='all'?'1970-01-01':(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->modify('-'.((in_array($days,[7,30,90],true)?$days:30)-1).' days')->format('Y-m-d');
            $q=$this->db->prepare("SELECT entity_key,SUM(metric_count) AS views FROM el_basic_entity_daily WHERE entity_type='destination' AND metric='page_view' AND page_day>=? GROUP BY entity_key");$q->execute([$start]);$counts=array_column($q->fetchAll(PDO::FETCH_ASSOC),'views','entity_key');$interest=[];foreach($public as $d)$interest[]=['slug'=>$d['slug'],'views'=>(int)($counts[$d['slug']]??0)];usort($interest,static fn($a,$b)=>($b['views']<=>$a['views'])?:strcmp($a['slug'],$b['slug']));$out['place_interest']=['status'=>'enabled','rows'=>array_slice($interest,0,8),'unit'=>'page_views','consent_only'=>false];
        }catch(Throwable){}
        return $out;
    }
}
