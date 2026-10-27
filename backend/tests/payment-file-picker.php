<?php
declare(strict_types=1);
$payment=file_get_contents(__DIR__.'/../public/business/payment.php');
foreach(['account-files.css','account-files.js','name="payment_proof"','accept="application/pdf,image/jpeg,image/png,image/webp" required'] as $required){
 if(!str_contains($payment,$required))throw new RuntimeException('Payment attachment lost shared control or validation: '.$required);
}
$registration=file_get_contents(__DIR__.'/../public/assets/account-registration.css');
if(!str_contains($registration,'@import url("/assets/account-files.css")')||str_contains($registration,'.account-file-picker{'))throw new RuntimeException('Registration must reuse one file-picker style definition.');
$css=file_get_contents(__DIR__.'/../public/assets/account-files.css');
foreach(['.account-file-button{','.account-file-status{',':focus-within','overflow-wrap:anywhere','min-height:44px'] as $required){
 if(!str_contains($css,$required))throw new RuntimeException('Missing shared attachment state: '.$required);
}
echo "OK: payment and registration share the accessible file picker; receipt field and accepted formats unchanged.\n";
