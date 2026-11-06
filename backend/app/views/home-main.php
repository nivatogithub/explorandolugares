<?php
declare(strict_types=1);
/** Shared home content. Receives copy, prefix, image URLs and escaping only. */
?>
<main id="contenido" class="wrap home-intro-proposal">
<section class="hero home-editorial-hero">
<div class="home-editorial-copy">
<p class="eyebrow"><?=$e($copy['hero_eyebrow'])?>
</p>
<h1><?=$e($copy['hero_title'])?> <em><?=$e($copy['hero_emphasis'])?>
</em>
</h1>
<p class="intro"><?=$e($copy['hero_intro'])?>
</p>
<div class="home-editorial-actions">
<a class="pill" href="#para-viajeros"><?=$e($copy['find_place'])?>
</a>
<a class="text-link" href="#para-negocios"><?=$e($copy['business_entry'])?></a>
</div>
</div>
<figure class="home-editorial-gallery" aria-label="<?=$e($copy['gallery_label'])?>">
<span class="home-gallery-accent" aria-hidden="true">
</span>
<a class="home-gallery-shot home-gallery-shot-main" href="<?=$e($prefix.'/lugares/')?>">
<img src="<?=$e($images['local_life'])?>" alt="<?=$e($copy['local_life_alt'])?>" width="1200" height="800" fetchpriority="high" decoding="async">
</a>
<a class="home-gallery-shot home-gallery-shot-city" href="<?=$e($prefix.'/lugares/')?>">
<img src="<?=$e($images['city'])?>" alt="<?=$e($copy['city_alt'])?>" width="1200" height="800" loading="lazy" decoding="async">
</a>
<span class="home-gallery-shot home-gallery-shot-jerico">
<img src="<?=$e($images['adventure'])?>" alt="<?=$e($copy['adventure_alt'])?>" width="1200" height="800" loading="lazy" decoding="async">
</span>
<span class="home-gallery-shot home-gallery-shot-experience">
<img src="<?=$e($images['mountains'])?>" alt="<?=$e($copy['mountains_alt'])?>" width="1200" height="800" loading="lazy" decoding="async">
</span>
</figure>
<p class="home-editorial-categories"><?=$e($copy['towns'])?> <span>·</span> <?=$e($copy['cities'])?> <span>·</span> <?=$e($copy['experiences'])?>
</p>
</section>
<section id="destinos" class="section">
<span id="formas-de-explorar" aria-hidden="true"></span>
<div id="para-viajeros" class="home-traveller-entry">
<div class="sectionhead">
<div>
<p class="eyebrow"><?=$e($copy['places_eyebrow'])?>
</p>
<h2><?=$e($copy['places_title'])?>
</h2>
</div>
<p><?=$e($copy['places_intro'])?>
</p>
</div>
<p class="home-traveller-action"><a class="pill" href="<?=$e($prefix.'/lugares/')?>"><?=$e($copy['all_places'])?></a></p>
</div>
<div class="home-attractions" data-home-attractions>
<?php if($attractions): foreach($attractions as $item): require __DIR__.'/home-attraction-card.php'; endforeach; else: ?>
<p><?=$e($copy['places_fallback'])?></p>
<?php endif ?>
</div>
</section>
<section id="para-negocios" class="section" aria-labelledby="home-business-title">
<div class="allies home-business-entry">
<div>
<p class="eyebrow"><?=$e($copy['business_eyebrow'])?></p>
<h2 id="home-business-title"><?=$e($copy['business_title'])?></h2>
<p><?=$e($copy['business_intro'])?></p>
</div>
<div class="home-business-actions">
<a class="pill" href="/negocios/registro/?tipo=business<?=$locale==='es'?'':'&amp;lang='.$e($locale)?>"><?=$e($copy['register_business'])?></a>
<a class="text-link" href="<?=$e($prefix.'/negocios-locales/')?>"><?=$e($copy['business_options'])?></a>
</div>
</div>
</section>
</main>
