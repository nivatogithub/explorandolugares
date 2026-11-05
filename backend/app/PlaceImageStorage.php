<?php
declare(strict_types=1);

/** Filesystem adapter, scoped to the new image files of one save operation. */
final class PlaceImageStorage
{
    private const EXTENSIONS=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    private array $createdPaths=[];

    public function __construct(private string $publicRoot) {}

    /** @return array{temporary_path:string,extension:string,width:int,height:int} */
    public function inspect(array $upload): array
    {
        if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name']??''))) {
            throw new RuntimeException('No se pudo recibir la imagen.');
        }
        if ((int)$upload['size']>5*1024*1024) throw new RuntimeException('La imagen debe pesar 5 MB o menos.');
        $info=@getimagesize($upload['tmp_name']);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!$info || !isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException('Usa una imagen JPG, PNG o WebP válida; no se admiten SVG.');
        }
        $width=(int)$info[0];$height=(int)$info[1];
        if ($width<240 || $height<160 || $width>10000 || $height>10000) {
            throw new RuntimeException('La imagen debe medir al menos 240 × 160 píxeles y no superar 10000 píxeles por lado.');
        }
        return ['temporary_path'=>$upload['tmp_name'],'extension'=>self::EXTENSIONS[$mime],'width'=>$width,'height'=>$height];
    }

    /** @param array{temporary_path:string,extension:string,width:int,height:int} $image Result of inspect(). */
    public function store(int $placeId, array $image): string
    {
        if ($placeId<1) throw new InvalidArgumentException('La imagen requiere un lugar con identidad persistida.');
        if (!in_array($image['extension'],self::EXTENSIONS,true)) {
            throw new RuntimeException('Usa una imagen JPG, PNG o WebP válida; no se admiten SVG.');
        }
        // Keep existing storage paths until their independently versioned migration.
        $relative='assets/destinos/'.$placeId;
        $folder=rtrim($this->publicRoot,'/').'/'.$relative;
        if (!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) {
            throw new RuntimeException('No se pudo crear la carpeta de imágenes del lugar.');
        }
        $name='imagen-'.bin2hex(random_bytes(8)).'.'.$image['extension'];
        $full=$folder.'/'.$name;
        if (!move_uploaded_file($image['temporary_path'],$full)) throw new RuntimeException('No se pudo guardar la imagen.');
        $this->createdPaths[]=$full;
        return $relative.'/'.$name;
    }

    /** Compensate a failed transaction; existing files are never added to this journal. */
    public function discardNewFiles(): bool
    {
        $failed=[];
        foreach ($this->createdPaths as $path) {
            if ((file_exists($path)||is_link($path)) && !@unlink($path)) $failed[]=$path;
        }
        $this->createdPaths=$failed;
        return $failed===[];
    }
}
