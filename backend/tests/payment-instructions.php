<?php
declare(strict_types=1);
require __DIR__.'/../app/PaymentInstructions.php';
$d=sys_get_temp_dir().'/el-payment-settings-'.bin2hex(random_bytes(8));mkdir($d,0700);
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec(file_get_contents(__DIR__.'/../database/migrations/039-payment-instructions.sqlite.sql'));
try{
 $input=['enabled'=>'1','holder'=>'PRUEBA SIN VALOR','bank'=>'BANCO FICTICIO','account_number'=>'NO-TRANSFERIR-0000','account_type'=>'savings','breb_key'=>'PRUEBA-NO-PAGAR'];PaymentInstructions::save($pdo,$d,$input,[],true,1);$local=PaymentInstructions::load($pdo,$d,true);if(!$local['test_mode']||$local['breb_key']!==$input['breb_key']||PaymentInstructions::load($pdo,$d,false)!==[])throw new RuntimeException('Simulation leaked to production or lost settings');
 $before=json_encode($pdo->query('SELECT * FROM payment_instructions')->fetchAll());try{PaymentInstructions::save($pdo,$d,array_replace($input,['account_type'=>'invalid']),[],true,1);throw new LogicException('invalid accepted');}catch(RuntimeException $e){}
 if(json_encode($pdo->query('SELECT * FROM payment_instructions')->fetchAll())!==$before)throw new RuntimeException('Invalid save changed configuration');
 PaymentInstructions::save($pdo,$d,$input,[],true,1);if(PaymentInstructions::load($pdo,$d,true)['account_number']!==$input['account_number'])throw new RuntimeException('Reload lost account');
 try{PaymentInstructions::save($pdo,$d,$input,['error'=>UPLOAD_ERR_OK,'tmp_name'=>__FILE__,'size'=>100],true,1);throw new LogicException('Non-upload accepted');}catch(RuntimeException $e){}
 PaymentInstructions::save($pdo,$d,array_replace($input,['holder'=>'TITULAR REAL CONFIGURABLE']),[],false,2);
 if(PaymentInstructions::load($pdo,$d,false)['holder']!=='TITULAR REAL CONFIGURABLE')throw new RuntimeException('Production settings unavailable');
 if(is_file($d.'/instructions.json'))throw new RuntimeException('Still writing JSON');
 echo "PASS: persistence, test-mode isolation, invalid type rollback and upload boundary\n";
}finally{rmdir($d);}
