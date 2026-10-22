<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AdminBusinessFilters.php';
require dirname(__DIR__).'/app/AdminBusinessOperations.php';
function operationCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$row=['id'=>1,'record_origin'=>'public_import','category_code'=>'hotel','category_codes'=>['hotel','experience'],'destination_id'=>4,'display_name'=>'Negocio ficticio','destination_name'=>'Lugar ficticio','publication_status'=>'draft','information_status'=>'PUBLICO','claim_status'=>'NO_RECLAMADO','operation_status'=>'ABIERTO','account_email'=>'','subscription_status'=>'sin suscripción','plan_code'=>'free'];
$filters=['category'=>'experience','destination'=>4,'status'=>'draft','query'=>'ficticio'];
operationCheck(AdminBusinessFilters::imported($row,$filters,[]),'Secondary category must match both list and bulk predicate');
operationCheck(!AdminBusinessFilters::imported($row,[...$filters,'category'=>'other'],[]),'Unrelated category must be excluded');
operationCheck(!AdminBusinessFilters::imported($row,[...$filters,'duplicates'=>'no'],[1=>['same']]),'Duplicate filters must use the shared map');
operationCheck(!AdminBusinessFilters::imported([...$row,'record_origin'=>'owner_submitted'],$filters,[]),'Restricted bulk list must not expand to owner submissions');
$rows=AdminBusinessOperations::summarize([$row,[...$row,'id'=>2,'record_origin'=>'owner_submitted','publication_status'=>'published']],[['id'=>11,'business_id'=>1,'status'=>'approved'],['id'=>12,'business_id'=>1,'status'=>'pending'],['id'=>13,'business_id'=>2,'status'=>'pending']]);
operationCheck(count($rows)===2&&count($rows[0]['applications'])===2,'Applications must not duplicate business rows');
operationCheck($rows[0]['in_attention']&&$rows[1]['in_review'],'Imported and owner-submitted businesses must appear according to their actual pending requests');
operationCheck(AdminBusinessOperations::matches($row,['plan'=>'free']),'Default plan must be Free when there is no explicit subscription');
operationCheck(AdminBusinessOperations::matches($row,['plan'=>'free','category'=>'experience','account'=>'no']),'Independent filters must match actual recorded dimensions');
operationCheck(!AdminBusinessOperations::matches($row,['account'=>'yes']),'Account filter must not infer a membership');
operationCheck($row['record_origin']==='public_import'&&$row['publication_status']==='draft','Pure presentation helpers must not mutate input');
echo "OK: shared secondary-category/duplicate filters, distinct business rows, all-origin review, account/plan separation and read-only presentation.\n";

operationCheck(AdminBusinessOperations::matches($row,['segment'=>'imported']),'Imported business must remain in imported segment');
operationCheck(!AdminBusinessOperations::matches([...$row,'account_email'=>'owner@example.invalid'],['segment'=>'registered']),'Associated account must not reclassify imported origin');
operationCheck(AdminBusinessOperations::matches([...$row,'record_origin'=>'owner_submitted'],['segment'=>'registered']),'Person-created business must be isolated in registered segment');
operationCheck(!AdminBusinessOperations::matches([...$row,'record_origin'=>'admin_created'],['segment'=>'imported']),'Historical manual records must not be presented as imports');
