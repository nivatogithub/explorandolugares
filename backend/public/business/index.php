<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/seo/PublicSeo.php';
header_remove('X-Robots-Tag');
$locale=match($_GET['lang']??''){'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
$labels=match($locale){'en'=>['Create a business account','I already have an account'],'pt-BR'=>['Criar conta empresarial','Já tenho uma conta'],default=>['Crear cuenta de negocio','Ya tengo cuenta']};
$languageQuery=$locale==='es'?'':'?lang='.rawurlencode(strtolower($locale));
$accountAvailable=business_portal_available();
$copy=json_decode(file_get_contents(dirname(__DIR__,2).'/app/presentation/business_landing/metadata.json'),true,16,JSON_THROW_ON_ERROR)[$locale];
$languages=['es'=>'/negocios-locales/','en'=>'/en/negocios-locales/','pt-BR'=>'/pt-br/negocios-locales/'];
$context=['locale'=>$locale,'path'=>'/negocios-locales/','languages'=>$languages];
$origin=rtrim(getenv('EL_PUBLIC_BASE_URL')?:'https://explorandolugares.com','/');
$escape=[PortalPresentation::class,'escape'];
$logo='/assets/brand/explorando-lugares-logo-v2-transparent.png';
?><!doctype html><html lang="<?=$escape($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=$escape($copy['description'])?>"><meta name="robots" content="index,follow"><title><?=$escape($copy['title'])?></title><link rel="canonical" href="<?=$escape($origin.$languages[$locale])?>"><?=PublicSeo::alternates($languages,$origin)?><?=PublicSeo::social($copy['title'],$copy['description'],$origin.$languages[$locale],$locale,$origin.$logo)?><?=PortalPresentation::assets(false,'public','css')?><?=PortalPresentation::assets(false,'public','js')?></head><body><a class="skip" href="#contenido"><?=$escape(match($locale){'en'=>'Skip to content','pt-BR'=>'Pular para o conteúdo',default=>'Saltar al contenido'})?></a><?=PortalPresentation::header($context)?><?php require dirname(__DIR__,2).'/app/presentation/business_landing/'.$locale.'.php'; ?><?=PortalPresentation::footer($context)?></body></html>
