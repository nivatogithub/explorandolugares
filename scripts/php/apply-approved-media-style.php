<?php
declare(strict_types=1);
function applyApprovedMediaStyle(PDO $p,array $plan,string $publicRoot,bool $apply): array {
 $protected=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)if(preg_match('/account|measurement|statistic|payment|subscription|contact|analytics|visit|event|trace|session|tracking|report/',$t))$protected[$t]=(int)$p->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();
 $identity=$p->query('SELECT id,asset_path FROM media_assets ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);$total=count($identity);$changes=[];$matched=0;
 $p->beginTransaction();try{
 foreach($plan as $m){
  $file=$publicRoot.'/'.$m['new_path'];if(!is_file($file)||hash_file('sha256',$file)!==$m['sha256'])throw new RuntimeException('Approved media bytes missing: '.$m['number']);
  $paths=array_values(array_unique(array_merge($m['old_paths'],array_values($m['targets_by_id']))));$q=$p->prepare('SELECT id,asset_path,provenance_json FROM media_assets WHERE asset_path IN ('.implode(',',array_fill(0,count($paths),'?')).') FOR UPDATE');$q->execute($paths);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(!$rows)throw new RuntimeException('Media precondition unmatched: '.$m['number']);
  $matched+=count($rows);foreach($rows as $row){$target=$m['targets_by_id'][(string)$row['id']]??null;if(!$target)throw new RuntimeException('Unexpected media identity');if(!is_file($publicRoot.'/'.$target)||hash_file('sha256',$publicRoot.'/'.$target)!==$m['sha256'])throw new RuntimeException('Alias bytes missing');if($row['asset_path']===$target)continue;$prov=json_decode($row['provenance_json']??'{}',true)?:[];$prov['style_transformation_20261021']=['source_sha256'=>$m['old_sha256'],'output_sha256'=>$m['sha256'],'prompt_record'=>'content/image-review/estilo-nueve-aprobadas-20261021.json','number'=>$m['number'],'approved_by_owner'=>true,'scope'=>'Rendering medium only; prior factual/source limitations retained.'];
   $u=$p->prepare('UPDATE media_assets SET asset_path=?,width=?,height=?,provenance_json=? WHERE id=? AND asset_path=?');$u->execute([$target,$m['width'],$m['height'],json_encode($prov,JSON_UNESCAPED_UNICODE),(int)$row['id'],$row['asset_path']]);if($u->rowCount()!==1)throw new RuntimeException('Concurrent media change');$changes[]=(int)$row['id'];
  }
 }
 if((int)$p->query('SELECT COUNT(*) FROM media_assets')->fetchColumn()!==$total)throw new RuntimeException('Media row count changed');
 foreach($protected as $t=>$n)if((int)$p->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn()<$n)throw new RuntimeException('Protected count decreased');
 if($apply)$p->commit();else $p->rollBack();return ['applied'=>$apply,'matched'=>$matched,'changed'=>count($changes),'ids'=>$changes,'media_total'=>$total,'protected_tables_non_decreasing'=>count($protected),'preflight_passed'=>true];
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
if(defined('EL_TRANSFER_FIXED')&&isset($options)){
 try{$c=require $options['config'];$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$plan=json_decode(base64_decode($options['fixed_plan']),true,512,JSON_THROW_ON_ERROR);$a=applyApprovedMediaStyle($p,$plan,$options['public_root'],false);$b=!empty($options['apply'])?applyApprovedMediaStyle($p,$plan,$options['public_root'],true):$a;$repeat=!empty($options['apply'])?applyApprovedMediaStyle($p,$plan,$options['public_root'],false):null;echo json_encode(['preflight'=>$a,'result'=>$b,'repeat'=>$repeat]);}
 catch(Throwable $e){http_response_code(500);error_log('approved media style: '.$e->getMessage());echo json_encode(['error'=>'Media style update stopped; consult private log.']);}
}
