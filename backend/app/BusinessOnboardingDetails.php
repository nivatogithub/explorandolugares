<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessAdminService.php';
require_once __DIR__.'/BusinessServiceSections.php';
final class BusinessOnboardingDetails
{
    public static function capture(PDO $pdo,array $input,string $primary,string $storage):array
    {
        $result=['location'=>(new BusinessAdminService($pdo,$storage))->submittedLocation($input),'categories'=>BusinessCategories::validate($pdo,$input['category_codes']??[],$primary),'text'=>[],'channels'=>[]];
        $plan=(string)($input['plan_code']??'free');$q=$pdo->prepare('SELECT max_summary_chars,max_description_chars,max_brand_story_chars,max_featured_services_chars FROM subscription_plans WHERE code=? AND is_active=1');$q->execute([$plan]);$quotas=$q->fetch(PDO::FETCH_ASSOC);if(!$quotas)throw new RuntimeException('Selecciona una suscripción vigente.');
        foreach(['summary','description','brand_story','featured_services'] as $key){$limit=(int)$quotas['max_'.$key.'_chars'];$text=trim((string)($input[$key]??''));if(mb_strlen($text)>$limit)throw new RuntimeException('El campo '.$key.' supera el límite de '.$limit.' caracteres de esta suscripción.');$result['text'][$key]=$text;}
        foreach(['email','phone','whatsapp','website','instagram','facebook','linkedin'] as $channel){$value=trim((string)($input['channels'][$channel]??''));if($value==='')continue;if(strlen($value)>500)throw new RuntimeException('El canal es demasiado largo.');$valid=match($channel){'email'=>(bool)filter_var($value,FILTER_VALIDATE_EMAIL),'phone','whatsapp'=>(bool)preg_match('/^\+?[0-9() .-]{7,24}$/D',$value),default=>(bool)filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true)};if(!$valid)throw new RuntimeException('Revisa el canal '.$channel.'.');$result['channels'][$channel]=$value;}
        if(isset($input['service_sections'])){$sections=BusinessServiceSections::validate($input['service_sections']);if($sections&&($input['plan_code']??'')!=='page_premium')throw new RuntimeException('Las secciones de servicios requieren Premium.');$result['service_sections']=$sections;}
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
