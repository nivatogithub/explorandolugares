<?php if($bookmark['authenticated']):?>
<form class="saved-place-control" method="post" action="<?=e($bookmark['endpoint'])?>">
<input type="hidden" name="csrf" value="<?=e($bookmark['csrf'])?>"><input type="hidden" name="place" value="<?=e($bookmark['slug'])?>"><input type="hidden" name="locale" value="<?=e($bookmark['locale'])?>"><input type="hidden" name="action" value="<?=$bookmark['saved']?'remove_place':'save_place'?>">
<button type="submit" class="saved-place-action" aria-pressed="<?=$bookmark['saved']?'true':'false'?>" aria-label="<?=e($bookmark['label'])?>" title="<?=e($bookmark['label'])?>">
<?php else:?>
<a class="saved-place-action" href="<?=e($bookmark['endpoint'].'?'.http_build_query(['place'=>$bookmark['slug'],'locale'=>$bookmark['locale']]))?>" aria-label="<?=e($bookmark['label'])?>" title="<?=e($bookmark['help'])?>">
<?php endif ?>
<svg viewBox="0 0 24 24" width="22" height="22" fill="<?=$bookmark['saved']?'currentColor':'none'?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17l-6-4-6 4V4Z"/></svg>
<?php if($bookmark['authenticated']):?></button></form><?php else:?></a><?php endif ?>

<?php if($bookmark['error']):?><span role="alert"><?=e($bookmark['errorText'])?></span><?php endif ?>
