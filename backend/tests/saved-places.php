<?php
require_once __DIR__.'/../app/SavedPlaces.php';
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec('PRAGMA foreign_keys=ON; CREATE TABLE accounts(id INTEGER PRIMARY KEY); CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT); CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,display_name TEXT); INSERT INTO accounts VALUES(1),(2); INSERT INTO destinations VALUES(1,"jerico","published"),(2,"oculto","draft"); INSERT INTO destination_translations VALUES(1,"es","Jericó"),(2,"es","Oculto")');
$sql=file_get_contents(__DIR__.'/../database/migrations/063-account-saved-places.sqlite.sql');$p->exec($sql);$p->exec($sql);$s=new SavedPlaces($p);
$s->change(1,'jerico',true);$s->change(1,'jerico',true);
if(count($s->list(1))!==1||$s->list(2)!==[])throw new Exception('Ownership or duplicate failure');
try{$s->change(1,'oculto',true);throw new LogicException('Draft accepted');}catch(RuntimeException){}
$p->exec('UPDATE destinations SET publication_status="draft" WHERE id=1');if($s->list(1)!==[])throw new Exception('Draft visible');
$p->exec('UPDATE destinations SET publication_status="published" WHERE id=1');$s->change(2,'jerico',false);if(count($s->list(1))!==1)throw new Exception('Cross account removal');$s->change(1,'jerico',false);if($s->list(1)!==[])throw new Exception('Removal failure');
echo "OK saved places: repeat migration, ownership, duplicates, drafts, removal\n";
