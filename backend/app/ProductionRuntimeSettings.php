<?php
declare(strict_types=1);
/** Non-secret deployment flags. Credentials and connection details stay in config.php. */
final class ProductionRuntimeSettings
{
 public static function load(string $directory):array{
  $file=$directory.'/production-runtime-settings.json';if(!is_file($file))return [];
  if(is_link($file))throw new RuntimeException('Configuración de ejecución no válida.');
  $v=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
  if(!is_array($v)||array_diff(array_keys($v),['business_accounts_enabled','unified_admin_only','account_mail']))throw new RuntimeException('Configuración de ejecución no válida.');
  foreach(['business_accounts_enabled','unified_admin_only'] as $k)if(isset($v[$k])&&!is_bool($v[$k]))throw new RuntimeException('Configuración de ejecución no válida.');
  if(isset($v['account_mail'])){if(!is_array($v['account_mail'])||array_diff(array_keys($v['account_mail']),['enabled','use_brevo_connection','public_origin']))throw new RuntimeException('Configuración de correo no válida.');foreach(['enabled','use_brevo_connection'] as $k)if(isset($v['account_mail'][$k])&&!is_bool($v['account_mail'][$k]))throw new RuntimeException('Configuración de correo no válida.');if(!preg_match('~^https://[a-z0-9.-]+$~iD',(string)($v['account_mail']['public_origin']??'')))throw new RuntimeException('Origen de correo no válido.');}
  return $v;
 }
}
