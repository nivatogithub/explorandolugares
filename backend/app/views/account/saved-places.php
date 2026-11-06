<section class="account-personal-card" id="mis-lugares" aria-labelledby="saved-places-heading">
<?php if(($data['placesUpdated']??false)):?><p role="status">Tu lista de lugares se ha actualizado.</p><?php endif ?><h2 id="saved-places-heading">Mis lugares por conocer</h2>
<p>Guarda los lugares que quieres visitar y vuelve a sus guías cuando quieras. Tu lista es privada.</p>
<?php if($data['saveCandidate']):$candidate=$data['saveCandidate']; ?>
<form method="post" action="/negocios/portal/?tab=personal#mis-lugares">
<input type="hidden" name="csrf" value="<?=$e($data['csrf'])?>"><input type="hidden" name="action" value="save_place"><input type="hidden" name="place_slug" value="<?=$e($candidate['slug'])?>">
<button type="submit" class="pill">Guardar <?=$e($candidate['display_name'])?></button></form>
<?php endif ?>
<p><a class="pill" href="/lugares/?guardados=1">Ver mis lugares por conocer →</a></p>
</section>
