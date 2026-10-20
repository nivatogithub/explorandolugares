<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/PremiumBusinessProfile.php';
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
        // Same publication and current plan gates as the public business renderer.
        $businesses=$this->db->query('SELECT b.id,b.public_id,t.locale,sp.code AS plan_code FROM businesses b
            JOIN destinations d ON d.id=b.destination_id AND d.publication_status="published"
            JOIN business_translations t ON t.business_id=b.id
            JOIN service_category_translations ct ON ct.category_code=b.category_code AND LOWER(ct.locale)=LOWER(t.locale)
            JOIN subscription_plans sp ON sp.code=COALESCE((SELECT bs.plan_code FROM business_subscriptions bs
                WHERE bs.business_id=b.id AND bs.status="active"
                AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP)
                AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                ORDER BY bs.id DESC LIMIT 1),"free")
            WHERE b.publication_status="published" AND b.information_status<>"PENDIENTE_REVISION"
            AND b.operation_status<>"CERRADO_DEFINITIVO" AND sp.has_business_page=1
            AND EXISTS(SELECT 1 FROM subscription_plan_features f WHERE f.plan_code=sp.code AND f.feature_code="business_page" AND f.included=1)
            AND t.locale IN ("es","en","pt-BR")')->fetchAll(PDO::FETCH_ASSOC);
        foreach($businesses as $row){
            if(!preg_match('/^[a-f0-9-]{16,64}$/iD',$row['public_id']))continue;
            $prefix=match($row['locale']){'en'=>'/en','pt-BR'=>'/pt-br',default=>''};
            $alias=$row['plan_code']==='page_premium'?PremiumBusinessProfile::currentAlias($this->db,(int)$row['id']):'';
            $urls[$base.$prefix.'/negocios/'.($alias!==''?'perfil/'.$alias:$row['public_id']).'/']=true;
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
                // Business URLs always come from current database eligibility.
                if(preg_match('~^/(?:en/|pt-br/)?(?:negocios|business)/~i',$path))continue;
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
