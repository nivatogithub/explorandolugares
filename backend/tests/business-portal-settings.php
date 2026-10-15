<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessPortalSettings.php';

function settings_check(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR,"FAIL: {$message}\n"); exit(1); }
    echo "OK: {$message}\n";
}

$directory=sys_get_temp_dir().'/el-business-settings-'.bin2hex(random_bytes(6));
mkdir($directory,0700);
$path=$directory.'/config.php';
$original=['dsn'=>'sqlite:/private/test.sqlite','db_user'=>'secret-user','db_password'=>'secret-password','app_key'=>str_repeat('x',64),'admin_user'=>'admin','admin_password_hash'=>'hash','business_accounts_enabled'=>false,'business_accounts_test_key'=>''];
file_put_contents($path,"<?php return ".var_export($original,true).";\n");chmod($path,0600);
try {
    $key=bin2hex(random_bytes(32));
    BusinessPortalSettings::save($path,false,$key);
    $saved=require $path;
    settings_check($saved['business_accounts_enabled']===false&&$saved['business_accounts_test_key']===$key,'A private test key is written while public account creation stays disabled.');
    settings_check($saved['db_user']===$original['db_user']&&$saved['db_password']===$original['db_password']&&$saved['app_key']===$original['app_key'],'Saving access controls preserves the remaining private settings.');
    settings_check((fileperms($path)&0777)===0600,'Atomic config replacement preserves restrictive file permissions.');
    BusinessPortalSettings::save($path,true,'');
    $saved=require $path;
    settings_check($saved['business_accounts_enabled']===true&&$saved['business_accounts_test_key']==='','Public enablement clears the private test key.');
    BusinessPortalSettings::save($path,false,'');
    $saved=require $path;
    settings_check($saved['business_accounts_enabled']===false&&$saved['business_accounts_test_key']==='','Closing public registration with no test key leaves the portal closed.');
    $rejected=false;
    try { BusinessPortalSettings::save($path,false,'short'); } catch (InvalidArgumentException) { $rejected=true; }
    settings_check($rejected,'Keys shorter than 32 characters are rejected.');
    $link=$directory.'/symlink.php';symlink($path,$link);$rejected=false;
    try { BusinessPortalSettings::save($link,false,''); } catch (RuntimeException) { $rejected=true; }
    settings_check($rejected,'A symlink cannot redirect settings writes to another file.');
} finally {
    foreach (glob($directory.'/*')?:[] as $file) @unlink($file);
    @rmdir($directory);
}
