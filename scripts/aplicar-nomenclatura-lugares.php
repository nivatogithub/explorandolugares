<?php
declare(strict_types=1);
/** Normalize presentation copy in the prepared package. Never rename routes/schema. */
function place_words(string $text):string {
 return preg_replace_callback('~(?<![\pL\pN_/.-])(destinos?|destinations?)(?![\pL\pN_/-]|\.[a-z])~iu',static function($m){
  $word=$m[1];$plural=str_ends_with(strtolower($word),'s');$english=str_starts_with(strtolower($word),'destination');$new=$english?($plural?'places':'place'):($plural?'lugares':'lugar');
  if($word===strtoupper($word))return strtoupper($new);if(ctype_upper($word[0]))return ucfirst($new);return $new;
 },$text);
}
function place_html(string $html):string {
 if(preg_match('~</?(?:script|style)\b~i',$html)){
  $parts=preg_split('~(<(?:script|style)\b[^>]*>.*?</(?:script|style)>)~is',$html,-1,PREG_SPLIT_DELIM_CAPTURE);$result='';foreach($parts as $part){if(preg_match('~</?(?:script|style)\b~i',$part))$result.=$part;else $result.=place_html($part);}return $result;
 }
 return preg_replace_callback('~<[^>]*>|[^<]+~s',static function($m){
  if($m[0][0]!=='<')return place_words($m[0]);
  return preg_replace_callback('~\b(alt|title|aria-label|placeholder|content)\s*=\s*(["\'])(.*?)\2~is',static fn($a)=>$a[1].'='.$a[2].place_words($a[3]).$a[2],$m[0]);
 },$html);
}
function place_literal(string $literal):string {
 $inner=substr($literal,1,-1);
 if(preg_match('~\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|JOIN|WHERE|FROM)\b~i',$inner))return $literal;
 if(str_contains($inner,'<'))$new=place_html($inner);
 elseif(preg_match('/\s/u',$inner)||preg_match('~^(Destinos?|Destinations?|DESTINOS?|DESTINATIONS?)$~D',$inner))$new=place_words($inner);
 else return $literal;
 return $literal[0].$new.substr($literal,-1);
}
function place_js(string $source):string {
 // Leave identifiers, selectors, regexes and application data intact. Dynamic
 // DOM copy is handled by the shared runtime; native prompts need literal copy.
 return preg_replace_callback('~\b(alert|confirm|prompt)(\s*\(\s*)(["\'])([^"\'\r\n]*)\3~',static fn($m)=>$m[1].$m[2].$m[3].place_words($m[4]).$m[3],$source);
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__)return;
$root=realpath($argv[1]??'');if(!$root||!is_dir($root.'/public')||!is_dir($root.'/app'))throw new RuntimeException('Indica la raíz de un paquete preparado.');
$changed=0;
foreach(['public','app'] as $folder)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file){
 if(!$file->isFile()||$file->isLink())continue;$ext=strtolower($file->getExtension());if(!in_array($ext,['html','php','js'],true))continue;
 $source=file_get_contents($file->getPathname());$new=$source;
 if($ext==='php'){$new='';foreach(token_get_all($source) as $token){if(!is_array($token)){$new.=$token;continue;}$new.=match($token[0]){T_INLINE_HTML=>place_html($token[1]),T_CONSTANT_ENCAPSED_STRING=>place_literal($token[1]),default=>$token[1]};}}
 elseif($ext==='html'){$parts=preg_split('~(<(?:script|style)\b[^>]*>.*?</(?:script|style)>)~is',$source,-1,PREG_SPLIT_DELIM_CAPTURE);$new='';foreach($parts as $part)$new.=preg_match('~^<(script|style)\b~i',$part)?$part:place_html($part);}
 else $new=place_js($source);
 if($new!==$source){file_put_contents($file->getPathname(),$new);$changed++;}
}
echo 'Nomenclatura de lugares aplicada a '.$changed." archivos del paquete. Rutas e identificadores conservados.\n";
