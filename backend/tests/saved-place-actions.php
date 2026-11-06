<?php
declare(strict_types=1);
require_once __DIR__.'/../app/SavedPlaceIntent.php';
require_once __DIR__.'/../app/views/directory-helpers.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec("CREATE TABLE accounts(id INTEGER PRIMARY KEY);CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT);CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,display_name TEXT);INSERT INTO accounts VALUES(1),(2);INSERT INTO destinations VALUES(1,'jerico','published');INSERT INTO destination_translations VALUES(1,'es','Jericó')");
$p->exec(file_get_contents(__DIR__.'/../database/migrations/063-account-saved-places.sqlite.sql'));
$s=[];SavedPlaceIntent::remember($s,'jerico','en');if($p->query('SELECT COUNT(*) FROM account_saved_places')->fetchColumn()!=0)throw new Exception('GET intent wrote data');
if(SavedPlaceIntent::complete($p,$s,1)!=='/en/lugares/jerico/'||SavedPlaceIntent::complete($p,$s,1)!==null)throw new Exception('Intent return or single use failed');
if(!(new SavedPlaceSelector($p))->contains(1,'jerico')||(new SavedPlaceSelector($p))->contains(2,'jerico'))throw new Exception('Saved state wrong owner');
SavedPlaceIntent::remember($s,'jerico','es');$s['saved_place_intent']['created']=time()-3601;if(SavedPlaceIntent::complete($p,$s,2)!==null)throw new Exception('Expired intent consumed');
foreach(['es','en','pt-BR'] as $locale)foreach([[false,false],[true,false],[true,true]] as [$auth,$saved]){
 $bookmark=SavedPlaceAction::context('jerico',$locale,$auth,$saved,'test-token');ob_start();require __DIR__.'/../app/views/saved-place-action.php';$html=ob_get_clean();
 if(str_contains($html,'<script')||str_contains($html,'Guardar Jericó'))throw new Exception('Unexpected script or confirmation');
 if($auth&&(!str_contains($html,'method="post"')||!str_contains($html,'test-token')||!str_contains($html,'aria-pressed="'.($saved?'true':'false').'"')))throw new Exception('Missing form or state');
 if($saved&&!str_contains($html,'fill="currentColor"'))throw new Exception('Saved bookmark unfilled');
 if(!$auth&&(!str_contains($html,'<a ')||!str_contains($html,'title="')))throw new Exception('Anonymous affordance missing');
}
echo "OK bookmark: server-rendered anonymous/unsaved/saved in 3 languages; pending intent does not write, authenticated completion single-use, expiration, ownership\n";

if(!SavedPlaceAction::accessNoticeRequired([],['session_return'=>'/lugares/?guardados=1']))throw new Exception('Saved filter needs explanation');
if(!SavedPlaceAction::accessNoticeRequired([],['session_return'=>'/en/lugares/?q=Jerico&guardados=1']))throw new Exception('Localized saved filter needs explanation');
if(!SavedPlaceAction::accessNoticeRequired([],['saved_place_intent'=>['slug'=>'jerico']]))throw new Exception('Bookmark needs explanation');
if(SavedPlaceAction::accessNoticeRequired([],['session_return'=>'/negocios/portal/'])||SavedPlaceAction::accessNoticeRequired([],['session_return'=>'/lugares/?q=Jerico']))throw new Exception('Unrelated access got saved-place explanation');
echo "OK account-benefit explanation for save and private filters, unrelated access unchanged\n";
