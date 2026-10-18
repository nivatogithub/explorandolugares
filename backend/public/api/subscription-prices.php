<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessSubscriptionChoice.php';
require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{$experiences=db()->query('SELECT p.id,p.destination_id,t.name,dt.display_name AS destination_name,d.country_code,d.department FROM points_of_interest p JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale="es" JOIN destinations d ON d.id=p.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es" WHERE p.poi_type="experience" AND p.publication_status="published" AND d.publication_status="published" ORDER BY dt.display_name,t.name')->fetchAll(PDO::FETCH_ASSOC);echo json_encode(['experiences'=>$experiences,'plans'=>(new BusinessSubscriptionChoice(db()))->options(),'operator'=>(new ExperienceOperatorAddon(db()))->prices()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
catch(Throwable $error){error_log('subscription-prices: '.$error->getMessage());http_response_code(503);echo '{"error":"Tarifas temporalmente no disponibles"}';}
