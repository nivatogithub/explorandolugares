<!doctype html>
<html lang="<?=$e($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=$e($copy['intro'])?>"><meta name="robots" content="<?=$filtered?'noindex,follow':'index,follow'?>">
<link rel="canonical" href="<?=$e($canonical)?>">
<?php foreach(PortalPresentation::alternates($context) as $language=>$url):?><link rel="alternate" hreflang="<?=$e($language)?>" href="<?=$e($url)?>"><?php endforeach ?>
<link rel="alternate" hreflang="x-default" href="<?=$e(PortalPresentation::alternates($context)['es'])?>">
<title><?=$e($title)?></title><?=PortalPresentation::assets(false)?><?php foreach($resources as $resource):?><link rel="stylesheet" href="<?=$e($resource)?>"><?php endforeach ?>
<meta property="og:type" content="website"><meta property="og:site_name" content="Explorando Lugares"><meta property="og:title" content="<?=$e($title)?>"><meta property="og:description" content="<?=$e($copy['intro'])?>"><meta property="og:url" content="<?=$e($canonical)?>">
<meta property="og:image" content="https://explorandolugares.com/assets/brand/explorando-lugares-logo-v2-transparent.png"><meta name="twitter:card" content="summary"><meta name="twitter:title" content="<?=$e($title)?>"><meta name="twitter:description" content="<?=$e($copy['intro'])?>">
<script type="application/ld+json"><?=$structured?></script>
<script defer src="<?=$e($script)?>"></script><script defer src="<?=$e($bookmarkScript)?>"></script>
<link rel="icon" href="/assets/brand/explorando-lugares-logo-v2-transparent.png" type="image/png"></head>
<body><a class="skip" href="#contenido"><?=$e($copy['skip'])?></a><?=PortalPresentation::header($context)?>
<main id="contenido" class="wrap"><section class="section destination-index place-directory">
<div class="destination-index-head"><div><p class="eyebrow">Explorando Lugares / <?=$e($copy['title'])?></p><h1><?=$e($copy['headingStart'])?> <em><?=$e($copy['headingEnd'])?></em></h1></div><p class="intro"><?=$e($copy['intro'])?></p></div>
<form class="place-directory-filters" method="get" action="<?=$e($path)?>" aria-label="<?=$e($copy['filters'])?>" data-place-filters data-updating="<?=$e($copy['updating'])?>">
<div class="place-directory-fields">
<div class="place-directory-search"><label for="place-search" class="visually-hidden"><?=$e($copy['search'])?></label><input id="place-search" type="search" name="q" maxlength="120" autocomplete="off" placeholder="<?=$e($copy['searchPlaceholder'])?>" value="<?=$e($filters['q'])?>"></div>
<?php foreach(['sector'=>['values'=>$data['sectors'],'label'=>'sector','all'=>'allSectors'],'department'=>['values'=>$data['departments'],'label'=>'department','all'=>'allDepartments']] as $key=>$field):?>
<label class="place-directory-field" for="place-filter-<?=$e($key)?>"><span><?=$e($copy[$field['label']])?></span><select id="place-filter-<?=$e($key)?>" name="<?=$e($key)?>"><option value=""><?=$e($copy[$field['all']])?></option>
<?php if($filters[$key]!==''&&!in_array($filters[$key],$field['values'],true)):?><option selected value="<?=$e($filters[$key])?>"><?=$e($filters[$key])?></option><?php endif ?>
<?php foreach($field['values'] as $value):?><option value="<?=$e($value)?>" <?=$filters[$key]===$value?'selected':''?>><?=$e($key==='sector'?$copy['sectorNames'][$value]:$value)?></option><?php endforeach ?></select></label>
<?php endforeach ?>
<div class="place-directory-actions"><label class="place-directory-heritage"><input type="checkbox" name="heritage" value="1" <?=$filters['heritage']?'checked':''?>><?=$e($copy['heritage'])?></label>
<label class="place-directory-saved"><input type="checkbox" name="guardados" value="1" <?=!empty($filters['saved'])?'checked':''?>><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4V3Z"/></svg><?=$e($copy['saved'])?></label>
<a class="place-directory-clear" href="<?=$e($path)?>" data-clear-filters><?=$e($copy['clear'])?></a>
</div></div><noscript><button type="submit" class="place-directory-fallback"><?=$e($copy['apply'])?></button></noscript>
</form>
<p class="place-directory-results" role="status"><?=$e($data['total']?sprintf($copy['results'],$data['first'],$data['last'],$data['total']):(!empty($filters['saved'])?$copy['savedEmpty']:$copy['empty']))?></p>
<div class="destination-grid" id="place-directory-grid">
<?php foreach($rows as $place):?><article class="destination-card <?=$place['heritageText']!==''?'has-heritage':''?>" data-place-slug="<?=$e($place['slug'])?>"><?php if($place['heritageText']!==''):?><span class="place-heritage-star" role="img" aria-label="<?=$e($place['heritageText'])?>" title="<?=$e($place['heritageText'])?>">★</span><?php endif ?><a class="destination-card-image" href="<?=$e($place['url'])?>"><?php if(!empty($place['asset_path'])):?><img src="/<?=$e(ltrim($place['asset_path'],'/'))?>" alt="<?=$e($place['alt_text']?:$place['display_name'])?>" loading="lazy"><?php endif ?></a><div class="copy">
<?php $placeLabels=$place['labels'];require __DIR__.'/place-labels.php'; ?><h2><a href="<?=$e($place['url'])?>"><?=$e($place['display_name'])?></a></h2><p><?=$e($place['intro'])?></p><div class="place-card-actions"><a class="text-link" href="<?=$e($place['url'])?>"><?=$e($copy['visit'])?></a><?php SavedPlaceAction::render($place['bookmark']); ?></div></div></article><?php endforeach ?>
</div>
<?php if($data['pages']>1):?><nav class="place-directory-pagination" aria-label="<?=$e($copy['pagination'])?>">
<?php if($previous):?><a rel="prev" href="<?=$e($previous)?>"><?=$e($copy['previous'])?></a><?php else:?><span aria-disabled="true"><?=$e($copy['previous'])?></span><?php endif ?>
<?php $lastNumber=0;foreach($pageLinks as $link):?><?php if($link['number']>$lastNumber+1):?><span aria-hidden="true">…</span><?php endif ?>
<?php if($link['number']===$data['page']):?><span aria-current="page"><?=$e((string)$link['number'])?></span><?php else:?><a href="<?=$e($link['url'])?>" aria-label="<?=$e(sprintf($copy['pageTitle'],$link['number']))?>"><?=$e((string)$link['number'])?></a><?php endif ?>
<?php $lastNumber=$link['number'];endforeach ?>
<?php if($next):?><a rel="next" href="<?=$e($next)?>"><?=$e($copy['next'])?></a><?php else:?><span aria-disabled="true"><?=$e($copy['next'])?></span><?php endif ?>
<small><?=$e(sprintf($copy['pageStatus'],$data['page'],$data['pages']))?></small></nav><?php endif ?>
</section></main><?=PortalPresentation::footer($context)?></body></html>
