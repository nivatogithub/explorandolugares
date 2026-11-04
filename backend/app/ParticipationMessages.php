<?php
declare(strict_types=1);

/** Interface messages for participation; stored data is never translated here. */
final class ParticipationMessages
{
    public static function locale(mixed $value):string
    {
        return is_string($value)&&in_array($value,['es','en','pt-br'],true)?$value:'es';
    }
    public static function text(string $key,string $locale):string
    {
        static $catalog=null;
        $catalog??=json_decode(file_get_contents(__DIR__.'/presentation/participation-messages.json'),true,64,JSON_THROW_ON_ERROR);
        if(!isset($catalog[$key]))throw new LogicException('Unknown participation message: '.$key);
        return $catalog[$key][$locale]??$catalog[$key]['es'];
    }
}
