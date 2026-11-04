<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_admin_session();
require_once dirname(__DIR__,2).'/app/OperationReportSelector.php';
$data=(new OperationReportSelector(db()))->all();
$reports=[
 'Publicación de negocios'=>$data['business_publication'],
 'Suscripciones por nivel y estado'=>$data['subscriptions'],
 'Cuentas por estado'=>$data['accounts'],
 'Solicitudes de negocios'=>$data['business_applications'],
 'Solicitudes de usuarios'=>$data['public_requests'],
];
$adminLayout=admin_layout_context();$adminPublicRoot=__DIR__;
$adminCss='../assets/admin.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/admin.css'),0,12);
$identityCss='/assets/portal-identity.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/portal-identity.css'),0,12);
require dirname(__DIR__,2).'/app/views/admin/reports.php';
