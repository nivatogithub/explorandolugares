<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AdminLayoutContext.php';
require dirname(__DIR__).'/app/AdminAccountSelector.php';
function layout_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$routes=[
 ['index.php',[],'requests'],['index.php',['tab'=>'sistema'],'system'],['index.php',['tab'=>'unknown'],'requests'],
 ['email-templates.php',[],'email-workspace'],['request-response.php',[],'requests'],
 ['request-details.php',['type'=>'account'],'account-applications'],['request-details.php',['tipo'=>'account'],'account-applications'],
 ['request-details.php',['type'=>'lead','tipo'=>'account'],'requests'],['request-details.php',['type'=>['account']],'requests'],
 ['business-support.php',[],'businesses'],['business-editorial.php',[],'businesses'],['editorial.php',[],'businesses'],
 ['editorial-package.php',[],'businesses'],['businesses.php',[],'businesses'],['transfer-businesses.php',[],'businesses'],
 ['import-businesses.php',[],'businesses'],['business-contacts.php',[],'campaigns'],['update-businesses.php',[],'businesses'],
 ['campaigns.php',['tab'=>'conexion'],'brevo-connection'],['campaigns.php',[],'campaigns'],
 ['claims.php',[],'account-applications'],['account-applications.php',[],'account-applications'],['accounts.php',[],'accounts'],
 ['subscriptions.php',[],'subscriptions'],['coupons.php',[],'subscriptions'],['places.php',[],'places'],
 ['paquete-destino-v2.php',[],'places'],['reports.php',[],'reports'],['public-requests.php',[],'public-requests'],
 ['payment-settings.php',[],'payment-settings'],['settings.php',[],'configuration'],['database.php',[],'database'],
 ['investors.php',[],'investor-intelligence'],['costs.php',[],'costs'],['cancellations.php',[],'cancellations'],
 ['incidents.php',[],'incidents'],['unknown.php',[],''],['',[],'requests'],
];
foreach($routes as [$path,$query,$expected]){
 $uri=$path===''?'':'/admin/'.$path.'?ignored=1';
 layout_check(AdminLayoutContext::activeItem($uri,$query)===$expected,'Incorrect navigation context: '.$path);
}
foreach([0,7] as $id){
 $context=AdminLayoutContext::prepare('/admin/places.php',[],$id,'Nombre <privado>','signed-token');
 layout_check($context['account']===['id'=>$id,'display_name'=>'Nombre <privado>','csrf'=>'signed-token','logout_url'=>$id?'/negocios/salir/':'/admin/index.php'],'Account context changed.');
 layout_check(!isset($context['account']['role'])&&!isset($context['account']['password_hash']),'Presentation context grew authorization/private fields.');
}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY,display_name TEXT)');
$insert=$pdo->prepare('INSERT INTO accounts VALUES(?,?)');foreach([1=>'Nombre público de cuenta',2=>'',3=>'0',4=>'Cuenta <script>'] as $id=>$name)$insert->execute([$id,$name]);
$selector=new AdminAccountSelector($pdo);
foreach([1=>'Nombre público de cuenta',2=>'Administrador',3=>'Administrador',4=>'Cuenta <script>',999=>'Administrador'] as $id=>$expected)layout_check($selector->displayName($id)===$expected,'Account projection fallback changed.');
echo 'OK: '.count($routes)." route contexts, account/logout data and five minimal account projections preserve existing behavior.\n";
