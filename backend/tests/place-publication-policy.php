<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PlacePublicationPolicy.php';

$checks=0;
function policy_rejects(callable $operation, string $message): void
{
    global $checks;
    try { $operation(); }
    catch (RuntimeException $error) {
        if ($error->getMessage() !== $message) throw new LogicException('Publication rejection message changed.');
        $checks++;return;
    }
    throw new LogicException('An invalid publication was accepted.');
}
function policy_accepts(callable $operation): void
{
    global $checks;
    $operation();$checks++;
}

$locales=['es','en','pt-BR'];$translations=[];$history=[];$reasons=[];$attractionTranslations=[];
foreach($locales as $locale){
    $translations[$locale]=['display_name'=>'Lugar sintético','eyebrow'=>'Referencia editorial','headline'=>'Contenido revisado','intro'=>'Descripción revisada','meta_description'=>'Información del lugar'];
    $history[$locale]=['Historia revisada'];$reasons[$locale]=['Motivo revisado'];
    $attractionTranslations[$locale]=['name'=>'Sitio sintético','description'=>'Descripción revisada','external_url'=>'','external_label'=>''];
}
$attractions=[['status'=>'published'],['status'=>'draft'],['status'=>'archived']];
policy_accepts(static fn()=>PlacePublicationPolicy::assertInitialState(0,'draft'));
foreach(['published','archived'] as $status)policy_rejects(static fn()=>PlacePublicationPolicy::assertInitialState(0,$status),'Los lugares nuevos siempre se crean como borrador. Guárdalo primero, revísalo y luego podrás publicarlo.');
foreach(['draft','published','archived'] as $status)policy_accepts(static fn()=>PlacePublicationPolicy::assertInitialState(1,$status));

foreach(['borrador local','local draft','rascunho local','imágenes definitivas pendientes','final images pending','imagens finais pendentes'] as $marker){
    foreach(['eyebrow','headline','intro','meta_description'] as $field){
        $copy=$translations['es'];$copy[$field]=$marker;
        policy_rejects(static fn()=>PlacePublicationPolicy::assertEditorialCopy($copy,'published'),'Retira los avisos internos de borrador antes de publicar el lugar.');
        policy_accepts(static fn()=>PlacePublicationPolicy::assertEditorialCopy($copy,'draft'));
    }
}
policy_accepts(static fn()=>PlacePublicationPolicy::assertEditorialCopy($translations['es'],'published'));
policy_accepts(static fn()=>PlacePublicationPolicy::assertAttractionTranslations($attractionTranslations,'published',$locales));
foreach($locales as $locale){
    foreach(['name','description'] as $field){
        $missing=$attractionTranslations;$missing[$locale][$field]='';
        policy_rejects(static fn()=>PlacePublicationPolicy::assertAttractionTranslations($missing,'published',$locales),"Completa la traducción del atractivo publicado en {$locale}.");
        policy_accepts(static fn()=>PlacePublicationPolicy::assertAttractionTranslations($missing,'draft',$locales));
    }
    $external=$attractionTranslations;$external[$locale]['external_url']='https://example.invalid/';
    policy_rejects(static fn()=>PlacePublicationPolicy::assertAttractionTranslations($external,'published',$locales),"Completa el texto del enlace externo en {$locale}.");
    $external[$locale]['external_label']='Fuente documental';
    policy_accepts(static fn()=>PlacePublicationPolicy::assertAttractionTranslations($external,'published',$locales));
}
policy_accepts(static fn()=>PlacePublicationPolicy::assertReadyToPublish($translations,$history,$reasons,$attractions,$locales));
foreach($locales as $locale){
    foreach(['display_name','headline','intro','history','reasons'] as $field){
        $tr=$translations;$h=$history;$r=$reasons;
        if($field==='history')$h[$locale]=[];
        elseif($field==='reasons')$r[$locale]=[];
        else $tr[$locale][$field]='';
        policy_rejects(static fn()=>PlacePublicationPolicy::assertReadyToPublish($tr,$h,$r,$attractions,$locales),"Para publicar completa título, introducción, historia y motivos en los tres idiomas; falta contenido en {$locale}.");
    }
}
foreach([[],[['status'=>'draft']],[['status'=>'archived']]] as $unpublished){
    policy_rejects(static fn()=>PlacePublicationPolicy::assertReadyToPublish($translations,$history,$reasons,$unpublished,$locales),'Publicar requiere al menos un atractivo con estado «Publicado».');
}
echo 'OK: '.$checks." publication-policy cases preserve draft creation, three-language content, editorial notices and attraction requirements.\n";
