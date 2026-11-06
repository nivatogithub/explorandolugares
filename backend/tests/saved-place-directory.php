<?php
declare(strict_types=1);
require_once __DIR__.'/support/place-directory.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->exec(file_get_contents(__DIR__.'/../database/destinations.sqlite.sql'));
check_place_directory($p);
$p->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY); INSERT INTO accounts VALUES(1),(2)');
$p->exec(file_get_contents(__DIR__.'/../database/migrations/063-account-saved-places.sqlite.sql'));
$p->exec("INSERT INTO account_saved_places(account_id,place_id) SELECT 1,id FROM destinations WHERE slug='jerico'; INSERT INTO account_saved_places(account_id,place_id) SELECT 2,id FROM destinations WHERE slug='jardin'");
$s=new PlaceDirectoryService(new PlaceDirectorySelector($p));
foreach(['es','en','pt-BR'] as $locale){
 $a=$s->search($locale,['guardados'=>'1','savedAccountId'=>2],1);
 if(array_column($a['rows'],'slug')!==['jerico']||$a['total']!==1)throw new RuntimeException('Private filter isolation failed');
 $b=$s->search($locale,['guardados'=>'1'],2);if(array_column($b['rows'],'slug')!==['jardin'])throw new RuntimeException('Wrong owner');
 if($s->search($locale,['guardados'=>'1','q'=>'Jardín'],1)['total']!==0)throw new RuntimeException('Combined search ignored');
 if(!str_contains(PlaceDirectoryService::url($locale,$a['filters'],2),'guardados=1')||str_contains(PlaceDirectoryService::url($locale,$a['filters'],2),'savedAccountId'))throw new RuntimeException('URL leaked private identity or lost filter');
}
try{$s->search('es',['guardados'=>'1']);throw new LogicException('Anonymous accepted');}catch(RuntimeException){}
$p->exec("UPDATE destinations SET publication_status='draft' WHERE slug='jerico'");if($s->search('es',['guardados'=>'1'],1)['total']!==0)throw new RuntimeException('Unpublished saved place exposed');
echo "OK saved directory: private ownership, forged identity ignored, translations, search, pagination URLs, anonymous denied, unpublished hidden\n";
