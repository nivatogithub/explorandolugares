<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessSubscriptionChoice.php';
require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';
require_once dirname(__DIR__,2).'/app/SubscriptionPricing.php';
require_once dirname(__DIR__,2).'/app/SubscriptionExperienceSelector.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{$experiences=(new SubscriptionExperienceSelector(db()))->published();echo json_encode(['experiences'=>$experiences,'plans'=>(new BusinessSubscriptionChoice(db()))->options(),'operator'=>(new ExperienceOperatorAddon(db()))->prices(),'periods'=>(new SubscriptionPricing(db()))->periods()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
catch(Throwable $error){if(class_exists('PortalIncidents',false))PortalIncidents::technical($error,'subscription_prices');error_log('subscription-prices: '.$error->getMessage());http_response_code(503);echo '{"error":"Tarifas temporalmente no disponibles"}';}
