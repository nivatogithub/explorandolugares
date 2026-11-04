<?php
declare(strict_types=1);
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function db():PDO{throw new RuntimeException('The layout must not query the database.');}
require dirname(__DIR__).'/app/AdminLayoutContext.php';
require dirname(__DIR__).'/public/admin/_admin-layout.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach([0,7] as $id){$_SESSION=['csrf'=>'must-not-read-session'];$context=AdminLayoutContext::prepare('/admin/places.php',[],$id,$id?'Nombre <administración>':'Administrador','signed-local-token');ob_start();admin_layout_account($context['account']);$out=ob_get_clean();check(str_contains($out,'href="/"'),'Return to public portal');check(str_contains($out,'signed-local-token'),'Signed logout retained');check(str_contains($out,$id?'action="/negocios/salir/"':'name="action" value="logout"'),'Same existing session/logout handler');check(!str_contains($out,'must-not-read-session'),'Layout read session instead of prepared data');if($id)check(str_contains($out,'Nombre &lt;administración&gt;'),'Account name escaping preserved');check(!str_contains($out,'<header')&&!str_contains($out,'<footer'),'No public shell');}
$assets=PortalPresentation::assets(false,'admin');check(!str_contains($assets,'main-menu.js')&&!str_contains($assets,'account-entry.js')&&!str_contains($assets,'analytics.js'),'No public scripts in private layout');check(str_contains($assets,'admin-theme.css'),'Private styles present');
foreach(glob(dirname(__DIR__).'/public/admin/*.php') as $file){$source=file_get_contents($file);check(!str_contains($source,'PortalPresentation::header')&&!str_contains($source,'PortalPresentation::footer'),'Public shell leaked into '.basename($file));}
echo "OK: independent admin layout, return link, personal/legacy signed logout, no public shell or public runtime.\n";
