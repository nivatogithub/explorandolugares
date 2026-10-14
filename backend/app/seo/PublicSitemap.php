<?php
declare(strict_types=1);
final class PublicSitemap {
    public function __construct(private PDO $db) {}
    public function xml(string $base,string $staticXml): string {
        $base=rtrim($base,'/');$parts=parse_url($base);
        if(!filter_var($base,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new InvalidArgumentException('Origen público inválido.');
        $rows=$this->db->query("SELECT d.slug,t.locale FROM destinations d JOIN destination_translations t ON t.destination_id=d.id WHERE d.publication_status='published' AND t.locale IN ('es','en','pt-BR') ORDER BY d.id,t.locale")->fetchAll(PDO::FETCH_ASSOC);
        $urls=[];$published=[];
        foreach($rows as $row){
            if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$row['slug']))continue;
            $prefix=match($row['locale']){'en'=>'/en','pt-BR'=>'/pt-br',default=>''};
            $path=$prefix.'/destinos/'.$row['slug'].'/';$published[$path]=true;$urls[$base.$path]=true;
        }
        if($staticXml!==''){
            if(strlen($staticXml)>10*1024*1024||preg_match('/<!DOCTYPE|<!ENTITY/i',$staticXml))throw new RuntimeException('Sitemap estático inválido.');
            $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
            try{$loaded=$doc->loadXML($staticXml,LIBXML_NONET);libxml_clear_errors();}finally{libxml_use_internal_errors($previous);}
            if(!$loaded||$doc->documentElement?->localName!=='urlset')throw new RuntimeException('Sitemap estático inválido.');
            foreach($doc->getElementsByTagNameNS('http://www.sitemaps.org/schemas/sitemap/0.9','loc') as $loc){
                $url=trim($loc->textContent);if(!str_starts_with($url,$base.'/'))continue;
                $path=substr($url,strlen($base));
                if(str_contains($path,'?')||str_contains($path,'#')||preg_match('~/(admin|api)/~i',$path))continue;
                if(preg_match('~^(/(?:en|pt-br))?/destinos/([a-z0-9-]+)/(.*)$~',$path,$match)){
                    $parent=($match[1]??'').'/destinos/'.$match[2].'/';
                    // Replace destination entries; keep static descendants only under a published parent.
                    if($match[3]===''||$match[3]==='index.html'||!isset($published[$parent]))continue;
                }
                $urls[$url]=true;
            }
        }
        if(count($urls)>50000)throw new RuntimeException('El sitemap requiere partición en un índice.');
        ksort($urls);$doc=new DOMDocument('1.0','UTF-8');$doc->formatOutput=true;
        $root=$doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9','urlset');$doc->appendChild($root);
        foreach(array_keys($urls) as $url){$entry=$doc->createElement('url');$node=$doc->createElement('loc');$node->appendChild($doc->createTextNode($url));$entry->appendChild($node);$root->appendChild($entry);}
        // No guessed lastmod: public content version history is not available yet.
        return $doc->saveXML();
    }
}
