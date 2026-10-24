<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessContactVisibility.php';

/** Registration contract, shared by PHP validation and the browser wizard. */
final class BusinessRegistrationPlan
{
    public static function all():array
    {
        $plans=[];
        foreach(['free'=>0,'featured'=>300,'page'=>450,'page_premium'=>1200] as $code=>$limit){
            $plans[$code]=['description_limit'=>$limit,'channels'=>BusinessContactVisibility::channels($code),
                'location'=>$code!=='free','cover'=>in_array($code,['page','page_premium'],true),
                'products'=>$code==='page_premium'?5:0,'identifier_required'=>true];
        }
        return $plans;
    }

    public static function get(string $code):array
    {
        return self::all()[$code]??throw new RuntimeException('Selecciona una suscripción vigente.');
    }

    /** Do not reject captured text or invalidate wider historical editorial data. */
    public static function editorialCapacity(string $code,int $existing):int
    {
        return max($existing,self::all()[$code]['description_limit']??0);
    }
}
