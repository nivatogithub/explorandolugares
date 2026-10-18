<?php
// Shared private-panel skin. The public portal retains its own layout.
$adminIdentityVersion=substr(hash_file('sha256',__DIR__.'/../assets/identidad-neutral.css'),0,12);
$adminThemeVersion=substr(hash_file('sha256',__DIR__.'/../assets/admin-theme.css'),0,12);
?><link rel="stylesheet" href="/assets/cobertura/portal-tokens.css"><link rel="stylesheet" href="/assets/identidad-neutral.css?v=<?=esc($adminIdentityVersion)?>"><link rel="stylesheet" href="/assets/admin-theme.css?v=<?=esc($adminThemeVersion)?>">
