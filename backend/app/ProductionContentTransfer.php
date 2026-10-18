<?php
declare(strict_types=1);
require_once __DIR__.'/DestinationAdminService.php';
/** Private release tool: scoped editorial upserts; never imports accounts or payments. */
final class ProductionContentTransfer
{
 private array $columnCache=[];
 public function __construct(private PDO $db){}
 private function columns(string $table):array{return $this->columnCache[$table]??=$this->db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);}
 private function upsert(string $table,array $row,array $keys,bool $id=false):int{
  $columns=array_column($this->columns($table),'Field');$row=array_intersect_key($row,array_flip($columns));unset($row['id']);
  foreach($keys as $key)if(!array_key_exists($key,$row))throw new RuntimeException('Missing identity for '.$table);
  $where=implode(' AND ',array_map(static fn($k)=>'`'.$k.'`=?',$keys));$q=$this->db->prepare('SELECT '.($id?'id':'1').' FROM `'.$table.'` WHERE '.$where.' LIMIT 2');$q->execute(array_map(static fn($k)=>$row[$k],$keys));$existing=$q->fetchAll(PDO::FETCH_COLUMN);if(count($existing)>1)throw new RuntimeException('Ambiguous identity in '.$table);
  if($existing){$fields=array_values(array_diff(array_keys($row),$keys,['created_at']));if($fields){$sql='UPDATE `'.$table.'` SET '.implode(',',array_map(static fn($k)=>'`'.$k.'`=?',$fields)).' WHERE '.$where;$this->db->prepare($sql)->execute([...array_map(static fn($k)=>$row[$k],$fields),...array_map(static fn($k)=>$row[$k],$keys)]);}return $id?(int)$existing[0]:0;}
  $fields=array_keys($row);$this->db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')')->execute(array_values($row));return $id?(int)$this->db->lastInsertId():0;
 }
 private function primary(string $table):array{return array_values(array_column(array_filter($this->columns($table),static fn($r)=>$r['Key']==='PRI'),'Field'));}
 public function validate(array $pack):array{
  $slugs=array_keys($pack['editorial']??[]);sort($slugs);$expected=['bogota','cartagena','jerico','medellin','santa-marta'];if(($pack['schema_version']??null)!==1||$slugs!==$expected)throw new RuntimeException('Unexpected publication scope');
  $businesses=$pack['tables']['businesses']??[];if(count($businesses)!==305)throw new RuntimeException('Unexpected business count');$seen=[];
  foreach($businesses as $b){$uuid=$b['public_id']??'';if(!preg_match('/^[a-f0-9-]{36}$/D',$uuid)||str_starts_with($uuid,'f00d0000-')||isset($seen[$uuid]))throw new RuntimeException('Invalid or test business identity');$seen[$uuid]=true;}
  foreach($pack['tables']['media_assets']??[] as $m)if(!preg_match('~^assets/[a-zA-Z0-9_./-]+$~D',$m['asset_path'])||str_contains($m['asset_path'],'..'))throw new RuntimeException('Invalid media path');
  foreach($pack['editorial'] as $slug=>$d)if(($d['slug']??'')!==$slug||($d['publication_status']??'')!=='published')throw new RuntimeException('Publication not explicit');
  return ['places'=>$expected,'businesses'=>count($businesses),'media'=>count($pack['tables']['media_assets']??[]),'accounts_imported'=>0,'payments_imported'=>0];
 }
 public function apply(array $pack,string $publicRoot):array{
  $report=$this->validate($pack);$tables=$pack['tables'];$media=[];$destinations=[];$businessMap=[];
  // Catalog data defines current offers; captured historical subscriptions are untouched.
  foreach(['service_categories','service_category_translations','subscription_plans','subscription_plan_translations','subscription_features','subscription_feature_translations','subscription_plan_features','subscription_plan_prices','subscription_pricing_policy','experience_operator_prices'] as $table)foreach($tables[$table]??[] as $row)$this->upsert($table,$row,$this->primary($table));
  foreach($tables['media_assets']??[] as $row)$media[(int)$row['id']]=$this->upsert('media_assets',$row,['asset_path'],true);
  foreach($tables['media_asset_translations']??[] as $row){$row['media_asset_id']=$media[(int)$row['media_asset_id']];$this->upsert('media_asset_translations',$row,['media_asset_id','locale']);}
  $service=new DestinationAdminService($this->db);
  foreach($pack['editorial'] as $slug=>$source){
   $q=$this->db->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$slug]);$id=(int)$q->fetchColumn();$existing=$id?$service->find($id):null;$in=$source;$in['id']=$id;
   foreach(['hero','history'] as $role)$in[$role.'_asset_id']=$media[(int)($source['destination_media'][$role]['media_asset_id']??0)]??0;
   $existingPoi=$existing?array_column($existing['places'],null,'slug'):[];
   foreach($in['places'] as &$poi){$poi['id']=(int)($existingPoi[$poi['slug']]['id']??0);$poi['media_asset_id']=$media[(int)($poi['media_asset_id']??0)]??0;}unset($poi);
   foreach(['es','en','pt-BR'] as $lang){$in['history'][$lang]=implode("\n",$source['history'][$lang]);$in['reasons'][$lang]=implode("\n",$source['reasons'][$lang]);$in['sources'][$lang]=implode("\n",array_map(static fn($s)=>$s['section_key'].'|'.$s['source_label'].'|'.$s['source_url'],$source['sources'][$lang]));}
   if(!$id){$draft=$in;$draft['publication_status']='draft';$draft['places']=[];$in['id']=$service->save($draft,[],[],$publicRoot);}
   $new=$service->save($in,[],[],$publicRoot);$destinations[(int)$source['id']]=$new;
  }
  $this->db->beginTransaction();
  try{
   foreach($tables['businesses'] as $b){$old=(int)$b['id'];$b['destination_id']=$destinations[(int)$b['destination_id']];
    $q=$this->db->prepare('SELECT b.id FROM businesses b WHERE b.public_id=?');$q->execute([$b['public_id']]);$prior=$q->fetchColumn();
    if($prior){$q=$this->db->prepare('SELECT COUNT(*) FROM business_account_memberships WHERE business_id=? AND status="active"');$q->execute([$prior]);if((int)$q->fetchColumn()>0)throw new RuntimeException('An owned business needs individual reconciliation');}
    else{$names=array_values(array_filter($tables['business_translations'],static fn($t)=>(int)$t['business_id']===$old&&$t['locale']==='es'));$name=$names[0]['display_name']??'';$q=$this->db->prepare('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');$q->execute([$b['destination_id'],$name]);if($q->fetchColumn())throw new RuntimeException('Possible duplicate business name; no new duplicate inserted');}
    $id=$this->upsert('businesses',$b,['public_id'],true);$businessMap[$old]=$id;
    $q=$this->db->prepare('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?');$q->execute([$id]);if(!(int)$q->fetchColumn())$this->db->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,quoted_amount,currency,status,starts_at,published_at) VALUES(?,"free",NULL,0,"COP","active",UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$id]);
   }
   foreach(['business_translations'=>['business_id','locale'],'business_categories'=>['business_id','category_code'],'business_sources'=>['business_id','source_url'],'business_contacts'=>['business_id','channel','contact_value'],'business_media'=>['business_id','media_asset_id','role']] as $table=>$keys)foreach($tables[$table]??[] as $row){$row['business_id']=$businessMap[(int)$row['business_id']];if(isset($row['media_asset_id']))$row['media_asset_id']=$media[(int)$row['media_asset_id']];$this->upsert($table,$row,$keys);}
   // CRM permissions/suppression are production data: insert missing references only.
   foreach($tables['business_research_contacts']??[] as $row){$q=$this->db->prepare('SELECT id FROM business_research_contacts WHERE record_hash=?');$q->execute([$row['record_hash']]);if($q->fetchColumn())continue;$row['business_id']=$businessMap[(int)$row['business_id']];$this->upsert('business_research_contacts',$row,['record_hash']);}
   $this->db->commit();
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
  $report['destination_id_map']=$destinations;$report['businesses_processed']=count($businessMap);$report['production_only_records_preserved']=true;return $report;
 }
}
