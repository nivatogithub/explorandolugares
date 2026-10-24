<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessAdminService.php';
require_once __DIR__.'/BusinessServiceSections.php';
require_once __DIR__.'/BusinessContactVisibility.php';
require_once __DIR__.'/BusinessRegistrationPlan.php';
final class BusinessOnboardingDetails
{
    public static function capture(PDO $pdo,array $input,string $primary,string $storage):array
    {
        $result=['location'=>(new BusinessAdminService($pdo,$storage))->submittedLocation($input),'categories'=>BusinessCategories::validate($pdo,$input['category_codes']??[],$primary),'text'=>[],'channels'=>[]];
        $plan=(string)($input['plan_code']??'free');
        if(!in_array($plan,['free','featured','page','page_premium'],true))throw new RuntimeException('Selecciona una suscripción vigente.');
        $rules=BusinessRegistrationPlan::get($plan);
        // One description only; its capacity grows with the selected plan.
        $limits=['summary'=>$rules['description_limit'],'description'=>0,'brand_story'=>0,'featured_services'=>0];
        foreach($limits as $key=>$limit){$text=trim((string)($input[$key]??''));if(mb_strlen($text)>$limit)throw new RuntimeException($limit===0?'Este campo no corresponde a la suscripción elegida.':'El campo supera el límite de '.$limit.' caracteres.');$result['text'][$key]=$text;}
        require_once __DIR__.'/BusinessPhone.php';$input['channels']=BusinessPhone::capture($input,(array)($input['channels']??[]));
        foreach(BusinessContactVisibility::channels($plan) as $channel){$value=trim((string)($input['channels'][$channel]??''));if($value==='')continue;if(strlen($value)>500)throw new RuntimeException('El canal es demasiado largo.');$valid=match($channel){'email'=>(bool)filter_var($value,FILTER_VALIDATE_EMAIL),'phone','phone_fixed','whatsapp'=>(bool)preg_match('/^\+?[0-9() .-]{7,24}$/D',$value),default=>(bool)filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true)};if(!$valid)throw new RuntimeException('Revisa el canal '.$channel.'.');$result['channels'][$channel]=$value;}
        if($rules['location']){
            if($result['text']['summary']==='')throw new RuntimeException('Completa la descripción del negocio.');
            if(trim((string)($result['location']['address_text']??''))==='')throw new RuntimeException('Completa la dirección del negocio.');
            if(empty($result['channels']['phone'])&&empty($result['channels']['phone_fixed']))throw new RuntimeException('Completa un teléfono de contacto del negocio.');
        }
        $products=[];foreach((array)($input['products']??[]) as $index=>$product){if(!is_array($product))continue;$title=trim((string)($product['title']??''));$summary=trim((string)($product['summary']??''));if($title===''&&$summary==='')continue;if($plan!=='page_premium')throw new RuntimeException('Los productos solo están disponibles para Premium.');if(!ctype_digit((string)$index)||(int)$index>4||mb_strlen($title)<2||mb_strlen($title)>100||mb_strlen($summary)>180)throw new RuntimeException('Cada producto debe tener un título y una descripción de máximo 180 caracteres.');$products[$index]=['title'=>$title,'summary'=>$summary];}
        if(count($products)>5)throw new RuntimeException('Premium permite hasta cinco productos o servicios.');$result['products']=$products;
        return $result;
    }
    public static function apply(PDO $pdo,int $id,string $primary,array $details):void
    {
        if(!$details)return;
        if(!empty($details['service_sections']))BusinessServiceSections::save($pdo,$id,$details['service_sections']);
        $keys=['address_text','location_reference','latitude','longitude','location_source','location_map_url','location_confirmed','opening_hours','price_range','capacity_count','menu_url','reservations_url'];$values=[];
        foreach($keys as $key)$values[]=$details['location'][$key]??null;
        $pdo->prepare('UPDATE businesses SET '.implode(',',array_map(static fn($k)=>$k.'=?',$keys)).' WHERE id=?')->execute([...$values,$id]);
        BusinessCategories::replace($pdo,$id,$primary,$details['categories']??[$primary]);
        $text=$details['text']??[];$pdo->prepare('UPDATE business_translations SET summary=?,description=?,brand_story=?,featured_services=? WHERE business_id=? AND locale="es"')->execute([$text['summary']??'',$text['description']??'',$text['brand_story']??'',$text['featured_services']??'',$id]);
        $q=$pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public,display_order) VALUES(?,?,?,0,?)');$order=0;foreach($details['channels']??[] as $channel=>$value)$q->execute([$id,$channel,$value,$order++]);
    }
}
