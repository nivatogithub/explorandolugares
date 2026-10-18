<?php
declare(strict_types=1);
$c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Solo preprod local');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$slugs=['jerico','jardin','cartagena','bogota','medellin','santa-marta'];$before=[];
foreach($slugs as $s){$q=$p->prepare('SELECT * FROM destinations WHERE slug=?');$q->execute([$s]);$d=$q->fetch();if(!$d)throw new RuntimeException('Destino faltante '.$s);$before[$s]=$d;}
file_put_contents('/srv/state/worker-review/before-local-public-'.gmdate('Ymd-His').'.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$p->beginTransaction();try{
 foreach($slugs as $s){if($s==='jerico')continue;$id=$before[$s]['id'];$p->prepare('UPDATE destinations SET publication_status="published" WHERE id=? AND publication_status="draft"')->execute([$id]);$p->prepare('UPDATE points_of_interest SET publication_status="published" WHERE destination_id=? AND publication_status="draft"')->execute([$id]);$p->prepare('UPDATE businesses SET publication_status="published" WHERE destination_id=? AND publication_status="draft" AND record_origin="public_import" AND EXISTS(SELECT 1 FROM business_sources bs WHERE bs.business_id=businesses.id)')->execute([$id]);}
 $p->commit();
}catch(Throwable $e){$p->rollBack();throw $e;}
$out=['scope'=>'local_preprod_only','production_changed'=>false,'destinations'=>[]];
foreach($slugs as $s){$q=$p->prepare('SELECT d.id,d.subregion,t.locale,t.display_name AS name,t.intro FROM destinations d JOIN destination_translations t ON t.destination_id=d.id WHERE d.slug=? AND d.publication_status="published"');$q->execute([$s]);foreach($q->fetchAll() as $v){$h=$p->prepare('SELECT m.asset_path FROM destination_media dm JOIN media_assets m ON m.id=dm.media_asset_id WHERE dm.destination_id=? AND dm.role="hero" LIMIT 1');$h->execute([$v['id']]);$v['hero_path']=$h->fetchColumn();$out['destinations'][$s][$v['locale']]=$v;}}
file_put_contents('/srv/state/worker-review/local-public-destinations.json',json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));echo json_encode(['status'=>'enabled_local_only','destinations'=>$slugs,'production_changed'=>false]);
