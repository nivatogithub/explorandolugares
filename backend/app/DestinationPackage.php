<?php
declare(strict_types=1);

final class DestinationPackage
{
    public const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    public const MAX_MANIFEST_BYTES = 2 * 1024 * 1024;
    public const MAX_PLACES = 40;
    private const LOCALES = ['es', 'en', 'pt-BR'];

    /** @return array{manifest: array, images: array<string, array{bytes?:string,path?:string,mime:string,name:string}>} */
    public static function readZip(string $path, ?string $stagingDirectory = null): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Este servidor no tiene habilitada la lectura de archivos ZIP.');
        if (!is_file($path) || filesize($path) > self::MAX_ARCHIVE_BYTES) throw new RuntimeException('El ZIP debe pesar 64 MB o menos.');
        if ($stagingDirectory !== null && (!is_dir($stagingDirectory) || !is_writable($stagingDirectory))) throw new RuntimeException('No se pudo preparar la carpeta temporal del paquete.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('No se pudo abrir el archivo ZIP.');
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_PLACES + 3) throw new RuntimeException('El ZIP debe contener un manifiesto y entre 1 y 41 imágenes.');
            $manifestIndex = $zip->locateName('paquete-destino.json');
            if ($manifestIndex === false) throw new RuntimeException('Falta paquete-destino.json en la raíz del ZIP.');
            $manifestStat = $zip->statIndex($manifestIndex);
            if (!$manifestStat || $manifestStat['size'] > self::MAX_MANIFEST_BYTES) throw new RuntimeException('El manifiesto JSON supera el máximo de 2 MB.');
            $manifestText = $zip->getFromIndex($manifestIndex);
            if (!is_string($manifestText)) throw new RuntimeException('No se pudo leer el manifiesto JSON.');
            try { $manifest = json_decode($manifestText, true, 64, JSON_THROW_ON_ERROR); }
            catch (JsonException) { throw new RuntimeException('El manifiesto no contiene JSON válido.'); }
            self::validateManifest($manifest);

            $referenced = self::imageReferences($manifest);
            if (count($referenced) < 1 || count($referenced) > self::MAX_PLACES + 1) throw new RuntimeException('El manifiesto debe referenciar una portada y hasta 40 imágenes de atractivos.');
            $images = [];
            $seen = [];
            $totalUncompressed = (int)$manifestStat['size'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) throw new RuntimeException('El ZIP contiene una entrada ilegible.');
                $name = (string)$stat['name'];
                if (str_ends_with($name, '/')) continue;
                self::validateArchivePath($name);
                if (isset($seen[$name])) throw new RuntimeException("El ZIP repite el archivo {$name}.");
                $seen[$name] = true;
                $mode = 0;
                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                    $mode = ($attributes >> 16) & 0170000;
                }
                if ($mode === 0120000) throw new RuntimeException('El ZIP no puede incluir enlaces simbólicos.');
                if ($name === 'paquete-destino.json') continue;
                if (!isset($referenced[$name])) throw new RuntimeException("El archivo {$name} no está referenciado en el manifiesto.");
                if ((int)$stat['size'] < 1 || (int)$stat['size'] > self::MAX_IMAGE_BYTES) throw new RuntimeException("La imagen {$name} debe pesar 5 MB o menos.");
                $totalUncompressed += (int)$stat['size'];
                if ($totalUncompressed > 60 * 1024 * 1024) throw new RuntimeException('Las imágenes del paquete superan 60 MB en total. Optimízalas y vuelve a importar.');
                $stream = $zip->getStream($name);
                if (!is_resource($stream)) throw new RuntimeException("No se pudo leer la imagen {$name}.");
                $bytes = '';
                $target = $stagingDirectory !== null ? rtrim($stagingDirectory,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($name) : null;
                $output = $target !== null ? @fopen($target,'xb') : null;
                if ($target !== null && !is_resource($output)) { fclose($stream); throw new RuntimeException('No se pudo guardar una imagen temporal del paquete.'); }
                try {
                    $actualSize = 0;
                    while (!feof($stream)) {
                        $chunk = fread($stream, 1024 * 1024);
                        if ($chunk === false) throw new RuntimeException("No se pudo leer la imagen {$name}.");
                        $actualSize += strlen($chunk);
                        if ($actualSize > self::MAX_IMAGE_BYTES) throw new RuntimeException("La imagen {$name} debe pesar 5 MB o menos.");
                        if ($output !== null) {
                            if (fwrite($output,$chunk)!==strlen($chunk)) throw new RuntimeException('No se pudo guardar una imagen temporal del paquete.');
                        } else $bytes .= $chunk;
                    }
                } finally { fclose($stream); if ($output !== null) fclose($output); }
                if ($actualSize !== (int)$stat['size']) throw new RuntimeException("El tamaño de {$name} no coincide con el ZIP.");
                $image = $target !== null ? self::inspectImageFile($target,$name) : self::inspectImage($bytes,$name);
                $images[$name] = ['bytes'=>$target===null?$bytes:null, 'path'=>$target, 'mime'=>$image['mime'], 'name'=>basename($name)];
            }
            foreach ($referenced as $name => $_) if (!isset($images[$name])) throw new RuntimeException("Falta en el ZIP la imagen {$name} indicada por el manifiesto.");
            return ['manifest'=>$manifest, 'images'=>$images];
        } finally { $zip->close(); }
    }

    public static function validateManifest(mixed $manifest): void
    {
        if (!is_array($manifest) || ($manifest['schema_version'] ?? null) !== 2 || !is_array($manifest['destination'] ?? null) || !is_array($manifest['places'] ?? null)) {
            throw new RuntimeException('Usa el formato de paquete de destino v2 indicado en la guía.');
        }
        $allowedTopLevel=['schema_version','destination','hero_image','places','local_research'];
        if (array_diff(array_keys($manifest),$allowedTopLevel)) throw new RuntimeException('El manifiesto incluye campos que la administración no reconoce.');
        if (count($manifest['places']) < 1 || count($manifest['places']) > self::MAX_PLACES) throw new RuntimeException('El paquete debe incluir entre 1 y 40 atractivos.');
        $destination=$manifest['destination'];
        $type=(string)($destination['destination_type']??'municipality');
        if(!in_array($type,['municipality','city','other'],true)) throw new RuntimeException('El tipo de destino debe ser municipality, city u other.');
        $country=strtoupper((string)($destination['country_code']??''));
        if(!preg_match('/^[A-Z]{2}$/D',$country)) throw new RuntimeException('Incluye el código de país de dos letras en destination.country_code.');
        if (!is_array($manifest['hero_image'] ?? null) || !is_string($manifest['hero_image']['file'] ?? null)) throw new RuntimeException('El manifiesto debe indicar el archivo de portada en hero_image.file.');
        self::validateImageMetadata($manifest['hero_image'],'portada');
        foreach (self::LOCALES as $locale) {
            $translation = $manifest['destination']['translations'][$locale] ?? null;
            if (!is_array($translation)) throw new RuntimeException("Faltan los textos del destino para {$locale}.");
            foreach (['display_name','headline','intro'] as $field) if (trim((string)($translation[$field] ?? '')) === '') throw new RuntimeException("Completa {$field} para {$locale}.");
            if (!is_array($manifest['destination']['history'][$locale] ?? null) || !is_array($manifest['destination']['reasons'][$locale] ?? null)) throw new RuntimeException("Faltan historia o motivos para {$locale}.");
        }
        foreach ($manifest['places'] as $index => $place) {
            if (!is_array($place) || !is_array($place['image'] ?? null) || !is_string($place['image']['file'] ?? null)) throw new RuntimeException('Cada atractivo debe incluir image.file con su imagen.');
            if (!in_array((string)($place['type']??'place'),['place','experience'],true)) throw new RuntimeException('El tipo debe ser place o experience.');
            if (($place['publication_status']??'draft')!=='draft') throw new RuntimeException('Los atractivos de un ZIP nuevo deben quedar en borrador.');
            self::validateImageMetadata($place['image'],'atractivo '.($index+1));
            if (!is_array($place['translations'] ?? null)) throw new RuntimeException('Revisa las traducciones de los atractivos.');
            foreach (self::LOCALES as $locale) {
                $translation = $place['translations'][$locale] ?? null;
                if (!is_array($translation) || trim((string)($translation['name'] ?? '')) === '' || trim((string)($translation['description'] ?? '')) === '') throw new RuntimeException('Cada atractivo necesita nombre y descripción en español, inglés y portugués.');
                $operatorUrl=trim((string)($translation['external_url']??''));
                if($operatorUrl!==''&&(!filter_var($operatorUrl,FILTER_VALIDATE_URL)||parse_url($operatorUrl,PHP_URL_SCHEME)!=='https'))throw new RuntimeException('Los enlaces a operadores deben usar HTTPS.');
                if(($place['type']??'place')==='experience'&&($place['publication_status']??'draft')==='published'&&($operatorUrl===''||trim((string)($translation['external_label']??''))===''))throw new RuntimeException('Antes de publicar una experiencia, incluye el enlace y los datos de contacto confirmados en los tres idiomas.');
            }
        }
        self::imageReferences($manifest);
    }

    private static function validateImageMetadata(array $image,string $label): void
    {
        $origin=(string)($image['image_origin']??'');
        if (!in_array($origin,['ai_generated','own','external'],true)) throw new RuntimeException("El origen de imagen de {$label} no es válido.");
        foreach(self::LOCALES as $locale) if(trim((string)($image['alt'][$locale]??''))==='') throw new RuntimeException("Falta el texto alternativo de {$label} en {$locale}.");
        if($origin==='external') foreach(['image_creator','image_source_url','image_license','image_license_url'] as $key) {
            $value=trim((string)($image[$key]??''));
            if($value==='') throw new RuntimeException("Completa autor, origen y licencia de la imagen externa de {$label}.");
            if(str_ends_with($key,'url')&&(!filter_var($value,FILTER_VALIDATE_URL)||parse_url($value,PHP_URL_SCHEME)!=='https')) throw new RuntimeException("Los enlaces de imagen de {$label} deben ser HTTPS.");
        }
    }

    /** @return array<string, true> */
    private static function imageReferences(array $manifest): array
    {
        $references = [];
        foreach ([$manifest['hero_image']['file'] ?? null, ...array_map(static fn($place) => $place['image']['file'] ?? null, $manifest['places'] ?? [])] as $name) {
            if (!is_string($name)) throw new RuntimeException('Hay una referencia de imagen incompleta.');
            self::validateArchivePath($name);
            if (!str_starts_with($name, 'images/')) throw new RuntimeException('Las imágenes deben guardarse dentro de images/.');
            if (isset($references[$name])) throw new RuntimeException("La imagen {$name} se referencia más de una vez.");
            $references[$name] = true;
        }
        return $references;
    }

    private static function validateArchivePath(string $name): void
    {
        if ($name === 'paquete-destino.json') return;
        if (str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/(^|\/)\.\.?($|\/)/', $name) || !preg_match('/^images\/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpg|jpeg|png|webp)$/iD', $name)) {
            throw new RuntimeException('El ZIP solo puede contener paquete-destino.json e imágenes JPG, PNG o WebP dentro de images/.');
        }
    }

    /** @return array{mime:string,width:int,height:int} */
    public static function inspectImage(string $bytes, string $name): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'el-image-check-');
        if ($tmp === false) throw new RuntimeException('No se pudo validar una imagen del paquete.');
        try {
            file_put_contents($tmp, $bytes);
            return self::inspectImageFile($tmp,$name);
        } finally { @unlink($tmp); }
    }

    private static function inspectImageFile(string $path,string $name): array
    {
        $info=@getimagesize($path);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!$info || !in_array($mime, ['image/jpeg','image/png','image/webp'], true)) throw new RuntimeException("{$name} no es una imagen JPG, PNG o WebP válida.");
        if ($info[0] < 240 || $info[1] < 160 || $info[0] > 10000 || $info[1] > 10000) throw new RuntimeException("{$name} debe medir entre 240 × 160 y 10000 × 10000 píxeles.");
        return ['mime'=>$mime,'width'=>(int)$info[0],'height'=>(int)$info[1]];
    }

    public static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || !str_starts_with(realpath($directory) ?: '', rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'el-destination-package-')) return;
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) if ($entry->isFile() || $entry->isLink()) @unlink($entry->getPathname());
        @rmdir($directory);
    }

    public static function removeExpiredStaging(): void
    {
        $pattern = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'el-destination-package-*';
        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $directory) {
            if ((filemtime($directory) ?: time()) < time() - 86400) self::removeDirectory($directory);
        }
    }
}
