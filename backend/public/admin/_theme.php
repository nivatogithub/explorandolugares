<?php
require_once __DIR__.'/_asset.php';
foreach (['style.css','admin.css','cobertura/portal-tokens.css','identidad-neutral.css','admin-theme.css'] as $asset):
?><link rel="stylesheet" href="<?=esc(admin_asset($asset))?>"><?php endforeach ?>
