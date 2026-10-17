<?php
// Only this disposable local container has access to the root Unix socket.
$root = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=explorando_preprod;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$configFile='/srv/state/private/config.php';
if (!is_file($configFile)) {
 $password=bin2hex(random_bytes(24));
 $config=['dsn'=>'mysql:host=127.0.0.1;dbname=explorando_preprod;charset=utf8mb4','db_user'=>'portal_local','db_password'=>$password,'app_key'=>bin2hex(random_bytes(32)), 'admin_user'=>'localadmin','admin_password_hash'=>password_hash(getenv('PREPROD_ADMIN_PASSWORD'),PASSWORD_DEFAULT),'registration_enabled'=>true,'business_accounts_enabled'=>true,'local_test'=>true,'privacy'=>['operator'=>'Entorno local de pruebas','contact'=>'local@example.invalid','retention'=>'Solo datos locales de prueba','retention_en'=>'Local test data only']];
 file_put_contents($configFile,"<?php\nreturn ".var_export($config,true).";\n");chmod($configFile,0600);
}
$config=require $configFile;
$root->exec("CREATE USER IF NOT EXISTS 'portal_local'@'127.0.0.1' IDENTIFIED BY ".$root->quote($config['db_password']));
$root->exec("GRANT ALL ON explorando_preprod.* TO 'portal_local'@'127.0.0.1'");
require '/srv/portal/app/DatabaseMaintenance.php';
$maintenance=new DatabaseMaintenance($root,'/srv/portal/app','/srv/portal/public');
$state=$maintenance->inspect();
if($state['empty_database'])$maintenance->installBaseSchema();
$maintenance->applyMigrations();
if($maintenance->inspect()['catalog_empty'])$maintenance->importInitialCatalog();
require '/srv/portal/app/analytics/AnalyticsService.php';
require '/srv/portal/app/analytics/BasicAudienceService.php';
$analytics=new AnalyticsService($root,$config['app_key']);
if(!$analytics->ready())$analytics->install(json_decode(file_get_contents('/srv/portal/app/content/coverage-plan.json'),true),json_decode(file_get_contents('/srv/portal/app/content/coverage-geography.json'),true));
$basic=new BasicAudienceService($root,'/srv/portal/public');
$basic->install();
echo "Preproducción local inicializada. No se usaron datos ni configuración de producción.\n";
