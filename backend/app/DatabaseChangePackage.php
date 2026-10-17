<?php
declare(strict_types=1);
/** No uploaded SQL is executed: bytes must match registered server migrations exactly. */
final class DatabaseChangePackage
{
    public static function validate(string $path, array $catalog): array
    {
        if(!is_file($path)||filesize($path)>2097152)throw new RuntimeException('Paquete ausente o superior a 2 MB.');
        if(!class_exists(ZipArchive::class))throw new RuntimeException('La extensión ZIP no está disponible.');
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('No es un ZIP válido.');
        try{
            if($zip->numFiles<2||$zip->numFiles>65)throw new RuntimeException('Cantidad de archivos no permitida.');
            $files=[];$total=0;
            for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$name=$stat['name'];$total+=$stat['size'];if($total>4194304||$stat['size']>1048576||isset($files[$name])||!preg_match('/^(manifest\.json|[0-9]{3}-[a-z0-9-]+\.mysql\.sql)$/D',$name))throw new RuntimeException('ZIP con nombres, rutas, duplicados o tamaños no permitidos.');$files[$name]=true;}
            $raw=$zip->getFromName('manifest.json');if(!is_string($raw)||strlen($raw)>65536)throw new RuntimeException('Falta un manifiesto válido.');
            $manifest=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(($manifest['format']??null)!=='explorandolugares-db-v1'||($manifest['engine']??null)!=='mysql-mariadb'||!is_array($manifest['migrations']??null)||!array_is_list($manifest['migrations'])||!$manifest['migrations'])throw new RuntimeException('Formato o motor no permitido.');
            $ids=[];$expected=['manifest.json'];
            foreach($manifest['migrations'] as $item){if(!is_array($item))throw new RuntimeException('Entrada de manifiesto inválida.');$id=$item['id']??'';if(!is_string($id)||!isset($catalog[$id])||in_array($id,$ids,true))throw new RuntimeException('Migración desconocida o repetida. Despliega primero su definición revisada.');$known=$catalog[$id];if(($item['file']??null)!==$known['file']||($item['sha256']??null)!==$known['sha256'])throw new RuntimeException('El manifiesto no coincide con la versión desplegada.');$sql=$zip->getFromName($known['file']);if(!is_string($sql)||!hash_equals($known['sha256'],hash('sha256',$sql)))throw new RuntimeException('El script fue modificado o falta.');$ids[]=$id;$expected[]=$known['file'];}
            if(array_diff(array_keys($files),$expected)||array_diff($expected,array_keys($files)))throw new RuntimeException('El ZIP contiene archivos extra o faltantes.');
            $ordered=array_values(array_intersect(array_keys($catalog),$ids));if($ordered!==$ids)throw new RuntimeException('Las migraciones no están en el orden registrado.');
            return $ids;
        }finally{$zip->close();}
    }
}
