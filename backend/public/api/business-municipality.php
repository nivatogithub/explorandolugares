<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_once dirname(__DIR__,2).'/app/BusinessMunicipality.php';require_once dirname(__DIR__,2).'/app/PlaceSelector.php';
header('Content-Type: application/json; charset=utf-8');header('X-Robots-Tag: noindex, nofollow');
try{$id=(int)($_GET['destination']??0);if(!(new PlaceSelector(db()))->isPublishedId($id)&&empty($_SESSION['admin'])){http_response_code(404);echo '{"error":"Lugar no disponible"}';exit;}$boundary=BusinessMunicipality::forDestination(db(),$id);if(!$boundary){http_response_code(422);echo '{"error":"No se encontró el límite municipal de este lugar."}';exit;}header('Cache-Control: private, max-age=3600');echo json_encode($boundary,JSON_THROW_ON_ERROR);}catch(Throwable $e){PortalIncidents::technical($e,'business_municipality');http_response_code(503);echo '{"error":"No se pudo comprobar el límite municipal."}';}
