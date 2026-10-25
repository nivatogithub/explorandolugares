<?php
declare(strict_types=1);
/** Uses the same local municipal polygons as coverage. No IP or external geolocation. */
final class BusinessMunicipality {
 private static function key(string $text):string{return strtolower(preg_replace('/[^a-zA-Z0-9]/','',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text)?:$text));}
 public static function forDestination(PDO $pdo,int $id):?array {
  static $cache=[];$key=spl_object_id($pdo).':'.$id;if(array_key_exists($key,$cache))return $cache[$key];
  $q=$pdo->prepare('SELECT d.slug,d.department,d.latitude,d.longitude,d.country_code,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" WHERE d.id=?');$q->execute([$id]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d||$d['country_code']!=='CO')return null;
  $code=null;$referenceCenter=null;$plan=json_decode(file_get_contents(__DIR__.'/content/coverage-plan.json'),true,512,JSON_THROW_ON_ERROR);foreach($plan['departments'] as $department)foreach($department['places'] as $place)if($place['slug']===$d['slug']){$code=$place['municipality_code'];$referenceCenter=$place['map_center']??null;}
  if(!$code){$geo=json_decode(file_get_contents(__DIR__.'/content/account-geography.json'),true,512,JSON_THROW_ON_ERROR);foreach($geo['municipalities'] as $m)if(self::key($m['name'])===self::key($d['display_name'])&&(self::key($d['department'])===self::key($geo['regions']['CO'][$m['department_code']]??'')||$d['department']===$m['department_code'])){$code=$m['code'];break;}}
  if(!$code||!preg_match('/^\d{5}$/D',$code))return $cache[$key]=null;
  $center=is_numeric($d['latitude'])&&is_numeric($d['longitude'])?[(float)$d['latitude'],(float)$d['longitude']]:$referenceCenter;
  $path=dirname(__DIR__).'/public/assets/cobertura/municipios-'.substr($code,0,2).'.geojson';if(!is_file($path))return $cache[$key]=null;$data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);foreach($data['features'] as $feature)if($feature['properties']['code']===$code){if($center&&!self::contains($feature['geometry'],(float)$center[0],(float)$center[1]))$center=null;return $cache[$key]=['code'=>$code,'name'=>$d['display_name'],'geometry'=>$feature['geometry'],'center'=>$center];}return $cache[$key]=null;
 }
 public static function contains(array $geometry,float $lat,float $lon):bool {
  $ring=function(array $points)use($lat,$lon):bool{$inside=false;for($i=0,$j=count($points)-1;$i<count($points);$j=$i++){$a=$points[$i];$b=$points[$j];$cross=($b[0]-$a[0])*($lat-$a[1])-($b[1]-$a[1])*($lon-$a[0]);if(abs($cross)<1e-11&&$lon>=min($a[0],$b[0])&&$lon<=max($a[0],$b[0])&&$lat>=min($a[1],$b[1])&&$lat<=max($a[1],$b[1]))return true;if(($a[1]>$lat)!==($b[1]>$lat)&&$lon<($b[0]-$a[0])*($lat-$a[1])/($b[1]-$a[1])+$a[0])$inside=!$inside;}return $inside;};
  $polygons=$geometry['type']==='Polygon'?[$geometry['coordinates']]:($geometry['type']==='MultiPolygon'?$geometry['coordinates']:[]);foreach($polygons as $p){if(!$ring($p[0]))continue;$hole=false;foreach(array_slice($p,1) as $inner)if($ring($inner))$hole=true;if(!$hole)return true;}return false;
 }
 public static function distance(array $a,array $b):float{$lat=deg2rad($b[0]-$a[0]);$lon=deg2rad($b[1]-$a[1]);$v=sin($lat/2)**2+cos(deg2rad($a[0]))*cos(deg2rad($b[0]))*sin($lon/2)**2;return 6371000*2*atan2(sqrt($v),sqrt(max(0,1-$v)));}
 public static function ruralReference(string $address):bool{return preg_match('/\b(?:km|kil[oó]metros?)\s*\d+(?:[.,]\d+)?\b/iu',$address)===1&&preg_match('/\b(?:v[ií]a|vereda|sector|carretera|casco\s+urbano|salida|finca)\b/iu',$address)===1;}
 public static function validate(PDO $pdo,array $input,array $location):void {
  if($location['latitude']===null||$location['longitude']===null)return;$boundary=self::forDestination($pdo,(int)($input['destination_id']??0));if($boundary&&!self::contains($boundary['geometry'],(float)$location['latitude'],(float)$location['longitude']))throw new RuntimeException('El punto del negocio está fuera del municipio seleccionado.');
  if((($input['location_address_kind']??'')==='rural'||($input['location_source']??'')==='map_rural')&&!self::ruralReference((string)$location['address_text']))throw new RuntimeException('Para una ubicación rural indica el kilómetro y la vía, vereda o referencia desde el casco urbano.');
 }
}
