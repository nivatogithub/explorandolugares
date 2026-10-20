<?php
// Shared private-panel skin. The public portal retains its own layout.
$adminIdentityVersion=substr(hash_file('sha256',__DIR__.'/../assets/identidad-neutral.css'),0,12);
$adminThemeVersion=substr(hash_file('sha256',__DIR__.'/../assets/admin-theme.css'),0,12);
$adminBaseVersion=is_file(__DIR__.'/../assets/style.css')?substr(hash_file('sha256',__DIR__.'/../assets/style.css'),0,12):'missing';
$adminGridVersion=substr(hash_file('sha256',__DIR__.'/../assets/admin.css'),0,12);
?><link rel="stylesheet" href="/assets/style.css?v=<?=esc($adminBaseVersion)?>"><link rel="stylesheet" href="/assets/admin.css?v=<?=esc($adminGridVersion)?>"><link rel="stylesheet" href="/assets/cobertura/portal-tokens.css"><link rel="stylesheet" href="/assets/identidad-neutral.css?v=<?=esc($adminIdentityVersion)?>"><link rel="stylesheet" href="/assets/admin-theme.css?v=<?=esc($adminThemeVersion)?>">
