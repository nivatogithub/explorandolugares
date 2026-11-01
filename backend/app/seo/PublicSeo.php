<?php
declare(strict_types=1);

/** Shared metadata for public HTML; callers must pass publication gates first. */
final class PublicSeo
{
    public static function prefix(string $locale): string
    {
        return match($locale){'en'=>'/en','pt-BR'=>'/pt-br',default=>''};
    }

    public static function destinationTitle(string $name,string $locale,string $region=''): string
    {
        $place=$name.($region!==''?', '.$region:'');
        return match($locale){
            'en'=>'Things to do in '.$place,
            'pt-BR'=>'O que fazer em '.$place,
            default=>'Qué ver y hacer en '.$place,
        }.' | Explorando Lugares';
    }

    /** Unicode-safe excerpt, never a cut word or byte. */
    public static function description(string $text,int $limit=160): string
    {
        $text=trim(preg_replace('/\s+/u',' ',strip_tags($text))??'');
        if(mb_strlen($text,'UTF-8')<=$limit)return $text;
        $cut=mb_substr($text,0,$limit-1,'UTF-8');
        $boundary=mb_strrpos($cut,' ',0,'UTF-8');
        if($boundary!==false)$cut=mb_substr($cut,0,$boundary,'UTF-8');
        return rtrim($cut," ,;:-").'…';
    }

    public static function jsonLd(array $graph): string
    {
        // HEX flags prevent editorial text closing the script element.
        return '<script type="application/ld+json">'.json_encode(
            ['@context'=>'https://schema.org','@graph'=>$graph],
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR
        ).'</script>';
    }

    public static function destinationGraph(array $destination,string $canonical,string $origin,string $locale,?string $image): array
    {
        $place=['@type'=>'TouristDestination','@id'=>$canonical.'#place','name'=>$destination['display_name'],'url'=>$canonical];
        if($image)$place['image']=$image;
        $page=['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>self::destinationTitle($destination['display_name'],$locale,(string)($destination['department']??'')),'inLanguage'=>$locale,'about'=>['@id'=>$place['@id']]];
        return [$place,$page,self::breadcrumbs($origin,$locale,$destination['display_name'],$canonical)];
    }

    public static function websiteGraph(string $origin,string $locale): array
    {
        return [
            ['@type'=>'Organization','@id'=>$origin.'/#organization','name'=>'Explorando Lugares','url'=>$origin.'/',
                'logo'=>['@type'=>'ImageObject','url'=>$origin.'/assets/brand/explorando-lugares-logo-v2-transparent.png']],
            ['@type'=>'WebSite','@id'=>$origin.'/#website','name'=>'Explorando Lugares','url'=>$origin.'/',
                'inLanguage'=>['es','en','pt-BR'],'publisher'=>['@id'=>$origin.'/#organization']],
            ['@type'=>'WebPage','@id'=>$origin.self::prefix($locale).'/#webpage','url'=>$origin.self::prefix($locale).'/',
                'inLanguage'=>$locale,'isPartOf'=>['@id'=>$origin.'/#website']],
        ];
    }

    public static function breadcrumbs(string $origin,string $locale,string $name,string $canonical): array
    {
        $prefix=self::prefix($locale);
        $labels=match($locale){'en'=>['Home','Places'],'pt-BR'=>['Início','Lugares'],default=>['Inicio','Lugares']};
        $items=[];
        foreach([[$labels[0],$origin.$prefix.'/'],[$labels[1],$origin.$prefix.'/lugares/'],[$name,$canonical]] as $index=>[$label,$url])
            $items[]=['@type'=>'ListItem','position'=>$index+1,'name'=>$label,'item'=>$url];
        return ['@type'=>'BreadcrumbList','@id'=>$canonical.'#breadcrumbs','itemListElement'=>$items];
    }

    public static function businessGraph(array $business,string $canonical,string $title,string $description,string $locale,?string $image): array
    {
        // Allowlist: no documents, payment, claimant, research or invented hours/ratings.
        $page=['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$title,'description'=>$description,'inLanguage'=>$locale,
            'mainEntity'=>['@type'=>'Organization','name'=>$business['display_name'],'url'=>$canonical]];
        if($image)$page['primaryImageOfPage']=['@type'=>'ImageObject','url'=>$image];
        return [$page];
    }

    public static function social(string $title,string $description,string $canonical,string $locale,?string $image): string
    {
        $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $tags=['og:type'=>'website','og:site_name'=>'Explorando Lugares','og:title'=>$title,'og:description'=>$description,'og:url'=>$canonical,
            'og:locale'=>match($locale){'en'=>'en_US','pt-BR'=>'pt_BR',default=>'es_CO'}];
        if($image)$tags['og:image']=$image;
        $html='';foreach($tags as $key=>$value)$html.='<meta property="'.$key.'" content="'.$escape($value).'">';
        foreach(['card'=>$image?'summary_large_image':'summary','title'=>$title,'description'=>$description]+($image?['image'=>$image]:[]) as $key=>$value)
            $html.='<meta name="twitter:'.$key.'" content="'.$escape($value).'">';
        return $html;
    }

    public static function alternates(array $paths,string $origin): string
    {
        $html='';
        if(count($paths)<2)return $html;
        foreach($paths+(isset($paths['es'])?['x-default'=>$paths['es']]:[]) as $locale=>$path){
            if(!in_array($locale,['es','en','pt-BR','x-default'],true)||!str_starts_with($path,'/')||str_starts_with($path,'//'))continue;
            $html.='<link rel="alternate" hreflang="'.$locale.'" href="'.htmlspecialchars($origin.$path,ENT_QUOTES,'UTF-8').'">';
        }
        return $html;
    }
}
