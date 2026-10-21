<?php
declare(strict_types=1);
final class AdminReturnContext
{
    public static function safe(mixed $value,string $fallback):string
    {
        if(!is_string($value)||strlen($value)>2000)return $fallback;
        $url=parse_url($value);if(!$url||array_diff(array_keys($url),['path','query'])||!in_array($url['path']??'',['index.php','negocios.php','atencion-comercios.php','solicitudes-cuenta.php'],true))return $fallback;
        parse_str($url['query']??'',$query);$allowed=['tab','q','status','page','pagina','view','query','destination','category','origin','account','plan','verification','destino','categoria','estado','origen','cuenta','suscripcion','verificacion'];$clean=[];
        foreach($query as $key=>$item)if(in_array($key,$allowed,true)&&is_string($item)&&strlen($item)<=240&&!preg_match('/[\x00-\x1f]/',$item))$clean[$key]=$item;
        return $url['path'].($clean?'?'.http_build_query($clean):'');
    }
}
