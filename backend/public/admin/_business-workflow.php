<?php
// Shared navigation for the existing package handler and editorial handler.
require_once dirname(__DIR__,2).'/app/AdminReturnContext.php';
$return=AdminReturnContext::safe($_REQUEST['return']??null,'atencion-comercios.php');
$workflowBusiness=(int)$business;
$workflowTab=$workflowTab??'requirements';
$workflowQuery=http_build_query(['business'=>$workflowBusiness,'return'=>$return]);
?>
<a href="/admin/<?=esc($return)?>">← Negocios locales</a>
<nav class="admin-business-tabs" aria-label="Ficha del negocio">
<?php foreach(['requirements'=>'Requisitos','editorial'=>'Edición','package'=>'Paquete','preview'=>'Vista previa','history'=>'Historial'] as $code=>$label):
    $url=$code==='editorial'?'/business/editorial.php?'.$workflowQuery.'&admin=1':'/admin/paquete-editorial.php?'.$workflowQuery.'&tab='.$code;
?><a href="<?=esc($url)?>" <?=$workflowTab===$code?'aria-current="page"':''?>><?=esc($label)?></a><?php endforeach ?>
</nav>
