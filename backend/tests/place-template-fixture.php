<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/views/directory-helpers.php';
require_once dirname(__DIR__).'/app/views/place-copy.php';
require_once dirname(__DIR__).'/app/SavedPlaceAction.php';

/** Synthetic view models exercise the actual template, without any HTML repair step. */
function placeTemplateFixture(string $locale='es',string $slug='jerico',bool $paid=false):string
{
    $bookmark=SavedPlaceAction::context($slug,$locale,false,false);
    $repositoryPath=dirname(__DIR__).'/app/PlaceRepository.php';
    $localePrefix=match($locale){'en'=>'en/','pt-BR'=>'pt-br/',default=>''};
    $localizedRoot='/'.$localePrefix;$name='Lugar <real> & cercano';
    $sectionTitles=placeSectionTitles($locale,$name);
    $t=placeInterfaceCopy($locale)+['featured'=>'Destacado','business_page'=>'Ver página','events_heading'=>'Eventos'];
    $media=['path'=>'assets/approved.webp','alt'=>'Imagen <aprobada>','width'=>800,'height'=>533];
    $places=[['id'=>42,'slug'=>'estacion-tren','type'=>'place','name'=>'Estación <segura>','description'=>'Texto de la base & sin cambios','media'=>[$media],'map_url'=>'https://maps.example.invalid/exact?x=1&y=2','external_url'=>'https://research.example.invalid','external_label'=>'Private research','operators'=>[]]];
    $experiences=[['id'=>43,'slug'=>'tren-sabana','type'=>'experience','name'=>'Viaje en tren','description'=>'Una experiencia real','media'=>[$media],'map_url'=>'','operators'=>[['public_id'=>'00000000-0000-4000-8000-000000000001','plan_code'=>'page','display_name'=>'Operador anunciado']]]];
    $map='https://maps.example.invalid/named-place';$interest='/negocios-locales/interes/?municipio=prueba#contacto';
    $d=['eyebrow'=>'Referencia local','headline'=>'Titular de base','intro'=>'Introducción de base','hero'=>$media,'history'=>['Historia de la base.'],'reasons'=>['Motivo real'],'places'=>[...$places,...$experiences],'categories'=>[],'businesses'=>[],'public_contacts'=>[]];
    if($paid)foreach(['free','basic','featured','page','page_premium'] as $i=>$tier){
        $id=$i+1;$publicId=sprintf('00000000-0000-4000-8000-%012d',$id);
        $d['businesses'][]=['id'=>$id,'public_id'=>$publicId,'plan_code'=>$tier,'display_name'=>'Negocio '.$tier,'summary'=>'Resumen aprobado '.$tier,'features'=>['business_page'=>in_array($tier,['page','page_premium'],true),'business_logo'=>in_array($tier,['page','page_premium'],true),'highlighted_card'=>$tier==='featured'],'image_path'=>'assets/logo.webp','width'=>160,'height'=>160,'code'=>'hospedaje','category_codes'=>['hospedaje'],'category_labels'=>['Hospedaje'],'location_map_url'=>'https://maps.example.invalid/business'];
        // Feature entitlements are part of the input model, as supplied by the real repository.
        $last=array_key_last($d['businesses']);
        $d['businesses'][$last]['features']=array_filter($d['businesses'][$last]['features']);
        $d['public_contacts'][$id]=[['channel'=>'phone','contact_value'=>'+573001234567','is_public'=>1]];
    }
    ob_start();require dirname(__DIR__).'/app/views/place-main.php';return ob_get_clean();
}
