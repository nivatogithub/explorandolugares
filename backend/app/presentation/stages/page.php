<?php
declare(strict_types=1);
$prefix = match ($locale) { 'en' => '/en', 'pt-BR' => '/pt-br', default => '' };
$query = match ($locale) { 'en' => 'waterfalls', 'pt-BR' => 'cachoeiras', default => 'cascadas' };
$format = static fn($value) => number_format((int)$value, 0, '.', $locale === 'en' ? ',' : '.');
?>
<main id="contenido" class="portal-about">
<section class="hero">
<div>
<small>
<?=$escape($copy['kicker'])?>
</small>
<h1>
<?=$escape($copy['headline'])?>
</h1>
<p>
<?=$escape($copy['intro'])?>
</p>
<a class="pill" href="<?=$prefix?>/lugares/">
<?=$escape($copy['explore'])?>
</a>
</div>
<figure class="photo">
<img src="/assets/medellin-semirrealista/web/metro-metrocable-recorrido-800.webp" alt="<?=$escape($copy['heroAlt'])?>">
<figcaption>Medellín</figcaption>
</figure>
</section>
<?php if ($counts): ?>
<section aria-label="<?=$escape($copy['figures'])?>">
<div class="stats">
<div>
<strong class="number">
<?=$format($counts['guides'])?>
</strong>
<?=$escape($copy['guides'])?>
</div>
<div>
<strong class="number">
<?=$format($counts['heritage'])?>
</strong>
<?=$escape($copy['heritage'])?>
</div>
<div>
<strong class="number">
<?=$format($counts['businesses'])?>
</strong>
<?=$escape($copy['businesses'])?>
</div>
</div>
<p class="fine">
<a href="<?=$prefix?>/cobertura/colombia/#cobertura">
<?=$escape($copy['map'])?>
</a>
</p>
</section>
<?php endif ?>
<section class="journey">
<h2>
<?=$escape($copy['growth'])?>
</h2>
<div class="rail">
<article class="stop">
<span class="label">
<?=$escape($copy['beginning'])?>
</span>
<h3>
<?=$escape($copy['firstTitle'])?>
</h3>
<p>
<?=$escape($copy['firstBody'])?>
</p>
</article>
<article class="stop current">
<span class="label">
<?=$escape($copy['today'])?>
</span>
<h3>
<?=$escape($copy['todayTitle'])?>
</h3>
<p>
<?=$escape($copy['todayBody'])?>
</p>
<a href="<?=$prefix?>/lugares/?q=<?=$query?>">
<?=$escape($copy['trySearch'])?>
</a>
</article>
<article class="stop">
<span class="label">
<?=$escape($copy['ongoing'])?>
</span>
<h3>
<?=$escape($copy['ongoingTitle'])?>
</h3>
<p>
<?=$escape($copy['ongoingBody'])?>
</p>
</article>
</div>
<div class="open">
<img src="/assets/destinos/guatape-1.webp" alt="<?=$escape($copy['nextAlt'])?>">
<div>
<small>Guatapé · Antioquia</small>
<h3>
<?=$escape($copy['nextTitle'])?>
</h3>
<p>
<?=$escape($copy['nextBody'])?>
</p>
</div>
</div>
</section>
<section class="join">
<div>
<h2>
<?=$escape($copy['travellers'])?>
</h2>
<p>
<?=$escape($copy['travellerBody'])?>
</p>
<a href="/contact.php?lang=<?=$escape(strtolower($locale))?>">
<?=$escape($copy['suggest'])?>
</a>
</div>
<div>
<h2>
<?=$escape($copy['hosts'])?>
</h2>
<p>
<?=$escape($copy['hostBody'])?>
</p>
<a class="pill" href="<?=$prefix?>/negocios-locales/">
<?=$escape($copy['participate'])?>
</a>
</div>
</section>
</main>
