<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessPaymentEvidenceService.php';
$data=['transaction_date'=>date('Y-m-d'),'paid_amount'=>'49900.50','payment_method'=>'breb','source_bank'=>'BANCO FICTICIO','checkout_reference'=>'EL-'.str_repeat('A',24)];
if(BusinessPaymentEvidenceService::validateTransaction('PRUEBA-TRANSACCION',$data)!==$data)throw new RuntimeException('Lost transaction details');
foreach(['transaction_date'=>'2026-02-30','paid_amount'=>'-1','payment_method'=>'invalid','source_bank'=>'','checkout_reference'=>'forged'] as $key=>$value){try{BusinessPaymentEvidenceService::validateTransaction('PRUEBA',array_replace($data,[$key=>$value]));throw new LogicException('Accepted invalid '.$key);}catch(RuntimeException $e){}}
try{BusinessPaymentEvidenceService::validateTransaction('',$data);throw new LogicException('Accepted missing transaction');}catch(RuntimeException $e){}
echo "PASS: transaction details, date, amount, method, bank and checkout reference validated.\n";
