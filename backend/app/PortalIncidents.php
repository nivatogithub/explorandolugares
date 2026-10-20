<?php
declare(strict_types=1);
/** Private, bounded technical diagnostics. Never records messages, arguments or request payloads. */
final class PortalIncidents
{
 private static string $directory='';
 private static string $request='';
 private static bool $installed=false;
 private static bool $busy=false;
 public static function configure(string $directory):void{self::$directory=$directory;}
 public static function install(string $directory):void{
  self::configure($directory);if(self::$installed)return;self::$installed=true;ini_set('display_errors','0');
  set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{
   if(!(error_reporting()&$severity))return false;
   $ref=self::capture(new ErrorException('',0,$severity,$file,$line),'php_warning');
   if(in_array($severity,[E_USER_ERROR,E_RECOVERABLE_ERROR],true)){self::respond($ref);exit(1);}return true;
  });
  set_exception_handler(static function(Throwable $error):void{$ref=self::capture($error,'unhandled');self::respond($ref);});
  register_shutdown_function(static function():void{$e=error_get_last();if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){$ref=self::capture(new ErrorException('',0,$e['type'],$e['file'],$e['line']),'fatal');self::respond($ref);}});
 }
 public static function capture(Throwable $error,string $component):string{
  self::$request=self::$request?:'EL-'.bin2hex(random_bytes(8));$ref=self::$request;
  if(self::$busy||self::$directory==='')return $ref;
  self::$busy=true;$lock=null;
  try{
   $dir=self::$directory;if(is_link($dir))throw new RuntimeException();
   if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException();
   $file=$dir.'/current.jsonl';$old=$dir.'/previous.jsonl';$lockPath=$dir.'/write.lock';
   foreach([$file,$old,$lockPath] as $path)if(is_link($path))throw new RuntimeException();
   $lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();chmod($lockPath,0600);
   clearstatcache(true,$file);if(is_file($file)&&filesize($file)>=1048576){if(is_file($old)&&!unlink($old))throw new RuntimeException();if(!rename($file,$old))throw new RuntimeException();}
   $path=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:'';
   // Only known application paths, never user-generated slugs or query values.
   $route=preg_match('~^/(?:api|admin|business)/[a-z-]+\.php$~D',$path)?$path:(preg_match('~^/(?:negocios|cuenta)/[a-z-]+/?$~D',$path)?$path:'public-page');
   $relative=static function(string $path):string{$root=dirname(__DIR__).'/';return str_starts_with($path,$root)?substr($path,strlen($root)):basename($path);};
   $trace=[];foreach(array_slice($error->getTrace(),0,8) as $frame)$trace[]=['file'=>$relative((string)($frame['file']??'')),'line'=>(int)($frame['line']??0),'function'=>substr((string)($frame['function']??''),0,100)];
   $row=['reference'=>$ref,'time'=>gmdate('c'),'component'=>preg_replace('/[^a-z0-9_-]/i','',substr($component,0,60)),'class'=>get_class($error),'code'=>substr(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$error->getCode()),0,20),'file'=>$relative($error->getFile()),'line'=>$error->getLine(),'route'=>$route,'trace'=>$trace];
   $row['fingerprint']=substr(hash('sha256',$row['class'].'|'.$row['file'].'|'.$row['line'].'|'.$row['component']),0,16);
   $encoded=json_encode($row,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
   $f=fopen($file,'ab');if(!$f)throw new RuntimeException();try{if(fwrite($f,$encoded)!==strlen($encoded))throw new RuntimeException();fflush($f);}finally{fclose($f);}chmod($file,0600);
  }catch(Throwable){error_log('Explorando Lugares: no se pudo guardar incidencia '.$ref);}
  finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}self::$busy=false;}
  return $ref;
 }
 public static function recent(int $limit=100):array{
  $rows=[];foreach(['previous.jsonl','current.jsonl'] as $name){$path=self::$directory.'/'.$name;if(!is_file($path)||is_link($path))continue;$f=fopen($path,'rb');if(!$f)continue;try{while(($line=fgets($f,16384))!==false){$row=json_decode($line,true);if(is_array($row)&&isset($row['reference'],$row['time'],$row['component']))$rows[]=$row;if(count($rows)>500)array_shift($rows);}}finally{fclose($f);}}
  return array_slice(array_reverse($rows),0,max(1,min(200,$limit)));
 }
 public static function technical(Throwable $error,string $component):?string{
  if($error instanceof PDOException||$error instanceof Error||$error instanceof ErrorException||$error->getPrevious())return self::capture($error,$component);
  return null;
 }
 private static function respond(string $reference):void{
  if(PHP_SAPI==='cli'){fwrite(STDERR,'Fallo técnico. Referencia: '.$reference."\n");exit(1);}
  if(!headers_sent()){http_response_code(503);header('Cache-Control: no-store');}
  $message='No pudimos completar la operación. Inténtalo de nuevo. Referencia: '.$reference;
  if(str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/api/')||str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')){if(!headers_sent())header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message,'reference'=>$reference]);}
  else echo htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 }
}
