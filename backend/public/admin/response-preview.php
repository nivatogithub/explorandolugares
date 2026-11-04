<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el portal.');}
header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; img-src 'self' https://explorandolugares.com; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'; sandbox");
$id=is_string($_GET['operation']??null)?$_GET['operation']:'';$lead=filter_var($_GET['lead']??null,FILTER_VALIDATE_INT);
if(!$lead||$lead<1||!preg_match('/^[a-f0-9]{32}$/D',$id)){http_response_code(404);exit('Respuesta no encontrada.');}
$q=db()->prepare('SELECT html_body FROM lead_reply_operations WHERE id=? AND lead_id=?');$q->execute([$id,$lead]);$html=$q->fetchColumn();if($html===false){http_response_code(404);exit('Respuesta no encontrada.');}
// Stored HTML is produced only by the versioned, escaped template service.
echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vista previa de respuesta</title><body>'.$html.'</body></html>';
