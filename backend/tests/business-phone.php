<?php
require dirname(__DIR__).'/app/BusinessPhone.php';
function expect($actual,$expected){if($actual!==$expected)throw new RuntimeException('Resultado inesperado');}
expect(BusinessPhone::normalize('300 123 4567'),'+573001234567');
expect(BusinessPhone::normalize('+57 300 123 4567'),'+573001234567');
expect(BusinessPhone::normalize('444 1234','fixed','604'),'+576044441234');
expect(BusinessPhone::normalize('+57 601 444 1234','fixed'),'+576014441234');
expect(BusinessPhone::capture(['business_phone'=>['type'=>'mobile','number'=>'3001234567','same_whatsapp'=>'1']],[])['whatsapp'],'+573001234567');
expect(BusinessPhone::capture(['business_phone'=>['type'=>'fixed','area'=>'604','number'=>'4441234','whatsapp'=>'3017654321']],[])['phone_fixed'],'+576044441234');
foreach([['123','mobile',''],['4441234','fixed',''],['1234567890','mobile',''],['+573001234567x','mobile','']] as $args){try{BusinessPhone::normalize(...$args);throw new LogicException('Aceptó inválido');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}}
echo "OK: celular/fijo, formato internacional, WhatsApp compartido y rechazo de números incompletos.\n";
