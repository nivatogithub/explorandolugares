<?php
declare(strict_types=1);
/** Read-only administrative views; never approves, creates, associates or publishes. */
final class AdminBusinessOperations
{
    public static function applications(PDO $db):array
    {
        return $db->query('SELECT r.id,r.business_id,r.business_name,r.destination_id,r.category_code,r.status,r.application_type,r.business_details_json,r.created_at,dt.display_name AS destination_name FROM business_account_applications r JOIN destination_translations dt ON dt.destination_id=r.destination_id AND dt.locale="es" ORDER BY r.created_at DESC,r.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function summarize(array $rows,array $applications):array
    {
        $byBusiness=[];
        foreach($applications as $application)if($application['business_id'])$byBusiness[(int)$application['business_id']][]=$application;
        foreach($rows as &$row){
            $row['applications']=$byBusiness[(int)$row['id']]??[];
            $row['pending_applications']=count(array_filter($row['applications'],static fn($item)=>in_array($item['status'],['pending','in_review'],true)));
            $row['in_attention']=count($row['applications'])>0;
            $row['in_review']=$row['publication_status']==='draft'||$row['pending_applications']>0;
        }unset($row);
        return $rows;
    }
    public static function matches(array $row,array $filters):bool
    {
        foreach(['destination'=>'destination_id','status'=>'publication_status','origin'=>'record_origin','verification'=>'information_status'] as $filter=>$field)if(($filters[$filter]??'')!==''&&(string)$filters[$filter]!==(string)$row[$field])return false;
        if(($filters['category']??'')!==''&&!in_array($filters['category'],$row['category_codes']??[$row['category_code']],true))return false;
        if(($filters['query']??'')!==''&&mb_stripos($row['display_name'].' '.$row['destination_name'],$filters['query'])===false)return false;
        if(($filters['account']??'')==='yes'&&$row['account_email']==='')return false;
        if(($filters['account']??'')==='no'&&$row['account_email']!=='')return false;
        $plan=$row['plan_code']?:'free';
        return ($filters['plan']??'')===''||$filters['plan']===$plan;
    }
    public static function accountLabel(array $row):string
    {
        if($row['account_email']!=='')return $row['account_email'];
        $approved=array_filter($row['applications']??[],static fn($item)=>$item['status']==='approved');
        if($row['record_origin']==='owner_submitted'||$approved)return 'Revisar asociación: falta la cuenta responsable';
        return $row['record_origin']==='public_import'?'Importado por el portal · sin responsable':($row['record_origin']==='admin_created'?'Creado en administración · sin responsable':'Origen pendiente de clasificar · sin responsable');
    }
    public static function informationLabel(string $status):string
    {
        return ['PUBLICO'=>'Fuente pública; sin confirmar','VERIFICADO'=>'Datos verificados','ACTIVO'=>'Activo','PENDIENTE_REVISION'=>'Por revisar'][$status]??$status;
    }
    public static function subscriptionLabel(string $status):string
    {
        return ['sin suscripción'=>'Gratis por defecto','pending_publication'=>'Pendiente de publicación','paid_pending_publication'=>'Pago confirmado; pendiente de publicación','active'=>'Activa','expired'=>'Vencida','cancelled'=>'Cancelada','pending_payment'=>'Pago pendiente'][$status]??$status;
    }
    public static function nextStep(array $row):string
    {
        if($row['pending_applications']>0)return 'Solicitud pendiente: abrir lo enviado y revisar su estado.';
        if($row['publication_status']==='archived')return 'Retirado del sitio; consultar el registro.';
        if($row['publication_status']==='published')return 'Publicado; consultar ficha y suscripción.';
        if($row['subscription_status']==='sin suscripción')return 'Gratis por defecto · pendiente de publicación.';
        if($row['operation_status']==='CERRADO_DEFINITIVO')return 'Operación cerrada: revisar la ficha antes de publicar.';
        return 'Publicación pendiente. La revisión individual comprueba los requisitos completos.';
    }
}
