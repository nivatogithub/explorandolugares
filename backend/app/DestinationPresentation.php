<?php
declare(strict_types=1);
/** Resolve previously client-side editorial transforms before HTML leaves the server. */
final class DestinationPresentation
{
    private DOMDocument $document;
    private DOMXPath $xpath;
    private string $locale;
    private array $data;
    private function __construct(string $html,string $locale,array $data)
    {
        $this->locale=$locale;$this->data=$data;
        $this->document=new DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        $this->document->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        foreach(iterator_to_array($this->document->childNodes) as $node)if($node->nodeType===XML_PI_NODE)$node->parentNode->removeChild($node);
        $this->xpath=new DOMXPath($this->document);
    }
    private static function cls(string $name):string{return "contains(concat(' ',normalize-space(@class),' '),' ".$name." ')";}
    private function nodes(string $query,?DOMNode $within=null):array{return iterator_to_array($this->xpath->query($query,$within));}
    private function first(string $query,?DOMNode $within=null):?DOMNode{return $this->nodes($query,$within)[0]??null;}
    private function remove(string $query,?DOMNode $within=null):void{foreach($this->nodes($query,$within) as $node)$node->parentNode?->removeChild($node);}
    private function text(string $query,string $text,?DOMNode $within=null):void{$node=$this->first($query,$within);if($node)$node->textContent=$text;}
    private function element(string $tag,string $text='',array $attributes=[]):DOMElement
    {
        $node=$this->document->createElement($tag);if($text!=='')$node->appendChild($this->document->createTextNode($text));
        foreach($attributes as $key=>$value)$node->setAttribute($key,(string)$value);return $node;
    }
    private function image(array $media,bool $hero=false):DOMElement
    {
        $alt=$media['alt']??'';if(is_array($alt))$alt=$alt[$this->locale]??$alt['es']??'';
        $attributes=['src'=>'/'.$media['path'],'alt'=>$alt,'width'=>$media['width'],'height'=>$media['height'],'decoding'=>'async'];
        if($hero)$attributes['fetchpriority']='high';else $attributes['loading']='lazy';
        foreach(['srcset','sizes'] as $key)if(isset($media[$key]))$attributes[$key]=$media[$key];
        return $this->element('img','',$attributes);
    }
    private function run():string
    {
        $body=$this->first('//body');$slug=$this->data['slug'];
        if($body){$body->setAttribute('class',trim($body->getAttribute('class').' destination-direct-reading '.$slug.'-compacta'));}
        $copy=$this->data['copy'][$this->locale]??[];
        if(isset($copy['intro']))$this->text('//*['.self::cls('destination-hero').']//*['.self::cls('intro').']',$copy['intro']);
        if(isset($copy['headline']))$this->text('//*['.self::cls('destination-hero').']//h1/em',$copy['headline']);
        $titles=match($this->locale){'en'=>['A brief history','Places to visit','Experiences','Directory','History'],'pt-BR'=>['História em breve','O que conhecer','Experiências','Diretório','História'],default=>['Historia en breve','Qué conocer','Experiencias','Directorio','Historia']};
        $this->remove('//*['.self::cls('pilot-banner').' or '.self::cls('destination-business-link').']');
        $this->remove('//*['.self::cls('destination-hero').']//*['.self::cls('eyebrow').']');
        // Existing data/identity stay unchanged; only the initial representation changes.
        $story=$this->first('//*[@id="descubre"]');
        if($story){
            $this->remove('.//figure|.//aside|.//*['.self::cls('eyebrow').' or '.self::cls('destination-history-photo').']',$story);
            if(!in_array($slug,['jerico','cartagena'],true))$this->remove('.//*['.self::cls('credit').']',$story);
            $this->text('.//h2',$titles[0],$story);
            if(isset($copy['history'])){
                $paragraphs=$this->nodes('.//p[not('.self::cls('credit').')]',$story);$p=array_shift($paragraphs);
                if($p)$p->textContent=is_array($copy['history'])?implode(' ',$copy['history']):$copy['history'];
                else $story->appendChild($this->element('p',(string)$copy['history']));
                foreach($paragraphs as $extra)$extra->parentNode?->removeChild($extra);
            }
        }
        foreach($this->nodes('//a[@href="#descubre"]') as $link)$link->textContent=$titles[4];
        foreach(['que-conocer'=>1,'lugares'=>3] as $id=>$index){
            $section=$this->first('//*[@id="'.$id.'"]');if(!$section)continue;
            $heading=$this->first('./*['.self::cls('sectionhead').']',$section);
            if($heading){$this->remove('.//p',$heading);$this->text('.//h2|.//h3',$titles[$index],$heading);$heading->setAttribute('class',trim($heading->getAttribute('class').' compact-section-heading'));}
        }
        $heading=$this->first('//*['.self::cls('destination-experiences-heading').']');if($heading){$this->remove('.//p',$heading);$this->text('.//h2|.//h3',$titles[2],$heading);}
        foreach(array_reverse($this->nodes('//main//details['.self::cls('place-disclosure').']')) as $detail){
            $parent=$detail->parentNode;
            foreach($this->nodes('./summary/node()|./*['.self::cls('place-disclosure-content').']/node()',$detail) as $child)$parent->insertBefore($child,$detail);
            $parent->removeChild($detail);
        }
        foreach($this->data['remove']??[] as $id)$this->remove('//*[@data-experience-slug="'.$id.'"]');
        foreach($this->nodes('//*[@data-place-slug or @data-experience-slug]') as $card){
            $id=$card->getAttribute('data-place-slug')?:$card->getAttribute('data-experience-slug');
            $description=$this->data['descriptions'][$id][$this->locale]??null;
            if($description!==null)$this->text('./p|./*['.self::cls('destination-experience-copy').']/p',$description,$card);
            if(isset($this->data['images'][$id])){
                $image=$this->image($this->data['images'][$id]);$existing=$this->first('.//img',$card);
                if($existing)$existing->parentNode->replaceChild($image,$existing);else $card->insertBefore($image,$card->firstChild);
            }
            $this->remove('.//*['.self::cls('story-number').']',$card);
        }
        if(isset($this->data['hero'])){
            $figure=$this->first('//*['.self::cls('destination-hero').']//figure');
            if($figure){$this->remove('./node()',$figure);$figure->appendChild($this->image($this->data['hero'],true));}
        }
        if(isset($this->data['cards']))$this->replaceCards();
        $invitation=$this->first('//*['.self::cls('destination-invitation').']');
        if($invitation&&!empty($this->data['copy'])){
            $button=$this->first('.//a['.self::cls('pill').']',$invitation);
            if($button){
                $names=['jerico'=>'Jericó','bogota'=>'Bogotá','cartagena'=>'Cartagena','medellin'=>'Medellín','santa-marta'=>'Santa Marta'];
                $name=$names[$slug]??$slug;
                $title=$copy['business_title']??match($this->locale){'en'=>'Have a business in '.$name.'?','pt-BR'=>'Tem um negócio em '.$name.'?',default=>'¿Tienes un negocio en '.$name.'?'};
                $text=$copy['business_text']??match($this->locale){'en'=>'Leave your details to join the directory.','pt-BR'=>'Deixe seus dados para participar do diretório.',default=>'Déjanos tus datos para participar en el directorio.'};
                $copyNode=$this->element('div');$copyNode->appendChild($this->element('h2',$title));$copyNode->appendChild($this->element('p',$text));
                $button=$button->cloneNode(true);$this->remove('./node()',$invitation);$invitation->appendChild($copyNode);$invitation->appendChild($button);
            }
        }
        if(!empty($this->data['credits_url'])){
            $area=$this->first('//*[@id="que-conocer"]');
            if($area&&!$this->first('.//*['.self::cls('compact-image-credits').']',$area)){
                $credit=$this->element('p','',['class'=>'compact-image-credits']);
                $credit->appendChild($this->element('a',match($this->locale){'en'=>'Image credits','pt-BR'=>'Créditos das imagens',default=>'Créditos de imágenes'},['href'=>$this->data['credits_url'],'target'=>'_blank','rel'=>'noopener']));$area->appendChild($credit);
            }
        }
        $this->remove('//*['.self::cls('destination-experience-copy').']/*['.self::cls('story-number').']');
        $this->remove('//*[@id="lugares"]//*['.self::cls('directory-description').' or '.self::cls('directory-card-details').']');
        // Images are content, not links to a file or a lightbox. Preserve map/business links.
        foreach($this->nodes('//a[.//img or .//picture]') as $link){
            if(preg_match('~\.(?:avif|webp|png|jpe?g|gif|svg)(?:\?.*)?$~i',$link->getAttribute('href'))){
                foreach(['href','target','download','role','tabindex'] as $attribute)$link->removeAttribute($attribute);
                $link->setAttribute('data-static-image','true');
            }
        }
        return $this->document->saveHTML();
    }
    private function replaceCards():void
    {
        foreach(['places'=>['//*[@id="atractivos-destino"]','data-place-slug'],'experiences'=>['//*['.self::cls('destination-experience-list').']','data-experience-slug']] as $kind=>[$query,$attribute]){
            $grid=$this->first($query);if(!$grid)continue;
            $existing=[];foreach($this->nodes('./article',$grid) as $card)$existing[$card->getAttribute($attribute)]=$card;
            $this->remove('./node()',$grid);
            foreach($this->data['cards'][$kind] as $record){
                $id=$record['id'];if(!isset($existing[$id]))continue; // Published repository output is the authority, never a historical JSON flag.
                $t=$record['translations'][$this->locale];
                $card=$this->element('article','',[$attribute=>$id,'data-location-id'=>$record['location_id']??'']);
                foreach($existing[$id]->attributes as $existingAttribute)$card->setAttribute($existingAttribute->name,$existingAttribute->value);
                if($kind==='experiences')$card->setAttribute('class','destination-experience-card');
                $image=$this->image($this->data['images'][$id]);
                if($kind==='places'){$frame=$this->element('div','',['class'=>'highlight-photo']);$frame->appendChild($image);$card->appendChild($frame);$container=$card;}
                else{$card->appendChild($image);$container=$this->element('div','',['class'=>'destination-experience-copy']);$card->appendChild($container);}
                $container->appendChild($this->element($kind==='places'?'h3':'h4',$t['name']));$container->appendChild($this->element('p',$t['description']));
                if(!empty($record['source_url']))$container->appendChild($this->element('a',$this->data['copy'][$this->locale]['source_label']??'Fuente',['class'=>'text-link','href'=>$record['source_url'],'target'=>'_blank','rel'=>'noopener noreferrer']));
                // Preserve paid operator links, even when the old browser replacement discarded them.
                if(isset($existing[$id]))foreach($this->nodes('.//*['.self::cls('experience-paid-operators').']',$existing[$id]) as $operators)$container->appendChild($operators);
                $grid->appendChild($card);
            }
        }
    }
    public static function data(string $slug):array
    {
        static $cache=[];if(isset($cache[$slug]))return $cache[$slug];
        if(!preg_match('/^[a-z0-9-]+$/D',$slug))return [];
        $file=__DIR__.'/presentation/destinations/'.$slug.'.json';
        return $cache[$slug]=is_file($file)?json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR):[];
    }
    public static function media(string $slug,?string $place=null,string $locale='es'):?array
    {
        $data=self::data($slug);$media=$place===null?($data['hero']??null):($data['images'][$place]??null);
        if($media&&is_array($media['alt']??null))$media['alt']=$media['alt'][$locale]??$media['alt']['es']??'';
        return $media;
    }
    public static function normalize(string $html,string $slug,string $locale):string
    {
        $file=__DIR__.'/presentation/destinations/'.$slug.'.json';
        if(!is_file($file)){
            if(!in_array($slug,['zipaquira','santa-fe-de-antioquia','jardin','covenas'],true))return $html;
            $data=['copy'=>[]];
        }else $data=self::data($slug);
        $data['slug']=$slug;
        return (new self($html,$locale,$data))->run();
    }
}
