<?php
declare(strict_types=1);
/** Navigate to a business's stored point; preserve verified listings as fallback. */
final class BusinessLocationLink
{
    /** Publication uses the confirmed stored point, not an optional legacy URL. */
    public static function hasConfirmedPoint(array $business):bool
    {
        return !empty($business['location_confirmed']) && self::hasCoordinates($business);
    }

    private static function hasCoordinates(array $business):bool
    {
        $lat=$business['latitude']??null;$lon=$business['longitude']??null;
        return is_numeric($lat)&&is_numeric($lon)&&is_finite((float)$lat)&&is_finite((float)$lon)&&abs((float)$lat)<=90&&abs((float)$lon)<=180;
    }

    public static function forBusiness(array $business,?string $userAgent=null):string
    {
        $lat=$business['latitude']??null;$lon=$business['longitude']??null;
        if(self::hasCoordinates($business)){
            $number=static fn($v)=>rtrim(rtrim(sprintf('%.7F',(float)$v),'0'),'.');
            $point=$number($lat).','.$number($lon);
            $name=trim((string)($business['display_name']??''));
            $agent=$userAgent??(string)($_SERVER['HTTP_USER_AGENT']??'');
            if(stripos($agent,'Android')!==false)return 'geo:'.$point.'?q='.rawurlencode($point.($name!==''?' ('.$name.')':''));
            if(preg_match('/iPhone|iPad|iPod/i',$agent)||(stripos($agent,'Macintosh')!==false&&stripos($agent,'Mobile')!==false))return 'https://maps.apple.com/?ll='.rawurlencode($point).'&q='.rawurlencode($name!==''?$name:$point);
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($point);
        }
        // Without a usable stored point, preserve only verified establishment links.
        $saved=trim((string)($business['location_map_url']??''));
        $url=parse_url($saved);
        $host=strtolower((string)($url['host']??''));$path=(string)($url['path']??'');
        if(filter_var($saved,FILTER_VALIDATE_URL)&&($url['scheme']??'')==='https'
            && (($host==='maps.app.goo.gl'&&$path!==''&&$path!=='/')
                || (in_array($host,['google.com','www.google.com','maps.google.com'],true)&&str_starts_with($path,'/maps/place/'))))return $saved;
        return '';
    }
}
