<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/app/DestinationPresentation.php';
$public=$argv[1];$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public,FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->getExtension()!=='html')continue;
    $relative=substr($file->getPathname(),strlen($public));
    if(!preg_match('~^/(?:(en|pt-br)/)?(?:destinos|lugares)/([a-z0-9-]+)/index\.html$~',$relative,$match))continue;
    $locale=($match[1]??'')==='pt-br'?'pt-BR':(($match[1]??'')==='en'?'en':'es');
    $source=file_get_contents($file->getPathname());$result=DestinationPresentation::normalize($source,$match[2],$locale);
    if($result!==$source){file_put_contents($file->getPathname(),$result);$count++;}
}
echo "Contenido inicial de lugares: $count páginas\n";
