<?php
declare(strict_types=1);
/** One shared navigation and visual shell for all private administration screens. */
function adminPageStart(string $active,string $title,string $description,string $base='',array $styles=[]): void {
    $signedIn=!empty($_SESSION['admin']);
    $groups=[
        'Visión general'=>['reports'=>['reports.php','Reportes']],
        'Contenido'=>['destinations'=>['destinos.php','Destinos'],'establishments'=>['advance/index.php?section=establishments','Negocios']],
        'Relaciones y comunicación'=>['contacts'=>['advance/index.php?section=contacts','Contactos y seguimiento'],'campaigns'=>['advance/index.php?section=campaigns','Campañas'],'templates'=>['advance/index.php?section=templates','Plantillas'],'dashboard'=>['advance/index.php','Resumen CRM']],
        'Gestión'=>['requests'=>['index.php','Solicitudes'],'system'=>['index.php?tab=sistema','Sistema'],'database'=>['base-datos.php','Base de datos']],
    ];
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=esc($title)?> | Explorando Lugares</title><?php foreach($styles as $style):?><link rel="stylesheet" href="<?=esc($base.$style)?>"><?php endforeach ?><link rel="stylesheet" href="<?=esc($base)?>admin.css?v=main-integration-2"></head><body class="admin-body"><a class="admin-skip" href="#admin-content">Saltar al contenido</a><div class="admin-shell <?=$signedIn?'':'admin-login'?>">
    <?php if($signedIn):?><aside class="admin-sidebar"><a class="admin-brand" href="<?=esc($base)?>reports.php"><img src="<?=esc($base)?>../assets/brand/explorando-lugares-logo-v2-transparent.png" alt="Explorando Lugares" width="180" height="90"></a><p class="admin-area-label">Administración</p><nav aria-label="Administración general"><?php foreach($groups as $label=>$items):?><div class="admin-nav-group"><p><?=esc($label)?></p><?php foreach($items as $key=>[$href,$name]):?><a href="<?=esc($base.$href)?>" <?=$active===$key?'aria-current="page"':''?>><?=esc($name)?></a><?php endforeach ?></div><?php endforeach ?></nav><p class="admin-private">Área privada · Explorando Lugares</p></aside><?php endif ?>
    <main id="admin-content" class="admin-main"><header class="admin-page-heading"><div><p class="admin-eyebrow">Explorando Lugares / Administración</p><h1><?=esc($title)?></h1><p><?=esc($description)?></p></div><div class="admin-session-actions"><span class="admin-private-badge"><?=$signedIn?'Acceso privado':'Inicio de sesión'?></span><?php if($signedIn):?><form method="post" action="<?=esc($base)?>index.php"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="pill light">Cerrar sesión</button></form><?php endif ?></div></header>
    <?php
}
function adminPageEnd(): void {echo '</main></div></body></html>';}
