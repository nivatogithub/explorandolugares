<?php
declare(strict_types=1);
/** Shared home content. Receives copy, prefix, image URLs and escaping only. */
?>
<main id="contenido" class="wrap">
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
<a class="pill" href="<?=$e($prefix.'/lugares/')?>"><?=$e($copy['find_place'])?>
</a>
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
<section id="formas-de-explorar" class="section">
<div class="sectionhead">
<div>
<p class="eyebrow"><?=$e($copy['explore_eyebrow'])?>
</p>
<h2><?=$e($copy['explore_title'])?>
<br><?=$e($copy['explore_subtitle'])?>
</h2>
</div>
<p><?=$e($copy['explore_intro'])?>
</p>
</div>
<div class="experience-editorial">
<div class="experience-stories">
<article>
<span class="story-number"><?=$e($copy['discover_step'])?>
</span>
<h3><?=$e($copy['discover_title'])?>
</h3>
<p><?=$e($copy['discover_intro'])?>
</p>
</article>
<article>
<span class="story-number"><?=$e($copy['connect_step'])?>
</span>
<h3><?=$e($copy['connect_title'])?>
</h3>
<p><?=$e($copy['connect_intro'])?>
</p>
</article>
<article>
<span class="story-number"><?=$e($copy['experience_step'])?>
</span>
<h3><?=$e($copy['experience_title'])?>
</h3>
<p><?=$e($copy['experience_intro'])?>
</p>
</article>
</div>
</div>
</section>
<section id="destinos" class="section">
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
<div class="home-attractions" data-home-attractions>
<?php if($attractions): foreach($attractions as $item): require __DIR__.'/home-attraction-card.php'; endforeach; else: ?>
<p><?=$e($copy['places_fallback'])?></p>
<?php endif ?>
</div>
<p>
<a class="text-link" href="<?=$e($prefix.'/lugares/')?>"><?=$e($copy['all_places'])?>
</a>
</p>
</section>
<section class="section">
<div class="allies">
<div>
<p class="eyebrow"><?=$e($copy['business_eyebrow'])?>
</p>
<h2><?=$e($copy['business_title'])?>
<br><?=$e($copy['business_subtitle'])?>
</h2>
<p><?=$e($copy['business_intro'])?>
</p>
<a class="pill" href="/negocios-locales"><?=$e($copy['local_businesses'])?>
</a>
</div>
<div class="steps">
<p><?=$e($copy['business_step_1'])?>
</p>
<p><?=$e($copy['business_step_2'])?>
</p>
<p><?=$e($copy['business_step_3'])?>
</p>
<p><?=$e($copy['business_step_4'])?>
</p>
</div>
</div>
</section>
</main>
