<?php
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../public/business/editorial.php');
if(!preg_match('~<nav class="editorial-actions">(.*?)</nav>~s',$source,$matches))throw new RuntimeException('Missing editorial preview links.');
if(str_contains($matches[1],'target="_blank"'))throw new RuntimeException('Merchant preview opens another tab.');
if(str_contains($source,'Un pago o una aprobación del comerciante no publican automáticamente.'))throw new RuntimeException('Outdated publication instructions.');
if(!str_contains($source,'La vigencia empezará al publicarla.'))throw new RuntimeException('Missing publication start explanation.');
if(str_contains($source,'name="note" maxlength="2000"')||!str_contains($source,'data-text-counter data-autogrow'))throw new RuntimeException('Review note lost its limit, counter or growth.');
$registration=file_get_contents(__DIR__.'/../public/business/new-business-fields.php');
if(str_contains($registration,'id="business-location-reference"')||str_contains($registration,'Referencia adicional (opcional)'))throw new RuntimeException('Extra address reference returned.');
foreach(['product-title','product-description','product-photo','data-autogrow'] as $marker)if(!str_contains($registration,$marker))throw new RuntimeException('Missing product layout '.$marker);
$workspace=file_get_contents(__DIR__.'/../public/assets/business-workspace.js');
if(!str_contains($workspace,"namedItem('note').dispatchEvent(new Event('input'"))throw new RuntimeException('Reopened reason has a stale counter.');
$css=file_get_contents(__DIR__.'/../../ensayos/v1/assets/style.css');
if(!preg_match('~@media\(max-width:760px\)\{[^\n]*business-profile-copy\{grid-row:3\}[^\n]*business-profile-image\{grid-row:2;~',$css))throw new RuntimeException('Mobile photo order regressed.');
echo "OK: merchant review explains owner publication, previews stay in same tab and mobile photo precedes description/contact.\n";
