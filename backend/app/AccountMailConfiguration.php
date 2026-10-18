<?php
declare(strict_types=1);
require_once __DIR__.'/AccountRecoveryDelivery.php';
require_once __DIR__.'/BrevoSettings.php';
/** Real local mail is opt-in for exactly one private recipient; synthetic tests keep their mailbox. */
final class AccountMailConfiguration
{
 public static function resolve(array $config,string $email):array{
  $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
  $local=getenv('EL_PREPROD_LOCAL')==='1'&&!empty($config['local_test'])&&preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host);
  $settings=$config['account_mail']??[];$realLocal=false;
  if($local){
   $path=dirname(__DIR__).'/private/account-mail-test.json';
   if(is_file($path)&&!is_link($path)){
    $test=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
    $realLocal=($test['enabled']??false)===true&&is_string($test['recipient']??null)&&filter_var($test['recipient'],FILTER_VALIDATE_EMAIL)&&strtolower($test['recipient'])===strtolower($email);
   }
   if(!$realLocal)return ['mailbox'=>true,'delivery'=>new AccountRecoveryDelivery([])];
   $settings=['enabled'=>true,'use_brevo_connection'=>true,'public_origin'=>'http://127.0.0.1:8088'];
  }
  if(!empty($settings['use_brevo_connection'])){$connection=BrevoSettings::load(dirname(__DIR__).'/private/brevo',(string)$config['app_key']);$settings=array_replace($connection,$settings);if($realLocal)$settings['enabled']=($connection['enabled']??false)===true;}
  return ['mailbox'=>false,'delivery'=>new AccountRecoveryDelivery($settings,null,$realLocal,$realLocal?dirname(__DIR__).'/private/account-mail-receipts.jsonl':null)];
 }
}
