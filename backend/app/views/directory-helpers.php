<?php
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
        'instagram','facebook' => filter_var($value,FILTER_VALIDATE_URL) && in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true) ? $value : null,
        default => null
    };
}
function directoryPlanContacts(array $business,array $contacts,string $locale): string {
    $plan=$business['plan_code']??'free';
    if(!in_array($plan,['page','page_premium'],true))return '';
    $channels=$plan==='page_premium'?['whatsapp','instagram','email']:['whatsapp'];
    $out=[];
    foreach($channels as $channel){
        foreach($contacts as $contact){
            if(($contact['channel']??'')!==$channel)continue;
            $href=contactHref($contact);if(!$href)continue;
            $label=match($channel){'whatsapp'=>'WhatsApp','instagram'=>'Instagram',default=>$locale==='en'?'Email':($locale==='pt-BR'?'E-mail':'Correo')};
            $out[]='<a class="text-link directory-contact-link directory-contact-'.e($channel).'" href="'.e($href).'"'.(str_starts_with($href,'http')?' target="_blank" rel="noopener noreferrer"':'').'>'.e($label).'</a>';
            break;
        }
    }
    return $out?'<nav class="directory-contact-links" aria-label="'.e($locale==='en'?'Business contact':($locale==='pt-BR'?'Contato do negócio':'Contacto del negocio')).'">'.implode(' ',$out).'</nav>':'';
}

function businessInitials(string $name): string {
    preg_match_all('/\b\p{L}/u', trim($name), $matches);
    $letters=array_slice($matches[0]??[],0,2);
    $initials=implode('', $letters);
    return function_exists('mb_strtoupper')?mb_strtoupper($initials,'UTF-8'):strtoupper($initials);
}
