<?php
declare(strict_types=1);

// Run only inside the local preproduction container against its MariaDB.
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!is_dir('/srv/state'))throw new RuntimeException('Solo para preproducción local.');
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require '/srv/portal/app/SubscriptionChangeService.php';

$pdo->beginTransaction();
try{
    // This fixture already has a later, paid request; temporarily hide it only
    // within this transaction and roll it back, preserving the actual request.
    $q=$pdo->prepare('UPDATE business_subscriptions SET status="pending" WHERE id=? AND business_id=? AND status="paid_pending_publication"');
    $q->execute([1027,2]);
    if($q->rowCount()!==1)throw new RuntimeException('La solicitud ficticia esperada cambió; no se modificó ningún otro dato.');
    $service=new SubscriptionChangeService($pdo);
    $current=$service->current(2,new DateTimeImmutable('now',new DateTimeZone('UTC')));
    if(!$current||$current['plan_code']!=='featured')throw new RuntimeException('La suscripción ficticia vigente no está disponible.');
    $addonService=new ExperienceOperatorAddon($pdo);
    $business=$pdo->query('SELECT destination_id FROM businesses WHERE id=2')->fetch(PDO::FETCH_ASSOC);
    $options=$addonService->options((int)$business['destination_id']);
    if(!$options)throw new RuntimeException('No hay experiencias publicadas para este negocio de prueba.');
    $addon=$addonService->quote('featured','monthly',(int)$business['destination_id'],(int)$options[0]['id']);
    $quote=$service->quote(2,'featured','monthly',$addon);
    $expected=SubscriptionChangeService::cents($current['quoted_amount'])+SubscriptionChangeService::cents($addon['amount']);
    if($quote['kind']!=='upgrade'||$quote['target_full_cents']!==$expected||$quote['due_cents']<=0||$quote['due_cents']>SubscriptionChangeService::cents($addon['amount']))throw new RuntimeException('La cotización del complemento no conserva la tarifa histórica o el saldo proporcional.');
    $renewal=$service->quote(2,'featured','monthly');
    if($renewal['kind']!=='renewal'||$renewal['effective_at']!==$current['ends_at'])throw new RuntimeException('La renovación normal cambió de comportamiento.');
    echo "OK: complemento calculado sobre la tarifa histórica y renovación conservada; transacción revertida.\n";
}finally{
    $pdo->rollBack();
}
