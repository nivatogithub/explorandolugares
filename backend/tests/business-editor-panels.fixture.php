<?php
declare(strict_types=1);
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function editorialSourceField(string $slot,string $label,array $payload,array $references):void{echo '<label>'.esc($label).'<input type="file" name="reference_images['.esc($slot).']"></label>';}
$admin=true;$service=true;$plan=['code'=>'page_premium','max_summary_chars'=>450,'max_description_chars'=>1200];$opts=['categories'=>[]];$productAssets=[];$references=[];$v=['id'=>1];$_SESSION=['csrf'=>'synthetic'];
$payload=['category_code'=>'hospedaje','translations'=>[],'showcase'=>['products'=>[]]];
foreach(['es','en','pt-BR'] as $locale){$payload['translations'][$locale]=['display_name'=>'Fixture '.$locale,'summary'=>'Breve '.$locale,'description'=>'Descripción '.$locale];foreach([0,3] as $i)$payload['showcase']['products'][$i]['translations'][$locale]=['name'=>'Producto '.$i.' '.$locale,'description'=>'Descripción '.$locale];}
$source=file_get_contents(dirname(__DIR__).'/public/business/editorial.php');
$texts=substr($source,strpos($source,'<fieldset><legend>Contenido de tu suscripción'));
$texts=substr($texts,0,strpos($texts,"\n<?php if(\$plan['code']!=='free')"));
$products=substr($source,strpos($source,'<fieldset data-editor-panels data-product-editor>'));
$products=substr($products,0,strpos($products,'<?php $experienceOptions='));
echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><body><form id="editor">';eval('?>'.$texts);eval('?>'.$products);echo '</form><form id="registration">';require dirname(__DIR__).'/public/business/new-business-fields.php';echo '</form></body></html>';
