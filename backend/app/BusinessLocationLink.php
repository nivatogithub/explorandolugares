<?php
declare(strict_types=1);
/** Expose a reviewed establishment link; coordinates are not a public listing. */
final class BusinessLocationLink
{
    public static function forBusiness(array $business,?string $userAgent=null):string
    {
        // Coordinates alone cannot establish the location of a named business.
        $saved=trim((string)($business['location_map_url']??''));
        $url=parse_url($saved);
        $host=strtolower((string)($url['host']??''));$path=(string)($url['path']??'');
        if(filter_var($saved,FILTER_VALIDATE_URL)&&($url['scheme']??'')==='https'
            && (($host==='maps.app.goo.gl'&&$path!==''&&$path!=='/')
                || (in_array($host,['google.com','www.google.com','maps.google.com'],true)&&str_starts_with($path,'/maps/place/'))))return $saved;
        return '';
    }
}
