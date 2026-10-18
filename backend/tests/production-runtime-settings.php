<?php
declare(strict_types=1);
require __DIR__.'/../app/ProductionRuntimeSettings.php';
$dir=sys_get_temp_dir().'/el-runtime-'.bin2hex(random_bytes(8));mkdir($dir,0700);
try{if(ProductionRuntimeSettings::load($dir)!==[])throw new RuntimeException('Missing overlay');
$v=['business_accounts_enabled'=>true,'unified_admin_only'=>true,'account_mail'=>['enabled'=>true,'use_brevo_connection'=>true,'public_origin'=>'https://explorandolugares.com']];file_put_contents($dir.'/production-runtime-settings.json',json_encode($v));if(ProductionRuntimeSettings::load($dir)!==$v)throw new RuntimeException('Valid flags');
foreach([['app_key'=>'x'],['local_test'=>true],['account_mail'=>['public_origin'=>'http://localhost']],['business_accounts_enabled'=>'yes']] as $bad){file_put_contents($dir.'/production-runtime-settings.json',json_encode($bad));$rejected=false;try{ProductionRuntimeSettings::load($dir);}catch(Throwable){$rejected=true;}if(!$rejected)throw new RuntimeException('Unsafe overlay accepted');}
echo "OK: production flags are explicit; no credentials, local access or arbitrary keys can be overlaid.\n";
}finally{unlink($dir.'/production-runtime-settings.json');rmdir($dir);}
