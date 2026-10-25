<?php
declare(strict_types=1);
/** Publication requirement; approved exceptions and retirements use stable identities. */
final class GeolocationPolicy
{
    public static function rules(): array {static $rules=null;return $rules??=json_decode(file_get_contents(__DIR__.'/content/geolocation-policy.json'),true,512,JSON_THROW_ON_ERROR);}
    public static function hasPoint(mixed $lat,mixed $lon):bool {return $lat!==null&&$lon!==null&&$lat!==''&&$lon!==''&&is_numeric($lat)&&is_numeric($lon)&&is_finite((float)$lat)&&is_finite((float)$lon)&&(float)$lat>=-90&&(float)$lat<=90&&(float)$lon>=-180&&(float)$lon<=180;}
    public static function requirePoint(mixed $lat,mixed $lon,string $label):void {if(!self::hasPoint($lat,$lon))throw new RuntimeException('Para publicar '.$label.' registra latitud y longitud válidas del sitio. Un enlace de búsqueda no sustituye las coordenadas.');}
    public static function placeExempt(string $destination,string $slug,string $type):bool {if($type==='experience')return true;foreach(self::rules()['place_exceptions'] as $r)if($r['destination']===$destination&&$r['slug']===$slug)return true;return false;}
    public static function business(PDO $db,int $id):void {
        $q=$db->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$id]);$b=$q->fetch(PDO::FETCH_ASSOC);if(!$b)throw new RuntimeException('El negocio no existe.');
        if(in_array(($b['public_id']??''),self::rules()['retired_public_ids'],true))throw new RuntimeException('Esta ficha fue retirada del catálogo aprobado. Revisa su identidad y geolocalización antes de autorizar su reincorporación.');
        if(($b['category_code']??'')==='experiencias'||in_array(($b['public_id']??''),self::rules()['business_exceptions'],true))return;
        self::requirePoint($b['latitude'],$b['longitude'],'un negocio con sitio físico');
    }
}
