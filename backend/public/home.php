<?php
declare(strict_types=1);
if (!isset($locale) || !in_array($locale,['es','en','pt-BR'],true)) {http_response_code(404);exit;}
require_once __DIR__.'/_portal-shell.php';
require_once dirname(__DIR__).'/app/PortalPageData.php';
require_once dirname(__DIR__).'/app/seo/PublicSeo.php';
$copy=json_decode(file_get_contents(dirname(__DIR__).'/app/presentation/home-metadata.json'),true,16,JSON_THROW_ON_ERROR)[$locale];
$languages=['es'=>'/','en'=>'/en/','pt-BR'=>'/pt-br/'];
$context=['locale'=>$locale,'path'=>'/','languages'=>$languages];
$origin=rtrim(getenv('EL_PUBLIC_BASE_URL')?:'https://explorandolugares.com','/');
$attractions=PortalPageData::attractions(dirname(__DIR__).'/private/config.php',$locale,__DIR__);
$manifest=is_file(__DIR__.'/asset-manifest.json')?json_decode(file_get_contents(__DIR__.'/asset-manifest.json'),true):[];
$css='/assets/home-attractions.css';$css.=isset($manifest[$css])?'?v='.$manifest[$css]:'';
$logo='/assets/brand/explorando-lugares-logo-v2-transparent.png';
$escape=[PortalPresentation::class,'escape'];
?><!doctype html><html lang="<?=$escape($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=$escape($copy['description'])?>"><meta name="robots" content="index,follow"><title><?=$escape($copy['title'])?></title><link rel="icon" href="<?=$escape($logo)?>" type="image/png"><link rel="canonical" href="<?=$escape($origin.$languages[$locale])?>"><?=PublicSeo::alternates($languages,$origin)?><?=PublicSeo::social($copy['title'],$copy['description'],$origin.$languages[$locale],$locale,$origin.$logo)?><?=PublicSeo::jsonLd(PublicSeo::websiteGraph($origin,$locale))?><?=PortalPresentation::assets(false,'public','css')?><link rel="stylesheet" href="<?=$escape($css)?>"><?=PortalPresentation::assets(false,'public','js')?></head><body><a class="skip" href="#contenido"><?=$escape(match($locale){'en'=>'Skip to content','pt-BR'=>'Pular para o conteúdo',default=>'Saltar al contenido'})?></a><?=PortalPresentation::header($context)?><?=PortalPresentation::homeMain($locale,$attractions)?><?=PortalPresentation::footer($context)?></body></html>
