<?php
declare(strict_types=1);
require __DIR__.'/reports.php';require dirname(__DIR__,2).'/app/advance/PaymentReview.php';
$db->exec(file_get_contents(dirname(__DIR__,2).'/database/migrations/013-subscription-payment-review.sqlite.sql'));
$review=new PaymentReview($db,'reviewer-test');$before=$db->query('SELECT COUNT(*) FROM subscription_payments')->fetchColumn();
$claim=['subscription_id'=>1,'reference'=>'review-test-1','payer_name'=>'Pagador ficticio','amount'=>'123.45','currency'=>'COP','reported_on'=>'2026-10-14','evidence_note'=>'Referencia ficticia para prueba'];
$review->submit($claim);check($db->query('SELECT COUNT(*) FROM subscription_payments')->fetchColumn()===$before,'Reportado no es recaudo');
$row=$review->rows('pending')[0];check($row['business']==='Negocio existente','Reporte vinculado al negocio');
rejects(fn()=>$review->submit($claim));
rejects(fn()=>$review->submit([...$claim,'reference'=>'bad-sub','subscription_id'=>9999]));
rejects(fn()=>$review->review(['claim_id'=>$row['id'],'review_note'=>''],true));
$review->review(['claim_id'=>$row['id'],'review_note'=>'Movimiento ficticio cotejado en prueba'],true);
$confirmed=$review->rows('confirmed')[0];check($confirmed['reviewed_by']==='reviewer-test'&&$confirmed['payment_id']!==null,'Autor y pago vinculados');
check((int)$db->query('SELECT COUNT(*) FROM subscription_payments')->fetchColumn()===(int)$before+1,'Confirmación registra un solo pago');
rejects(fn()=>$review->review(['claim_id'=>$row['id'],'review_note'=>'Repetición'],true));
$review->submit([...$claim,'reference'=>'review-test-2']);$pending=$review->rows('pending')[0];
$review->review(['claim_id'=>$pending['id'],'review_note'=>'Referencia ficticia no corresponde'],false);
check(count($review->rows('rejected'))===1,'Rechazo trazable');
check((int)$db->query('SELECT COUNT(*) FROM subscription_payments')->fetchColumn()===(int)$before+1,'Rechazo no suma ingresos');
check($db->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()==='draft','Confirmación no publica');
check($db->query('SELECT status FROM business_subscriptions WHERE id=1')->fetchColumn()==='cancelled','Confirmación no activa periodos');
check($review->history()[0]['payer_name']==='Pagador ficticio','Historial relaciona pagador y movimiento');
// Conflicto con registro manual posterior: rollback completo mantiene reporte pendiente.
$review->submit([...$claim,'reference'=>'review-conflict']);$pending=$review->rows('pending')[0];
$reports->record('payment',['subscription_id'=>1,'reference'=>'review-conflict','entry_type'=>'payment','amount'=>'123.45','currency'=>'COP','received_on'=>'2026-10-14']);
rejects(fn()=>$review->review(['claim_id'=>$pending['id'],'review_note'=>'Referencia ya conciliada'],true));
check($review->rows('pending')[0]['id']===$pending['id'],'Conflicto no confirma dos veces');
echo "OK: reporte pendiente, negocio/suscripción/pagador, revisión con autor, confirmación única, rechazo, historial y conflicto sin duplicación ni activación/publicación.\n";
