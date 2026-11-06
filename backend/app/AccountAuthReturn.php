<?php
declare(strict_types=1);
/** Cancel an account dialog back to a public local page; never to another protected login loop. */
final class AccountAuthReturn
{
 public static function path(mixed $pending,string $referer,string $host):string{
  $candidate=is_string($pending)?$pending:'';
  if($candidate===''){
   $parts=parse_url($referer);
   $expected=parse_url('http://'.$host);
   if(is_array($parts)&&is_array($expected)&&isset($parts['host'],$expected['host'])&&strtolower($parts['host'])===strtolower($expected['host'])&&($parts['port']??null)===($expected['port']??null))$candidate=($parts['path']??'/').(isset($parts['query'])?'?'.$parts['query']:'');
  }
  if($candidate===''||!str_starts_with($candidate,'/')||str_starts_with($candidate,'//')||preg_match('/[\\\\\x00-\x20]|%0[ad]|%5c/i',$candidate))return '/';
  $parts=parse_url($candidate);$path=(string)($parts['path']??'/');
  if(preg_match('~^/(?:en/|pt-br/)?lugares/(?:[a-z0-9-]+/)?$~',$path)){
   parse_str((string)($parts['query']??''),$query);unset($query['guardados']);
   return $path.($query?'?'.http_build_query($query):'');
  }
  // Other account pages may require authentication again; cancel to the public portal.
  return $path==='/'||in_array($path,['/en/','/pt-br/','/planea-tu-visita/','/negocios-locales/','/negocios-locales/suscripciones/'],true)?$candidate:'/';
 }
}
