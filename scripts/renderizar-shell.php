<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/app/PortalPresentation.php';
$contexts=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);$result=[];
foreach($contexts as $key=>$context)$result[$key]=['header'=>PortalPresentation::header($context),'footer'=>PortalPresentation::footer($context)];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
