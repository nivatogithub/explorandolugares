<?php
declare(strict_types=1);
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/PortalPresentation.php';
PortalPresentation::setPublicRoot(__DIR__);
function portal_page_context(array $context=[]):array
{
    return $context+['legacy_admin'=>!empty($_SESSION['admin'])&&empty($_SESSION['business_account_id']),'csrf'=>(string)($_SESSION['csrf']??'')];
}
