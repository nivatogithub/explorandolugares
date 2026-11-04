<?php
// Compatibility adapter for existing component checks.
if(!isset($paymentInstructions)){http_response_code(404);exit;}
require dirname(__DIR__,2).'/app/views/account/payment-instructions.php';
