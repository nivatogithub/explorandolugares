<?php
declare(strict_types=1);

/** Reads a business import ZIP without extracting untrusted paths into the site. */
final class BusinessPackage
{
    public const MAX_ARCHIVE_BYTES=64*1024*1024;
    public const MAX_IMAGE_BYTES=5*1024*1024;
    public const MAX_IMAGE_COUNT=100;
    public const MAX_BUSINESSES=500;

    /** @return array{manifest:array,images:array<string,array{path:string,name:string,mime:string,size:int,width:int,height:int}>} */
    public static function readZip(string $path,string $stagingDirectory):array
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('Este servidor no tiene habilitada la lectura de archivos ZIP.');
        if(!is_file($path)||(int)filesize($path)>self::MAX_ARCHIVE_BYTES)throw new RuntimeException('El ZIP debe pesar 64 MB o menos.');
        if(!is_dir($stagingDirectory)||!is_writable($stagingDirectory))throw new RuntimeException('No se pudo preparar el espacio temporal del paquete.');
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('No se pudo abrir el ZIP.');
        try{
            if($zip->numFiles<1||$zip->numFiles>self::MAX_IMAGE_COUNT+2)throw new RuntimeException('El paquete tiene demasiados archivos.');
            $manifestIndex=$zip->locateName('paquete-negocios.json');if($manifestIndex===false)throw new RuntimeException('El ZIP debe incluir paquete-negocios.json en la raíz.');
            $manifestStat=$zip->statIndex($manifestIndex);if(!$manifestStat||(int)$manifestStat['size']>2*1024*1024)throw new RuntimeException('El manifiesto JSON supera 2 MB.');
            $json=$zip->getFromIndex($manifestIndex);if(!is_string($json))throw new RuntimeException('No se pudo leer paquete-negocios.json.');
            try{$manifest=json_decode($json,true,128,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('paquete-negocios.json no contiene JSON válido.');}
            self::validateManifest($manifest);
            $references=self::imageReferences($manifest);$images=[];$seen=[];$total=(int)$manifestStat['size'];
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i);if(!$stat)throw new RuntimeException('El ZIP contiene una entrada ilegible.');
                $name=(string)$stat['name'];if(str_ends_with($name,'/'))continue;
                self::validateArchivePath($name);if(isset($seen[$name]))throw new RuntimeException("El ZIP repite el archivo {$name}.");$seen[$name]=true;
                $mode=0;if(method_exists($zip,'getExternalAttributesIndex')){$zip->getExternalAttributesIndex($i,$opsys,$attributes);$mode=($attributes>>16)&0170000;}
                if($mode===0120000)throw new RuntimeException('El ZIP no puede incluir enlaces simbólicos.');
                if($name==='paquete-negocios.json')continue;
                if(!isset($references[$name]))throw new RuntimeException("La imagen {$name} no está referenciada por ningún negocio.");
                $size=(int)$stat['size'];if($size<1||$size>self::MAX_IMAGE_BYTES)throw new RuntimeException("Cada imagen debe pesar máximo 5 MB: {$name}.");
                $total+=$size;if($total>60*1024*1024)throw new RuntimeException('Las imágenes superan 60 MB en total. Optimízalas y vuelve a crear el ZIP.');
                $stream=$zip->getStream($name);if(!is_resource($stream))throw new RuntimeException("No se pudo leer {$name}.");
                $target=rtrim($stagingDirectory,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.bin2hex(random_bytes(16));$output=@fopen($target,'xb');if(!is_resource($output)){fclose($stream);throw new RuntimeException('No se pudo preparar una imagen temporal.');}
                $written=0;try{while(!feof($stream)){$chunk=fread($stream,1024*1024);if($chunk===false)throw new RuntimeException("No se pudo leer {$name}.");$written+=strlen($chunk);if($written>self::MAX_IMAGE_BYTES)throw new RuntimeException("Cada imagen debe pesar máximo 5 MB: {$name}.");if(fwrite($output,$chunk)!==strlen($chunk))throw new RuntimeException('No se pudo guardar una imagen temporal.');}}finally{fclose($stream);fclose($output);}
                if($written!==$size){@unlink($target);throw new RuntimeException("El tamaño de {$name} no coincide con el ZIP.");}
                $info=@getimagesize($target);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($target);
                if(!$info||$mime!==($info['mime']??'')||!in_array($mime,['image/jpeg','image/png','image/webp'],true)){@unlink($target);throw new RuntimeException("{$name} no es una imagen JPG, PNG o WebP válida.");}
                if($info[0]<240||$info[1]<160||$info[0]>10000||$info[1]>10000){@unlink($target);throw new RuntimeException("{$name} debe medir entre 240 × 160 y 10000 × 10000 píxeles.");}
                $images[$name]=['path'=>$target,'name'=>basename($name),'mime'=>$mime,'size'=>$written,'width'=>(int)$info[0],'height'=>(int)$info[1]];
            }
            foreach($references as $name=>$_)if(!isset($images[$name]))throw new RuntimeException("Falta {$name}, indicado en paquete-negocios.json.");
            return ['manifest'=>$manifest,'images'=>$images];
        }finally{$zip->close();}
    }

    public static function validateManifest(mixed $manifest):void
    {
        if(!is_array($manifest)||($manifest['schema_version']??'')!=='business-import-v1')throw new RuntimeException('Usa el formato business-import-v1 de la plantilla.');
        if(array_diff(array_keys($manifest),['schema_version','destination_slug','businesses']))throw new RuntimeException('El manifiesto incluye propiedades desconocidas.');
        $businesses=$manifest['businesses']??null;if(!is_array($businesses)||count($businesses)<1||count($businesses)>self::MAX_BUSINESSES)throw new RuntimeException('El paquete debe incluir entre 1 y 500 negocios.');
        foreach($businesses as $index=>$business){
            if(!is_array($business))throw new RuntimeException('El negocio '.($index+1).' debe ser un objeto.');
            if(array_diff(array_keys($business),['category_code','category_codes','information_status','name','summary','description','sources','image_references']))throw new RuntimeException('El negocio '.($index+1).' tiene campos desconocidos.');
        }
        self::imageReferences($manifest);
    }

    /** @return array<string,true> */
    public static function imageReferences(array $manifest):array
    {
        $references=[];foreach($manifest['businesses']??[] as $business){$items=$business['image_references']??[];if(!is_array($items)||count($items)>10)throw new RuntimeException('Cada negocio puede incluir hasta 10 imágenes de referencia.');foreach($items as $image){if(!is_array($image))throw new RuntimeException('Referencia de imagen no válida.');$file=(string)($image['file']??'');self::validateArchivePath($file);if(!str_starts_with($file,'images/'))throw new RuntimeException('Guarda las imágenes dentro de images/.');if(isset($references[$file]))throw new RuntimeException("La imagen {$file} está asociada más de una vez.");$references[$file]=true;}}
        if(count($references)>self::MAX_IMAGE_COUNT)throw new RuntimeException('El ZIP admite hasta 100 imágenes de referencia.');return $references;
    }

    private static function validateArchivePath(string $name):void
    {
        if($name==='paquete-negocios.json')return;
        if(str_contains($name,'\\')||str_starts_with($name,'/')||preg_match('~(^|/)\.\.?($|/)~',$name)||!preg_match('~^images/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpg|jpeg|png|webp)$~iD',$name))throw new RuntimeException('El ZIP solo puede incluir paquete-negocios.json e imágenes JPG, PNG o WebP dentro de images/.');
    }

    public static function removeDirectory(string $directory):void
    {
        $real=realpath($directory);$tmp=realpath(sys_get_temp_dir());if(!$real||!$tmp||!str_starts_with($real,$tmp.DIRECTORY_SEPARATOR.'el-business-package-'))return;
        foreach(new FilesystemIterator($real,FilesystemIterator::SKIP_DOTS) as $entry)if($entry->isFile()||$entry->isLink())@unlink($entry->getPathname());@rmdir($real);
    }
}
