<?php
declare(strict_types=1);
// Narrow, repeatable withdrawal. IDs and relations are preserved; no deletion.
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))throw new RuntimeException('Explicit --apply required');
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Isolated dev database required');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$targets=['qc-33-4'=>'los-patios','qc-34-2'=>'el-zulia','qc-37-4'=>'la-paz','qc-16-2'=>'dagua','qc-30-4'=>'pueblo-viejo'];$before=[];$after=[];
$p->beginTransaction();
try{
 foreach($targets as $slug=>$destination){
  $q=$p->prepare('SELECT p.id,p.slug,p.publication_status,d.slug destination_slug FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id WHERE p.slug=? AND d.slug=? FOR UPDATE');$q->execute([$slug,$destination]);$rows=$q->fetchAll();
  if(count($rows)!==1)throw new RuntimeException('Expected exactly one stable identity: '.$slug);
  $r=$rows[0];$before[]=$r;
  $q=$p->prepare("UPDATE points_of_interest SET publication_status='archived' WHERE id=? AND slug=?");$q->execute([$r['id'],$slug]);$r['publication_status']='archived';$after[]=$r;
 }
 $q=$p->query("SELECT d.slug,t.locale,t.body FROM destination_reasons r JOIN destination_reason_translations t ON t.reason_id=r.id JOIN destinations d ON d.id=r.destination_id WHERE d.slug IN ('los-patios','el-zulia','la-paz')");
 $reasons=$q->fetchAll();
 foreach($reasons as $r)if(preg_match('/Agualinda|Jilguero|San Miguel/iu',$r['body']))throw new RuntimeException('A reason still promotes a retired item; reconcile before applying');
 $p->commit();
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
echo json_encode(['environment'=>'dev1','before'=>$before,'after'=>$after,'reason_rows_checked'=>count($reasons),'identity_preserved'=>true,'deleted_rows'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
