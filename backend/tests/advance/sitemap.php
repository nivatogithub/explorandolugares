<?php
declare(strict_types=1);
require __DIR__.'/service.php';require dirname(__DIR__,2).'/app/seo/PublicSitemap.php';
$sitemap=new PublicSitemap($db);$base='https://example.invalid';
$seed='<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.invalid/</loc></url><url><loc>https://example.invalid/destinos/destino-prueba/</loc></url><url><loc>https://example.invalid/admin/index.php</loc></url></urlset>';
$xml=$sitemap->xml($base,$seed);check(!str_contains($xml,'/destinos/destino-prueba/'),'Borrador excluido aunque esté en sitemap anterior');check(!str_contains($xml,'/admin/'),'Privado excluido');
$db->exec("UPDATE destinations SET publication_status='published' WHERE id=1");
$xml=$sitemap->xml($base,$seed);check(substr_count($xml,'https://example.invalid/destinos/destino-prueba/')===1,'Publicación incluida sin duplicados');check(!str_contains($xml,'/en/destinos/'),'No inventar idioma sin traducción');
$db->exec("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,'en','Test destination','Test','Test')");
check(str_contains($sitemap->xml($base,$seed),'/en/destinos/destino-prueba/'),'Traducción publicada incluida');
$db->exec("UPDATE destinations SET publication_status='archived' WHERE id=1");check(!str_contains($sitemap->xml($base,$seed),'/destinos/destino-prueba/'),'Archivo retira URL');
rejects(fn()=>$sitemap->xml($base,'<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><urlset/>'));
rejects(fn()=>$sitemap->xml('https://user:secret@example.invalid',$seed));
echo "OK: sitemap dinámico, publicación/archivo, borradores privados, idiomas existentes, deduplicación y XML sin entidades externas.\n";
