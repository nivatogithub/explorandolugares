<?php
declare(strict_types=1);
/** Render navigation from the stored point; the capture map is not a navigation provider. */
final class BusinessLocationLink
{
    public static function forBusiness(array $business,?string $userAgent=null):string
    {
        $lat=$business['latitude']??null;$lon=$business['longitude']??null;
        if(!is_numeric($lat)||!is_numeric($lon)||!is_finite((float)$lat)||!is_finite((float)$lon)||abs((float)$lat)>90||abs((float)$lon)>180){
            $saved=trim((string)($business['location_map_url']??''));
            return filter_var($saved,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($saved,PHP_URL_SCHEME)),['https','http'],true)?$saved:'';
        }
        $number=static fn($v)=>rtrim(rtrim(sprintf('%.7F',(float)$v),'0'),'.');
        $point=$number($lat).','.$number($lon);
        $name=trim((string)($business['display_name']??''));
        $agent=$userAgent??(string)($_SERVER['HTTP_USER_AGENT']??'');
        if(stripos($agent,'Android')!==false)return 'geo:'.$point.'?q='.rawurlencode($point.($name!==''?' ('.$name.')':''));
        if(preg_match('/iPhone|iPad|iPod/i',$agent)||(stripos($agent,'Macintosh')!==false&&stripos($agent,'Mobile')!==false)){
            return 'https://maps.apple.com/?ll='.rawurlencode($point).'&q='.rawurlencode($name!==''?$name:$point);
        }
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($point);
    }
}
