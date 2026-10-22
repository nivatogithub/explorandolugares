<?php
require_once dirname(__DIR__).'/BusinessContactVisibility.php';
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function u(string $path): string { return $path; }
function mediaPath(?array $media): string { return $media ? u('/'.e($media['path'])) : ''; }
function credit(?array $media): string {
    if (!$media) return '';
    if (in_array($media['origin']??'external',['own','ai_generated'],true)) return '';
    return '<figcaption class="credit"><a href="'.e($media['source_url']).'">'.e($media['creator']).'</a> · <a href="'.e($media['license_url']).'">'.e($media['license_name']).'</a></figcaption>';
}
function contactHref(array $contact): ?string {
    $value=trim((string)($contact['contact_value']??''));
    return match ((string)($contact['channel']??'')) {
        'email' => filter_var($value,FILTER_VALIDATE_EMAIL) ? 'mailto:'.$value : null,
        'phone','phone_fixed' => preg_match('/^\+?[0-9() .-]{7,24}$/',$value) ? 'tel:'.preg_replace('/[^+0-9]/','',$value) : null,
        'whatsapp' => preg_match('/^\+?[0-9() .-]{7,24}$/',$value) ? 'https://wa.me/'.preg_replace('/\D/','',$value) : null,
        'website' => filter_var($value,FILTER_VALIDATE_URL) && in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true) ? $value : null,
        'instagram','facebook','linkedin' => filter_var($value,FILTER_VALIDATE_URL) && in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true) ? $value : null,
        default => null
    };
}
function directoryPlanContacts(array $business,array $contacts,string $locale): string {
    $plan=$business['plan_code']??'free';
    $out=[];
    if($plan!=='free'){
        $map=trim((string)($business['location_map_url']??''));
        if($map===''&&isset($business['latitude'],$business['longitude']))$map='https://www.google.com/maps/search/?api=1&query='.rawurlencode($business['latitude'].','.$business['longitude']);
        if($map===''&&trim((string)($business['address_text']??''))!=='')$map='https://www.google.com/maps/search/?api=1&query='.rawurlencode($business['address_text'].' '.($business['location_reference']??''));
        if($map!==''&&filter_var($map,FILTER_VALIDATE_URL)&&in_array(parse_url($map,PHP_URL_SCHEME),['https','http'],true)){
            $label=$locale==='en'?'Directions':($locale==='pt-BR'?'Como chegar':'Cómo llegar');
            $out[]='<a class="directory-contact-link directory-contact-location" href="'.e($map).'" target="_blank" rel="noopener noreferrer" aria-label="'.e($label).'" title="'.e($label).'">'.BusinessContactVisibility::icon('location').'</a>';
        }
    }
    $seen=[];
    foreach(BusinessContactVisibility::filter($plan,$contacts) as $contact){
        $channel=(string)$contact['channel'];
        if(isset($seen[$channel]))continue;
        $href=contactHref($contact);if(!$href)continue;
        $seen[$channel]=true;
        $label=BusinessContactVisibility::label($channel,$locale);
        $out[]='<a class="directory-contact-link directory-contact-'.e($channel).'" href="'.e($href).'"'.(str_starts_with($href,'http')?' target="_blank" rel="noopener noreferrer"':'').' aria-label="'.e($label).'" title="'.e($label).'">'.BusinessContactVisibility::icon($channel).'</a>';
    }
    return $out?'<nav class="directory-contact-links" aria-label="'.e($locale==='en'?'Business contact':($locale==='pt-BR'?'Contato do negócio':'Contacto del negocio')).'">'.implode(' ',$out).'</nav>':'';
}

function businessInitials(string $name): string {
    preg_match_all('/\b\p{L}/u', trim($name), $matches);
    $letters=array_slice($matches[0]??[],0,2);
    $initials=implode('', $letters);
    return function_exists('mb_strtoupper')?mb_strtoupper($initials,'UTF-8'):strtoupper($initials);
}

function directoryIcon(string $kind,bool $category=false):string {
    static $icons;
    $icons??=json_decode(file_get_contents(dirname(__DIR__).'/presentation/directory-icons.json'),true,32,JSON_THROW_ON_ERROR);
    $path=$category?($icons['categoryIcons'][$kind]??$icons['categoryIcons']['otro']):($icons['icons'][$kind]??$icons['icons']['page']);
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$path.'</svg>';
}
function directoryActions(array $business,array $contacts,string $locale,?string $pageUrl=null):string {
    if($business['plan_code']==='free')return '';
    $links=[];$seen=[];
    $map=trim((string)($business['location_map_url']??''));
    if($map===''&&($business['latitude']??null)!==null&&($business['longitude']??null)!==null)$map='https://www.google.com/maps/search/?api=1&query='.rawurlencode($business['latitude'].','.$business['longitude']);
    if($map===''&&trim((string)($business['address_text']??''))!=='')$map='https://www.google.com/maps/search/?api=1&query='.rawurlencode($business['address_text'].' '.($business['location_reference']??''));
    if($map!==''&&!str_starts_with($map,'#')&&(!filter_var($map,FILTER_VALIDATE_URL)||!in_array(parse_url($map,PHP_URL_SCHEME),['http','https'],true)))$map='';
    if($map!=='')$links[]=['href'=>$map,'kind'=>'map','label'=>$locale==='en'?'Find on map':($locale==='pt-BR'?'Ver localização':'Ver ubicación')];
    foreach(BusinessContactVisibility::filter($business['plan_code'],$contacts) as $contact){
        $channel=$contact['channel'];if(isset($seen[$channel]))continue;$url=contactHref($contact);if(!$url)continue;$seen[$channel]=true;
        $links[]=['href'=>$url,'kind'=>in_array($channel,['phone','phone_fixed'],true)?'phone':$channel,'label'=>BusinessContactVisibility::label($channel,$locale)];
    }
    if($pageUrl)$links[]=['href'=>$pageUrl,'kind'=>'page','label'=>$locale==='en'?'Open business page':($locale==='pt-BR'?'Abrir página do negócio':'Ver página del negocio')];
    $html='';foreach($links as $link)$html.='<a class="directory-icon-action" href="'.e($link['href']).'" aria-label="'.e($link['label']).'" title="'.e($link['label']).'"'.(str_starts_with($link['href'],'http')?' target="_blank" rel="noopener noreferrer"':'').'>'.directoryIcon($link['kind']).'</a>';
    return $html?'<nav class="directory-icon-actions" aria-label="'.e($locale==='en'?'Business actions':($locale==='pt-BR'?'Ações do negócio':'Acciones del negocio')).'">'.$html.'</nav>':'';
}

function destinationRelation(string $destination,string $kind,string $slug,string $locale,array $places):array {
    if($destination!=='zipaquira')return [];
    $slugs=array_column($places,'slug');if(!in_array('estacion-tren',$slugs,true)||!in_array('tren-sabana',$slugs,true))return [];
    $labels=match($locale){'en'=>['See the train experience','See the arrival station'],'pt-BR'=>['Ver a experiência de trem','Ver a estação de chegada'],default=>['Ver la experiencia del tren','Ver la estación de llegada']};
    if($kind==='place'&&$slug==='estacion-tren')return ['id'=>'lugar-estacion-tren','target'=>'experiencia-tren-sabana','label'=>$labels[0]];
    if($kind==='experience'&&$slug==='tren-sabana')return ['id'=>'experiencia-tren-sabana','target'=>'lugar-estacion-tren','label'=>$labels[1]];
    return [];
}
function destinationRelationLink(array $relation):string {
    if(!$relation)return '';
    return '<a class="text-link place-relation-link" data-place-relation href="#'.e($relation['target']).'">'.e($relation['label']).'</a>';
}

/** Exact data-backed links; location is independent of translated descriptions. */
function destinationPointActions(array $place,array $copy,string $locale):string
{
    $html='';$seen=[];
    foreach(['map_url','external_url'] as $key){
        $url=trim((string)($place[$key]??''));
        if($url===''||isset($seen[$url])||!preg_match('~^https?://~i',$url)||!filter_var($url,FILTER_VALIDATE_URL))continue;
        $seen[$url]=true;
        $label=$key==='map_url'?($copy['map']??'Ver ubicación'):($place['external_label']??'');
        if(trim((string)$label)==='')$label=match($locale){'en'=>'More information','pt-BR'=>'Mais informações',default=>'Más información'};
        $html.='<a class="text-link" href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e((string)$label).'</a>';
    }
    return $html;
}
