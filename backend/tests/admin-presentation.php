<?php
declare(strict_types=1);
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function db():PDO{static $db;if(!$db){$db=new PDO('sqlite::memory:');$db->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY,display_name TEXT)');$db->exec("INSERT INTO accounts VALUES(7,'Nombre largo de administración')");}return $db;}
require dirname(__DIR__).'/public/admin/_admin-layout.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach([0,7] as $id){$_SESSION=['admin'=>true,'admin_account_id'=>$id,'csrf'=>'signed-local-token'];ob_start();admin_layout_account();$out=ob_get_clean();check(str_contains($out,'href="/"'),'Return to public portal');check(str_contains($out,'signed-local-token'),'Signed logout retained');check(str_contains($out,$id?'action="/negocios/salir/"':'name="action" value="logout"'),'Same existing session/logout handler');check(!str_contains($out,'<header')&&!str_contains($out,'<footer'),'No public shell');}
$assets=PortalPresentation::assets(false,'admin');check(!str_contains($assets,'menu-principal.js')&&!str_contains($assets,'account-entry.js')&&!str_contains($assets,'medicion.js'),'No public scripts in private layout');check(str_contains($assets,'admin-theme.css'),'Private styles present');
foreach(glob(dirname(__DIR__).'/public/admin/*.php') as $file){$source=file_get_contents($file);check(!str_contains($source,'PortalPresentation::header')&&!str_contains($source,'PortalPresentation::footer'),'Public shell leaked into '.basename($file));}
echo "OK: independent admin layout, return link, personal/legacy signed logout, no public shell or public runtime.\n";
