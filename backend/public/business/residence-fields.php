<?php
if(!function_exists('db')){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/AccountResidence.php';
require_once dirname(__DIR__,2).'/app/AccountResidencePresentation.php';
AccountResidencePresentation::render(AccountResidencePresentation::context(AccountResidence::catalog(),$_POST,$residenceValues??[]));
